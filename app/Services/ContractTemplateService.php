<?php

namespace App\Services;

use App\Models\Contract;

class ContractTemplateService
{
    /**
     * @return array<string, string>
     */
    public function standardModelOptions(): array
    {
        return [
            'prestari_servicii_cadru' => 'Contract cadru de prestări servicii',
            'prestari_servicii_unic'  => 'Contract de prestări servicii',
        ];
    }

    public function standardModelContent(string $model): string
    {
        return match ($model) {
            'prestari_servicii_unic' => $this->modelPrestariServiciiUnic(),
            default => $this->modelPrestariServiciiCadru(),
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function standardModelCustomFields(string $model): array
    {
        return match ($model) {
            'prestari_servicii_unic' => [],
            default => [
                [
                    'key'        => 'billing_cycle',
                    'label'      => 'Ciclu facturare',
                    'field_type' => 'select',
                    'required'   => false,
                    'options'    => ['Lunar', 'Trimestrial', 'Anual', 'Unic'],
                ],
                [
                    'key'        => 'frequency',
                    'label'      => 'Frecvență servicii',
                    'field_type' => 'select',
                    'required'   => false,
                    'options'    => ['Lunar', 'Bilunar', 'Trimestrial', 'Semestrial', 'Anual'],
                ],
                [
                    'key'        => 'locations',
                    'label'      => 'Locații',
                    'field_type' => 'textarea',
                    'required'   => false,
                ],
                [
                    'key'        => 'service_scope',
                    'label'      => 'Sfera serviciilor',
                    'field_type' => 'textarea',
                    'required'   => false,
                ],
            ],
        };
    }

    /**
     * Returns available placeholders for contract templates.
     *
     * @return array<string, string>
     */
    public function placeholders(array $customFields = []): array
    {
        $items = [
            '{{company.name}}'            => 'Denumirea companiei furnizoare',
            '{{company.cif}}'             => 'CIF companie',
            '{{company.reg_com}}'         => 'Nr. Registrul Comerțului companie',
            '{{company.address}}'         => 'Adresă companie',
            '{{company.city}}'            => 'Localitate companie',
            '{{company.county}}'          => 'Județ companie',
            '{{company.iban}}'            => 'IBAN companie',
            '{{company.bank}}'            => 'Bancă companie',
            '{{client.name}}'             => 'Nume client',
            '{{client.cif}}'              => 'CIF client',
            '{{client.cnp}}'              => 'CNP client',
            '{{client.reg_com}}'          => 'Reg. Com. client',
            '{{client.address}}'          => 'Adresă client',
            '{{client.city}}'             => 'Localitate client',
            '{{client.county}}'           => 'Județ client',
            '{{client.phone}}'            => 'Telefon client',
            '{{client.email}}'            => 'Email client',
            '{{contract.number}}'         => 'Număr contract',
            '{{contract.type}}'           => 'Tip contract (numele șablonului selectat)',
            '{{contract.template}}'       => 'Șablon contract (alias pentru contract.type)',
            '{{contract.status}}'         => 'Status contract',
            '{{contract.signed_date}}'    => 'Data contractului (semnare, d.m.Y)',
            '{{contract.start_date}}'     => 'Data început (d.m.Y)',
            '{{contract.end_date}}'       => 'Data sfârșit (d.m.Y) sau "nedeterminat"',
            '{{contract.value}}'          => 'Valoare contract formatată',
            '{{contract.currency}}'       => 'Moneda contractului',
            '{{contract.notes}}'          => 'Observații contract',
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

    /**
     * Render template content by replacing placeholders with contract data.
     */
    public function render(Contract $contract, ?string $templateContent = null): string
    {
        $contract->loadMissing(['company', 'client', 'template']);
        $additionalAttributes = is_array($contract->additional_attributes) ? $contract->additional_attributes : [];

        $content = $templateContent;
        if (blank($content)) {
            $content = $contract->template?->content ?: $this->defaultTemplate();
        }

        $replacements = [
            '{{company.name}}'            => e($contract->company->name ?? ''),
            '{{company.cif}}'             => e($contract->company->cif ?? ''),
            '{{company.reg_com}}'         => e($contract->company->reg_com ?? ''),
            '{{company.address}}'         => e($contract->company->address ?? ''),
            '{{company.city}}'            => e($contract->company->city ?? ''),
            '{{company.county}}'          => e($contract->company->county ?? ''),
            '{{company.iban}}'            => e($contract->company->iban ?? ''),
            '{{company.bank}}'            => e($contract->company->bank ?? ''),
            '{{client.name}}'             => e($contract->client->name ?? ''),
            '{{client.cif}}'              => e($contract->client->cif ?? ''),
            '{{client.cnp}}'              => e($contract->client->cnp ?? ''),
            '{{client.reg_com}}'          => e($contract->client->reg_com ?? ''),
            '{{client.address}}'          => e($contract->client->address ?? ''),
            '{{client.city}}'             => e($contract->client->city ?? ''),
            '{{client.county}}'           => e($contract->client->county ?? ''),
            '{{client.phone}}'            => e($contract->client->phone ?? ''),
            '{{client.email}}'            => e($contract->client->email ?? ''),
            '{{contract.number}}'         => e($contract->number ?? ''),
            '{{contract.title}}'          => e($this->formatAttributeValue($additionalAttributes['contract_title'] ?? '')),
            '{{contract.type}}'           => e($contract->template?->name ?? ''),
            '{{contract.template}}'       => e($contract->template?->name ?? ''),
            '{{contract.status}}'         => e($contract->status?->label() ?? ''),
            '{{contract.signed_date}}'    => e($contract->signed_date?->format('d.m.Y') ?? $contract->start_date?->format('d.m.Y') ?? ''),
            '{{contract.start_date}}'     => e($contract->start_date?->format('d.m.Y') ?? ''),
            '{{contract.end_date}}'       => e($contract->end_date?->format('d.m.Y') ?? 'nedeterminat'),
            '{{contract.value}}'          => e($this->formatMoney((float) $contract->value, $contract->currency)),
            '{{contract.currency}}'       => e($contract->currency ?? 'RON'),
            '{{contract.billing_cycle}}'  => e($this->formatAttributeValue($additionalAttributes['billing_cycle'] ?? '')),
            '{{contract.notes}}'          => e($contract->notes ?? ''),
            '{{contract.ddd_frequency}}'  => e($this->formatAttributeValue($additionalAttributes['ddd_frequency'] ?? '')),
            '{{contract.ddd_locations}}'  => e($this->formatAttributeValue($additionalAttributes['ddd_locations'] ?? '')),
            '{{contract.paintball_sessions}}' => e($this->formatAttributeValue($additionalAttributes['paintball_sessions'] ?? '')),
            '{{contract.paintball_players}}'  => e($this->formatAttributeValue($additionalAttributes['paintball_players'] ?? '')),
        ];

        foreach ($additionalAttributes as $key => $value) {
            if (! is_string($key) || trim($key) === '') {
                continue;
            }

            $replacements['{{attr.' . $key . '}}'] = e($this->formatAttributeValue($value));
        }

        $rendered = strtr($content, $replacements);

        if ($rendered === strip_tags($rendered)) {
            return nl2br($rendered);
        }

        return $rendered;
    }

    private function formatMoney(float $value, ?string $currency): string
    {
        $currency = $currency ?: 'RON';

        return number_format($value, 2, ',', '.') . ' ' . $currency;
    }

    private function defaultTemplate(): string
    {
        return $this->modelPrestariServiciiCadru();
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

    private function modelPrestariServiciiCadru(): string
    {
        return <<<'TPL'
<p class="doc-kicker">Model standard</p>
<h2 class="doc-title">CONTRACT CADRU</h2>
<p class="doc-subtitle">Prestări Servicii</p>
<p class="doc-number">Nr. {{contract.number}} din {{contract.signed_date}}</p>

<h3>I. Părțile contractante</h3>
<p><strong>Prestator:</strong> {{company.name}}, CIF {{company.cif}}, Reg. Com. {{company.reg_com}}, sediu {{company.address}}, {{company.city}}, {{company.county}}, IBAN {{company.iban}}, banca {{company.bank}}.</p>
<p><strong>Beneficiar:</strong> {{client.name}}, CIF/CNP {{client.cif}}{{client.cnp}}, Reg. Com. {{client.reg_com}}, sediu {{client.address}}, {{client.city}}, {{client.county}}, contact {{client.phone}} / {{client.email}}.</p>

<h3>II. Obiectul contractului</h3>
<p>Prestatorul se obligă să furnizeze serviciile agreate de părți în cadrul prezentului contract, conform solicitărilor Beneficiarului.</p>

<h3>III. Durata contractului</h3>
<p>Contractul este valabil în perioada <strong>{{contract.start_date}}</strong> - <strong>{{contract.end_date}}</strong>.</p>

<h3>IV. Valoare și modalitate de plată</h3>
<p><strong>Valoare contract:</strong> {{contract.value}}</p>
<p><strong>Ciclu facturare:</strong> {{attr.billing_cycle}}</p>
<p><strong>Monedă:</strong> {{contract.currency}}</p>
<p>Termenele și condițiile de plată se stabilesc prin facturile emise în baza prezentului contract.</p>
<p><strong>Frecvență servicii:</strong> {{attr.frequency}}</p>
<p><strong>Locații:</strong> {{attr.locations}}</p>
<p><strong>Sfera serviciilor:</strong> {{attr.service_scope}}</p>

<h3>V. Drepturile și obligațiile părților</h3>
<ul>
    <li>Prestatorul va executa serviciile cu diligență profesională și în conformitate cu legislația aplicabilă.</li>
    <li>Beneficiarul va pune la dispoziție informațiile și condițiile necesare executării serviciilor.</li>
    <li>Beneficiarul va achita contravaloarea serviciilor la termenele convenite.</li>
</ul>

<h3>VI. Încetarea contractului</h3>
<p>Contractul poate înceta prin ajungere la termen, acordul părților sau denunțare unilaterală, cu notificare prealabilă conform legislației.</p>

<h3>VII. Dispoziții finale</h3>
<p>Litigiile se vor soluționa pe cale amiabilă, iar în caz contrar de instanțele competente.</p>
<div class="section-note">
    <strong>Observații:</strong> {{contract.notes}}
</div>

<table class="signature-table">
    <tr>
        <td>
            <div class="signature-title">Prestator</div>
            <div class="signature-line"></div>
        </td>
        <td style="text-align:right;">
            <div class="signature-title">Beneficiar</div>
            <div class="signature-line" style="margin-left:auto;"></div>
        </td>
    </tr>
</table>
TPL;
    }

    private function modelPrestariServiciiUnic(): string
    {
        return <<<'TPL'
<p class="doc-kicker">Model standard</p>
<h2 class="doc-title">CONTRACT DE PRESTĂRI SERVICII</h2>
<p class="doc-subtitle">Lucrare Unică</p>
<p class="doc-number">Nr. {{contract.number}} din {{contract.signed_date}}</p>

<h3>I. Părțile contractante</h3>
<p><strong>Prestator:</strong> {{company.name}}, CIF {{company.cif}}, Reg. Com. {{company.reg_com}}, sediu {{company.address}}, {{company.city}}, {{company.county}}, IBAN {{company.iban}}, banca {{company.bank}}.</p>
<p><strong>Beneficiar:</strong> {{client.name}}, CIF/CNP {{client.cif}}{{client.cnp}}, Reg. Com. {{client.reg_com}}, sediu {{client.address}}, {{client.city}}, {{client.county}}, contact {{client.phone}} / {{client.email}}.</p>

<h3>II. Obiectul contractului</h3>
<p>Prestatorul se obligă să execute o lucrare unică de prestări servicii pentru Beneficiar, conform solicitării agreate între părți.</p>

<h3>III. Termen de execuție</h3>
<p>Lucrarea se realizează în perioada <strong>{{contract.start_date}}</strong> - <strong>{{contract.end_date}}</strong>.</p>

<h3>IV. Valoare și plată</h3>
<p><strong>Valoare lucrare:</strong> {{contract.value}}</p>
<p><strong>Monedă:</strong> {{contract.currency}}</p>
<p>Plata se efectuează integral, conform facturii emise după finalizarea lucrării sau conform termenelor agreate de părți.</p>

<h3>V. Recepția lucrării</h3>
<p>La finalizarea serviciilor, părțile confirmă execuția prin document de recepție sau prin acceptarea explicită a lucrării.</p>

<h3>VI. Dispoziții finale</h3>
<div class="section-note">
    <strong>Observații:</strong> {{contract.notes}}
</div>

<table class="signature-table">
    <tr>
        <td>
            <div class="signature-title">Prestator</div>
            <div class="signature-line"></div>
        </td>
        <td style="text-align:right;">
            <div class="signature-title">Beneficiar</div>
            <div class="signature-line" style="margin-left:auto;"></div>
        </td>
    </tr>
</table>
TPL;
    }
}
