<?php

namespace App\Services;

use App\Enums\BillingCycle;
use App\Models\Contract;
use Illuminate\Support\Str;

class ContractTemplateService
{
    /**
     * @return array<string, string>
     */
    public function standardModelOptions(): array
    {
        return [
            'cadru_prestari_servicii' => 'Contract cadru de Prestări Servicii',
            'interventie_la_cerere'   => 'Contract de Intervenție la Cerere',
        ];
    }

    public function standardModelContent(string $model): string
    {
        return match ($model) {
            'interventie_la_cerere' => $this->modelInterventieLaCerere(),
            default                 => $this->modelCadruPrestariServicii(),
        };
    }

    /**
     * Returns available placeholders for contract templates.
     *
     * @return array<string, string>
     */
    public function placeholders(): array
    {
        return [
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
            '{{contract.title}}'          => 'Titlu contract',
            '{{contract.type}}'           => 'Tip contract (etichetă RO)',
            '{{contract.status}}'         => 'Status contract (etichetă RO)',
            '{{contract.start_date}}'     => 'Data început (d.m.Y)',
            '{{contract.end_date}}'       => 'Data sfârșit (d.m.Y) sau "nedeterminat"',
            '{{contract.value}}'          => 'Valoare contract formatată',
            '{{contract.currency}}'       => 'Moneda contractului',
            '{{contract.billing_cycle}}'  => 'Ciclu facturare (etichetă RO)',
            '{{contract.notes}}'          => 'Observații contract',
            '{{contract.ddd_frequency}}'  => 'Frecvență tratament DDD',
        ];
    }

    /**
     * Render template content by replacing placeholders with contract data.
     */
    public function render(Contract $contract, ?string $templateContent = null): string
    {
        $contract->loadMissing(['company', 'client', 'template']);

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
            '{{contract.title}}'          => e($contract->title ?? ''),
            '{{contract.type}}'           => e($contract->type?->label() ?? ''),
            '{{contract.status}}'         => e($contract->status?->label() ?? ''),
            '{{contract.start_date}}'     => e($contract->start_date?->format('d.m.Y') ?? ''),
            '{{contract.end_date}}'       => e($contract->end_date?->format('d.m.Y') ?? 'nedeterminat'),
            '{{contract.value}}'          => e($this->formatMoney((float) $contract->value, $contract->currency)),
            '{{contract.currency}}'       => e($contract->currency ?? 'RON'),
            '{{contract.billing_cycle}}'  => e($this->billingCycleLabel($contract)),
            '{{contract.notes}}'          => e($contract->notes ?? ''),
            '{{contract.ddd_frequency}}'  => e($contract->ddd_frequency ?? ''),
        ];

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

    private function billingCycleLabel(Contract $contract): string
    {
        if ($contract->billing_cycle instanceof BillingCycle) {
            return $contract->billing_cycle->label();
        }

        return match ((string) $contract->billing_cycle) {
            'lunar'       => 'Lunar',
            'trimestrial' => 'Trimestrial',
            'anual'       => 'Anual',
            'unic'        => 'Unic',
            default       => Str::of((string) $contract->billing_cycle)->replace('_', ' ')->title()->toString(),
        };
    }

    private function defaultTemplate(): string
    {
        return $this->modelCadruPrestariServicii();
    }

