<?php

namespace App\Services;

use App\Enums\DecisionStatus;
use App\Models\Company;
use App\Models\Decision;
use App\Models\DecisionAuditLog;
use App\Models\DecisionTemplate;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class DecisionService
{
    public function nextDecisionNumber(Company $company, ?CarbonInterface $decisionDate = null): int
    {
        $decisionDate = $decisionDate ?? now();
        $year = (int) $decisionDate->year;

        return DB::transaction(function () use ($company, $year): int {
            DB::table('companies')
                ->where('id', $company->id)
                ->lockForUpdate()
                ->first();

            $max = Decision::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('decision_year', $year)
                ->lockForUpdate()
                ->max('number');

            $next = ((int) $max) + 1;

            DecisionAuditLog::log('decision_number_reserved', [
                'company_id' => $company->id,
                'payload' => ['next_number' => $next, 'decision_year' => $year],
            ]);

            return $next;
        });
    }

    public function issueDecision(Decision $decision): void
    {
        DB::transaction(function () use ($decision): void {
            /** @var Decision $locked */
            $locked = Decision::withoutGlobalScopes()
                ->where('id', $decision->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === DecisionStatus::Issued) {
                return;
            }

            $locked->loadMissing(['company', 'template']);

            $this->validateCustomAttributes($locked);

            if (! $locked->decision_date) {
                $locked->decision_date = now()->toDateString();
            }

            $decisionYear = (int) $locked->decision_date->year;

            if (blank($locked->legal_representative_name)) {
                $locked->legal_representative_name = $locked->company?->administrator ?? '';
            }

            if (blank($locked->title)) {
                $locked->title = $locked->template?->name ?? 'Decizie administrativă';
            }

            if (! $locked->number) {
                $locked->number = $this->nextDecisionNumber($locked->company, $locked->decision_date);
            }

            $previousDecision = Decision::withoutGlobalScopes()
                ->where('company_id', $locked->company_id)
                ->where('decision_year', $decisionYear)
                ->whereNotNull('number')
                ->where('id', '!=', $locked->id)
                ->where('number', '<', (int) $locked->number)
                ->orderByDesc('number')
                ->first();

            if ($previousDecision && $previousDecision->decision_date && $locked->decision_date->lt($previousDecision->decision_date)) {
                DecisionAuditLog::log('decision_issue_failed', [
                    'company_id' => $locked->company_id,
                    'decision_id' => $locked->id,
                    'decision_template_id' => $locked->decision_template_id,
                    'result' => 'failed',
                    'payload' => [
                        'reason' => 'chronology_violation',
                        'previous_decision_number' => $previousDecision->number,
                        'previous_decision_date' => $previousDecision->decision_date->toDateString(),
                        'current_decision_date' => $locked->decision_date->toDateString(),
                    ],
                ]);

                throw new \RuntimeException('Cronologia este invalidă: data deciziei trebuie să fie egală sau ulterioară ultimei decizii numerotate.');
            }

            $locked->content_snapshot = $this->renderDecisionContent($locked);
            $locked->decision_year = $decisionYear;
            $locked->status = DecisionStatus::Issued;
            $locked->save();

            DecisionAuditLog::log('decision_issued', [
                'company_id' => $locked->company_id,
                'decision_id' => $locked->id,
                'decision_template_id' => $locked->decision_template_id,
            ]);
        });
    }

    public function renderDecisionContent(Decision $decision): string
    {
        $decision->loadMissing(['company', 'template']);

        $templateContent = $decision->template?->body_template ?: '<p>{{decision.title}}</p>';
        $attrs = is_array($decision->custom_attributes) ? $decision->custom_attributes : [];

        $replacements = [
            '{{company.name}}' => e($decision->company?->name ?? ''),
            '{{company.cif}}' => e($decision->company?->cif ?? ''),
            '{{company.reg_com}}' => e($decision->company?->reg_com ?? ''),
            '{{company.address}}' => e($decision->company?->address ?? ''),
            '{{decision.number}}' => e((string) ($decision->number ?? '')), 
            '{{decision.date}}' => e($decision->decision_date?->format('d.m.Y') ?? ''),
            '{{decision.title}}' => e($decision->title ?? ''),
            '{{decision.legal_representative_name}}' => e($decision->legal_representative_name ?? ''),
            '{{decision.notes}}' => e($decision->notes ?? ''),
        ];

        foreach ($attrs as $key => $value) {
            if (! is_string($key) || trim($key) === '') {
                continue;
            }

            if ($key === 'assets') {
                $formattedAssets = $this->formatAssetsForDecision($value);
                $replacements['{{attr.' . $key . '}}'] = $formattedAssets !== null
                    ? $formattedAssets
                    : e($this->formatAttributeValue($value));
                continue;
            }

            $replacements['{{attr.' . $key . '}}'] = e($this->formatAttributeValue($value));
        }

        $rendered = strtr($templateContent, $replacements);

        if ($rendered === strip_tags($rendered)) {
            return nl2br($rendered);
        }

        return $rendered;
    }

    public function validateCustomAttributes(Decision $decision): void
    {
        $schema = $decision->template?->custom_fields_schema;
        $attributes = is_array($decision->custom_attributes) ? $decision->custom_attributes : [];

        if (! is_array($schema)) {
            return;
        }

        foreach ($schema as $field) {
            if (! is_array($field)) {
                continue;
            }

            $key = (string) ($field['key'] ?? '');
            $required = (bool) ($field['required'] ?? false);

            if ($key === '' || ! $required) {
                continue;
            }

            $value = $attributes[$key] ?? null;

            $isEmpty = $value === null
                || $value === ''
                || (is_array($value) && $value === []);

            if ($isEmpty) {
                throw new \RuntimeException('Lipsește atributul obligatoriu: ' . ($field['label'] ?? $key));
            }
        }
    }

    private function formatAttributeValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'Da' : 'Nu';
        }

        if (is_array($value)) {
            return collect($value)
                ->map(function (mixed $item): string {
                    if (is_array($item)) {
                        return collect($item)
                            ->filter(fn (mixed $v): bool => $v !== null && $v !== '')
                            ->map(fn (mixed $v, string|int $k): string => is_string($k) ? "{$k}: {$v}" : (string) $v)
                            ->implode(', ');
                    }

                    if (is_bool($item)) {
                        return $item ? 'Da' : 'Nu';
                    }

                    return (string) $item;
                })
                ->filter(fn (string $item): bool => $item !== '')
                ->implode('; ');
        }

        return (string) $value;
    }

    private function formatAssetsForDecision(mixed $value): ?string
    {
        if (! is_array($value) || $value === []) {
            return null;
        }

        $isListOfObjects = collect($value)->every(fn (mixed $item): bool => is_array($item));
        if (! $isListOfObjects) {
            return null;
        }

        $lines = collect($value)
            ->values()
            ->map(function (array $asset, int $index): string {
                $name = trim((string) ($asset['name'] ?? ''));
                $serial = trim((string) ($asset['serial_number'] ?? ''));
                $reason = trim((string) ($asset['reason'] ?? ''));

                if ($name === '' && $serial === '' && $reason === '') {
                    return '';
                }

                $parts = [];
                $parts[] = e($name !== '' ? $name : 'N/A');
                if ($serial !== '') {
                    $parts[] = 'serie ' . e($serial);
                }
                if ($reason !== '') {
                    $parts[] = 'motiv: ' . e($reason);
                }

                return ($index + 1) . '. ' . implode(', ', $parts);
            })
            ->filter(fn (string $line): bool => $line !== '')
            ->values();

        if ($lines->isEmpty()) {
            return null;
        }

        return $lines->implode('<br>');
    }

    /**
     * @return array<string, string>
     */
    public function placeholders(array $customFields = []): array
    {
        $items = [
            '{{company.name}}' => 'Denumire companie',
            '{{company.cif}}' => 'CIF companie',
            '{{company.reg_com}}' => 'Reg. Comerț companie',
            '{{company.address}}' => 'Adresă companie',
            '{{decision.number}}' => 'Număr decizie',
            '{{decision.date}}' => 'Data deciziei',
            '{{decision.title}}' => 'Titlu decizie',
            '{{decision.legal_representative_name}}' => 'Nume reprezentant legal',
            '{{decision.notes}}' => 'Observații',
        ];

        foreach ($customFields as $field) {
            if (! is_array($field)) {
                continue;
            }

            $key = trim((string) ($field['key'] ?? ''));
            if ($key === '') {
                continue;
            }

            $label = trim((string) ($field['label'] ?? ''));
            $items['{{attr.' . $key . '}}'] = $label !== ''
                ? 'Câmp personalizat: ' . $label
                : 'Câmp personalizat: ' . $key;
        }

        return $items;
    }

    public function preloadTitleFromTemplate(?int $templateId): ?string
    {
        if (! $templateId) {
            return null;
        }

        $template = DecisionTemplate::find($templateId);

        return $template?->name;
    }
}
