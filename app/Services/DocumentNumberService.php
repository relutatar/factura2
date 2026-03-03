<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\NumberingRange;
use App\Models\Proforma;
use App\Models\Receipt;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class DocumentNumberService
{
    /**
     * Preview next document number without reserving it.
     *
     * @return array{numbering_range_id:int,series:string,number:int,full_number:string}|null
     */
    public function peekNextNumber(
        Company $company,
        string $documentType,
        ?string $workPointCode = null,
        ?CarbonInterface $issuedAt = null
    ): ?array {
        $issuedAt = $issuedAt ?? now();
        $range = $this->resolveActiveRange($company->id, $documentType, $workPointCode, (int) $issuedAt->year, false);

        if (! $range) {
            return null;
        }

        $this->assertWithinRange($range);

        if ((int) $range->next_number > (int) $range->end_number) {
            return null;
        }

        $number = (int) $range->next_number;

        return [
            'numbering_range_id' => (int) $range->id,
            'series' => (string) $range->series,
            'number' => $number,
            'full_number' => $this->formatFullNumber((string) $range->series, $number),
            'work_point_code' => $range->work_point_code,
        ];
    }

    /**
     * Reserve and return next document number from active range.
     *
     * @return array{numbering_range_id:int,series:string,number:int,full_number:string}
     */
    public function reserveNextNumber(
        Company $company,
        string $documentType,
        ?string $workPointCode = null,
        ?CarbonInterface $issuedAt = null
    ): array {
        $issuedAt = $issuedAt ?? now();
        $year = (int) $issuedAt->year;

        return DB::transaction(function () use ($company, $documentType, $workPointCode, $issuedAt, $year): array {
            $range = $this->resolveActiveRange($company->id, $documentType, $workPointCode, $year, true);

            if (! $range) {
                throw new \RuntimeException("Nu există plajă activă pentru {$documentType} în anul {$year}.");
            }

            $this->assertWithinRange($range);

            if ((int) $range->next_number > (int) $range->end_number) {
                throw new \RuntimeException("Plaja de numerotare {$range->series} este epuizată.");
            }

            $number = (int) $range->next_number;
            $this->validateChronology(
                companyId: (int) $company->id,
                documentType: $documentType,
                series: (string) $range->series,
                workPointCode: $this->normalizeWorkPointCode($workPointCode),
                issuedAt: $issuedAt,
                number: $number,
            );

            $range->update(['next_number' => $number + 1]);

            return [
                'numbering_range_id' => (int) $range->id,
                'series' => (string) $range->series,
                'number' => $number,
                'full_number' => $this->formatFullNumber((string) $range->series, $number),
                'work_point_code' => $range->work_point_code,
            ];
        });
    }

    /**
     * Return max used number for the same scope; null when unused.
     */
    public function getMaxUsedNumberInRange(NumberingRange $range): ?int
    {
        $workPointCode = $this->normalizeWorkPointCode($range->work_point_code);

        if ($range->document_type === 'factura') {
            $maxNumber = Invoice::withoutGlobalScopes()
                ->where('company_id', (int) $range->company_id)
                ->where('series', (string) $range->series)
                ->when(
                    $workPointCode === null,
                    fn ($query) => $query->whereNull('work_point_code'),
                    fn ($query) => $query->where('work_point_code', $workPointCode)
                )
                ->max('number');

            return $maxNumber !== null ? (int) $maxNumber : null;
        }

        if ($range->document_type === 'proforma') {
            $maxNumber = Proforma::withoutGlobalScopes()
                ->where('company_id', (int) $range->company_id)
                ->where('series', (string) $range->series)
                ->when(
                    $workPointCode === null,
                    fn ($query) => $query->whereNull('work_point_code'),
                    fn ($query) => $query->where('work_point_code', $workPointCode)
                )
                ->max('number');

            return $maxNumber !== null ? (int) $maxNumber : null;
        }

        if ($range->document_type === 'chitanta') {
            $maxNumber = Receipt::withoutGlobalScopes()
                ->where('company_id', (int) $range->company_id)
                ->where('series', (string) $range->series)
                ->when(
                    $workPointCode === null,
                    fn ($query) => $query->whereNull('work_point_code'),
                    fn ($query) => $query->where('work_point_code', $workPointCode)
                )
                ->max('number');

            return $maxNumber !== null ? (int) $maxNumber : null;
        }

        return null;
    }

    /**
     * Validate chronology across the same company/document_type/series/work_point scope.
     */
    public function validateChronology(
        int $companyId,
        string $documentType,
        string $series,
        ?string $workPointCode,
        CarbonInterface $issuedAt,
        int $number
    ): void {
        $lastEmitted = $this->getLastEmittedDocument($companyId, $documentType, $series, $workPointCode);

        if (! $lastEmitted) {
            return;
        }

        $lastIssueDate = Carbon::parse($lastEmitted['issue_date'])->startOfDay();
        $newIssueDate = Carbon::instance($issuedAt)->startOfDay();

        if ($newIssueDate->lt($lastIssueDate)) {
            throw new \RuntimeException(
                sprintf(
                    'Data documentului (%s) nu poate fi anterioară ultimei emiteri din serie (%s).',
                    $newIssueDate->format('d.m.Y'),
                    $lastIssueDate->format('d.m.Y'),
                )
            );
        }

        if ($number <= (int) $lastEmitted['number']) {
            throw new \RuntimeException('Cronologia numerotării este invalidă pentru seria selectată.');
        }
    }

    /**
     * Validate range integrity boundaries.
     */
    public function assertWithinRange(NumberingRange $range): void
    {
        $startNumber = (int) $range->start_number;
        $endNumber = (int) $range->end_number;
        $nextNumber = (int) $range->next_number;

        if ($startNumber <= 0 || $endNumber <= 0 || $nextNumber <= 0) {
            throw new \RuntimeException('Plaja de numerotare conține valori invalide.');
        }

        if ($startNumber > $endNumber) {
            throw new \RuntimeException('Plaja de numerotare este invalidă: numărul de început este mai mare decât numărul de final.');
        }

        if ($nextNumber < $startNumber || $nextNumber > ($endNumber + 1)) {
            throw new \RuntimeException('Plaja de numerotare este invalidă: next_number este în afara limitelor permise.');
        }
    }

    /**
     * @return array{number:int,issue_date:string}|null
     */
    private function getLastEmittedDocument(
        int $companyId,
        string $documentType,
        string $series,
        ?string $workPointCode
    ): ?array {
        $workPointCode = $this->normalizeWorkPointCode($workPointCode);

        if ($documentType === 'factura') {
            $query = Invoice::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('series', $series)
                ->when(
                    $workPointCode === null,
                    fn ($builder) => $builder->whereNull('work_point_code'),
                    fn ($builder) => $builder->where('work_point_code', $workPointCode)
                )
                ->whereNotNull('number')
                ->whereNotNull('issue_date');

            return $query
                ->orderByDesc('number')
                ->first(['number', 'issue_date'])
                ?->toArray();
        }

        if ($documentType === 'proforma') {
            $query = Proforma::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('series', $series)
                ->when(
                    $workPointCode === null,
                    fn ($builder) => $builder->whereNull('work_point_code'),
                    fn ($builder) => $builder->where('work_point_code', $workPointCode)
                )
                ->whereNotNull('number')
                ->whereNotNull('issue_date');

            return $query
                ->orderByDesc('number')
                ->first(['number', 'issue_date'])
                ?->toArray();
        }

        if ($documentType === 'chitanta') {
            $query = Receipt::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('series', $series)
                ->when(
                    $workPointCode === null,
                    fn ($builder) => $builder->whereNull('work_point_code'),
                    fn ($builder) => $builder->where('work_point_code', $workPointCode)
                )
                ->whereNotNull('number')
                ->whereNotNull('issue_date');

            return $query
                ->orderByDesc('number')
                ->first(['number', 'issue_date'])
                ?->toArray();
        }

        return null;
    }

    private function resolveActiveRange(
        int $companyId,
        string $documentType,
        ?string $workPointCode,
        int $year,
        bool $lockForUpdate
    ): ?NumberingRange {
        $workPointCode = $this->normalizeWorkPointCode($workPointCode);

        $query = NumberingRange::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('document_type', $documentType)
            ->where('fiscal_year', $year)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->when(
                $workPointCode === null,
                fn ($builder) => $builder->whereNull('work_point_code'),
                fn ($builder) => $builder->where('work_point_code', $workPointCode)
            )
            ->orderBy('id');

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    private function normalizeWorkPointCode(?string $workPointCode): ?string
    {
        $workPointCode = trim((string) $workPointCode);

        return $workPointCode !== '' ? $workPointCode : null;
    }

    private function formatFullNumber(string $series, int $number): string
    {
        return $series . '-' . str_pad((string) $number, 4, '0', STR_PAD_LEFT);
    }
}
