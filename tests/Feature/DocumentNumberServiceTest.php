<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\NumberingRange;
use App\Services\DocumentNumberService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentNumberServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reserves_unique_numbers_sequentially(): void
    {
        $company = $this->createCompany();
        $this->createRange($company, 'factura', 'NOD', 1, 999, 1);

        $service = app(DocumentNumberService::class);

        $first = $service->reserveNextNumber($company, 'factura');
        $second = $service->reserveNextNumber($company, 'factura');

        $this->assertSame(1, $first['number']);
        $this->assertSame(2, $second['number']);
        $this->assertNotSame($first['full_number'], $second['full_number']);
    }

    public function test_it_blocks_when_range_is_exhausted(): void
    {
        $company = $this->createCompany();
        $this->createRange($company, 'factura', 'NOD', 1, 2, 3);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Plaja de numerotare NOD este epuizată.');

        app(DocumentNumberService::class)->reserveNextNumber($company, 'factura');
    }

    public function test_it_blocks_backdated_chronology_in_same_scope(): void
    {
        $company = $this->createCompany();
        $client = $this->createClient($company);

        $range = $this->createRange($company, 'factura', 'NOD', 1, 999, 5);

        Invoice::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => InvoiceStatus::Trimisa,
            'series' => 'NOD',
            'number' => 4,
            'full_number' => 'NOD-0004',
            'numbering_range_id' => $range->id,
            'work_point_code' => null,
            'issue_date' => Carbon::create(2026, 3, 3)->toDateString(),
            'due_date' => Carbon::create(2026, 4, 3)->toDateString(),
            'payment_method' => 'virament_bancar',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Data documentului');

        app(DocumentNumberService::class)->reserveNextNumber(
            company: $company,
            documentType: 'factura',
            issuedAt: Carbon::create(2026, 3, 2),
        );
    }

    public function test_it_allows_same_number_for_different_work_points(): void
    {
        $company = $this->createCompany();
        $this->createRange($company, 'factura', 'NOD', 1, 999, 1, 'WP-A');
        $this->createRange($company, 'factura', 'NOD', 1, 999, 1, 'WP-B');

        $service = app(DocumentNumberService::class);

        $a = $service->reserveNextNumber($company, 'factura', 'WP-A');
        $b = $service->reserveNextNumber($company, 'factura', 'WP-B');

        $this->assertSame(1, $a['number']);
        $this->assertSame(1, $b['number']);
        $this->assertNotSame($a['numbering_range_id'], $b['numbering_range_id']);
    }

    public function test_it_ignores_soft_deleted_ranges_for_reservations(): void
    {
        $company = $this->createCompany();

        $deletedRange = $this->createRange($company, 'factura', 'OLD', 1, 999, 10);
        $deletedRange->delete();

        $this->createRange($company, 'factura', 'NOD', 1, 999, 1);

        $reserved = app(DocumentNumberService::class)->reserveNextNumber($company, 'factura');

        $this->assertSame('NOD', $reserved['series']);
        $this->assertSame(1, $reserved['number']);
    }

    public function test_emitted_invoice_numbering_is_immutable(): void
    {
        $company = $this->createCompany();
        $client = $this->createClient($company);
        $range = $this->createRange($company, 'factura', 'NOD', 1, 999, 8);

        $invoice = Invoice::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => InvoiceStatus::Trimisa,
            'series' => 'NOD',
            'number' => 7,
            'full_number' => 'NOD-0007',
            'numbering_range_id' => $range->id,
            'work_point_code' => null,
            'issue_date' => Carbon::create(2026, 3, 3)->toDateString(),
            'due_date' => Carbon::create(2026, 4, 3)->toDateString(),
            'payment_method' => 'virament_bancar',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Numerotarea facturii nu mai poate fi modificată după emitere.');

        $invoice->update(['number' => 21]);
    }

    private function createCompany(): Company
    {
        return Company::query()->create([
            'name' => 'Companie Test',
            'administrator' => 'Admin Test',
            'cif' => 'RO' . random_int(10000000, 99999999),
        ]);
    }

    private function createClient(Company $company): Client
    {
        return Client::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'type' => 'persoana_juridica',
            'name' => 'Client Test',
            'cif' => 'RO' . random_int(10000000, 99999999),
        ]);
    }

    private function createRange(
        Company $company,
        string $documentType,
        string $series,
        int $startNumber,
        int $endNumber,
        int $nextNumber,
        ?string $workPointCode = null
    ): NumberingRange {
        return NumberingRange::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'document_type' => $documentType,
            'fiscal_year' => (int) now()->year,
            'series' => $series,
            'start_number' => $startNumber,
            'end_number' => $endNumber,
            'next_number' => $nextNumber,
            'work_point_code' => $workPointCode,
            'is_active' => true,
        ]);
    }
}
