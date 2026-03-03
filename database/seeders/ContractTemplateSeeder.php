<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\ContractTemplate;
use Illuminate\Database\Seeder;

class ContractTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $company = Company::withoutGlobalScopes()->where('cif', 'RO27864858')->first();

        if (! $company) {
            return;
        }

        ContractTemplate::updateOrCreate(
            [
                'company_id' => $company->id,
                'name' => 'Contract cadru DDD',
            ],
            [
                'description' => null,
                'content' => <<<'HTML'
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
<p><strong>Ciclu facturare:</strong> {{contract.billing_cycle}}</p>
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
HTML,
                'custom_fields' => [
                    [
                        'key' => 'frequency',
                        'label' => 'Frecvență servicii',
                        'options' => ['Lunar', 'Bilunar', 'Trimestrial', 'Semestrial', 'Anual'],
                        'required' => false,
                        'field_type' => 'select',
                    ],
                    [
                        'key' => 'locations',
                        'label' => 'Locații',
                        'required' => false,
                        'field_type' => 'textarea',
                    ],
                    [
                        'key' => 'service_scope',
                        'label' => 'Sfera serviciilor',
                        'required' => false,
                        'field_type' => 'textarea',
                    ],
                ],
                'is_default' => true,
                'is_active' => true,
            ]
        );
    }
}