    private function modelCadruPrestariServicii(): string
    {
        return <<<'TPL'
<p class="doc-kicker">Model standard</p>
<h1 class="doc-title">CONTRACT CADRU</h1>
<p class="doc-subtitle">Prestări Servicii</p>
<p class="doc-number">Nr. {{contract.number}}</p>

<h3>I. Părțile contractante</h3>
<table class="party-table">
    <tr>
        <td class="party-label">Prestator</td>
        <td>
            <strong>{{company.name}}</strong><br>
            CIF {{company.cif}}, Reg. Com. {{company.reg_com}}<br>
            Sediu: {{company.address}}, {{company.city}}, {{company.county}}<br>
            IBAN: {{company.iban}} | Banca: {{company.bank}}
        </td>
    </tr>
    <tr>
        <td class="party-label">Beneficiar</td>
        <td>
            <strong>{{client.name}}</strong><br>
            CIF/CNP: {{client.cif}}{{client.cnp}} | Reg. Com.: {{client.reg_com}}<br>
            Sediu: {{client.address}}, {{client.city}}, {{client.county}}<br>
            Contact: {{client.phone}} | {{client.email}}
        </td>
    </tr>
</table>

<h3>II. Obiectul contractului</h3>
<p>Prestatorul se obligă să furnizeze serviciile descrise în cadrul prezentului contract: <strong>{{contract.title}}</strong>.</p>

<h3>III. Durata contractului</h3>
<p>Contractul este valabil în perioada <strong>{{contract.start_date}}</strong> - <strong>{{contract.end_date}}</strong>.</p>

<h3>IV. Valoare și modalitate de plată</h3>
<table class="summary-table">
    <tr>
        <td class="summary-label">Valoare contract</td>
        <td><strong>{{contract.value}}</strong></td>
    </tr>
    <tr>
        <td class="summary-label">Ciclu facturare</td>
        <td>{{contract.billing_cycle}}</td>
    </tr>
    <tr>
        <td class="summary-label">Monedă</td>
        <td>{{contract.currency}}</td>
    </tr>
</table>
<p>Termenele și condițiile de plată se stabilesc prin facturile emise în baza prezentului contract.</p>

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

    private function modelInterventieLaCerere(): string
    {
        return <<<'TPL'
<p class="doc-kicker">Model standard</p>
<h1 class="doc-title">CONTRACT</h1>
<p class="doc-subtitle">Intervenție la Cerere</p>
<p class="doc-number">Nr. {{contract.number}}</p>

<h3>I. Părțile contractante</h3>
<table class="party-table">
    <tr>
        <td class="party-label">Prestator</td>
        <td>
            <strong>{{company.name}}</strong><br>
            CIF {{company.cif}}, Reg. Com. {{company.reg_com}}<br>
            Sediu: {{company.address}}, {{company.city}}, {{company.county}}<br>
            IBAN: {{company.iban}} | Banca: {{company.bank}}
        </td>
    </tr>
    <tr>
        <td class="party-label">Beneficiar</td>
        <td>
            <strong>{{client.name}}</strong><br>
            CIF/CNP: {{client.cif}}{{client.cnp}} | Reg. Com.: {{client.reg_com}}<br>
            Sediu: {{client.address}}, {{client.city}}, {{client.county}}<br>
            Contact: {{client.phone}} | {{client.email}}
        </td>
    </tr>
</table>

<h3>II. Obiectul contractului</h3>
<p>Prestatorul va executa intervenții punctuale la solicitarea Beneficiarului pentru: <strong>{{contract.title}}</strong>.</p>

<h3>III. Lansarea comenzilor de intervenție</h3>
<p>Intervențiile se solicită de Beneficiar prin e-mail/telefon, iar Prestatorul confirmă disponibilitatea și termenul estimat de execuție.</p>

<h3>IV. Termen și execuție</h3>
<p>Prezentul contract este valabil în perioada {{contract.start_date}} - {{contract.end_date}}.</p>
<p>Durata fiecărei intervenții se stabilește în funcție de complexitatea lucrărilor.</p>

<h3>V. Preț și facturare</h3>
<table class="summary-table">
    <tr>
        <td class="summary-label">Valoare estimată</td>
        <td><strong>{{contract.value}}</strong></td>
    </tr>
    <tr>
        <td class="summary-label">Ciclu facturare</td>
        <td>{{contract.billing_cycle}}</td>
    </tr>
    <tr>
        <td class="summary-label">Monedă</td>
        <td>{{contract.currency}}</td>
    </tr>
</table>
<p>Facturarea se realizează conform ciclului stabilit sau per intervenție, după caz.</p>

<h3>VI. Recepția serviciilor</h3>
<p>La finalizarea intervenției, părțile pot semna proces-verbal/raport de intervenție, care confirmă serviciile prestate.</p>

<h3>VII. Răspundere contractuală</h3>
<p>Fiecare parte răspunde pentru neexecutarea sau executarea necorespunzătoare a obligațiilor asumate prin prezentul contract.</p>

<h3>VIII. Dispoziții finale</h3>
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
