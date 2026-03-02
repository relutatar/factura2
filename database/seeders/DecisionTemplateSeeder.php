<?php

namespace Database\Seeders;

use App\Models\DecisionTemplate;
use Illuminate\Database\Seeder;

class DecisionTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'code' => 'decizie_numerotare',
                'name' => 'Decizie Numerotare (Facturi/Chitanțe)',
                'body_template' => $this->bodyNumerotare(),
                'custom_fields_schema' => [
                    ['key' => 'fiscal_year', 'label' => 'An', 'field_type' => 'number', 'required' => true],
                    ['key' => 'invoice_series', 'label' => 'Serie facturi', 'field_type' => 'text', 'required' => true],
                    ['key' => 'invoice_start_number', 'label' => 'Număr început facturi', 'field_type' => 'number', 'required' => true],
                    ['key' => 'invoice_end_number', 'label' => 'Număr final facturi', 'field_type' => 'number', 'required' => true],
                    ['key' => 'receipt_series', 'label' => 'Serie chitanțe', 'field_type' => 'text', 'required' => true],
                    ['key' => 'receipt_start_number', 'label' => 'Număr început chitanțe', 'field_type' => 'number', 'required' => true],
                    ['key' => 'receipt_end_number', 'label' => 'Număr final chitanțe', 'field_type' => 'number', 'required' => true],
                    ['key' => 'responsible_emission_person', 'label' => 'Responsabil de emitere', 'field_type' => 'text', 'required' => true],
                ],
            ],
            [
                'code' => 'decizie_inventar',
                'name' => 'Decizie Inventariere Anuală',
                'body_template' => $this->bodyInventar(),
                'custom_fields_schema' => [
                    ['key' => 'commission_president', 'label' => 'Nume Președinte', 'field_type' => 'text', 'required' => true],
                    ['key' => 'commission_member', 'label' => 'Nume Membru', 'field_type' => 'text', 'required' => true],
                    ['key' => 'inventory_start_date', 'label' => 'Data început inventar', 'field_type' => 'date', 'required' => true],
                    ['key' => 'inventory_end_date', 'label' => 'Data final', 'field_type' => 'date', 'required' => true],
                ],
            ],
            [
                'code' => 'decizie_decontare',
                'name' => 'Decizie Avansuri spre Decontare',
                'body_template' => $this->bodyDecontare(),
                'custom_fields_schema' => [
                    ['key' => 'daily_advance_limit_ron', 'label' => 'Limită avans zilnic (RON)', 'field_type' => 'number', 'required' => true],
                    ['key' => 'authorized_persons', 'label' => 'Persoane autorizate', 'field_type' => 'text', 'required' => true],
                    ['key' => 'justification_term_working_days', 'label' => 'Termen justificare (zile lucrătoare)', 'field_type' => 'number', 'required' => true],
                ],
            ],
            [
                'code' => 'decizie_norma_combustibil',
                'name' => 'Decizie Normă Combustibil',
                'body_template' => $this->bodyNormaCombustibil(),
                'custom_fields_schema' => [
                    ['key' => 'vehicle_make', 'label' => 'Marcă', 'field_type' => 'text', 'required' => true],
                    ['key' => 'vehicle_registration_number', 'label' => 'Nr. Auto', 'field_type' => 'text', 'required' => true],
                    ['key' => 'consumption_l_per_100km', 'label' => 'Consum (l/100km)', 'field_type' => 'number', 'required' => true],
                    [
                        'key' => 'norm_basis',
                        'label' => 'Baza normei',
                        'field_type' => 'select',
                        'required' => true,
                        'options' => [
                            'carte_tehnica' => 'Carte tehnică',
                            'teste_proprii' => 'Teste proprii',
                            'alta_baza' => 'Altă bază',
                        ],
                    ],
                    ['key' => 'norm_basis_notes', 'label' => 'Detalii bază normă', 'field_type' => 'text', 'required' => false],
                ],
            ],
            [
                'code' => 'decizie_casare',
                'name' => 'Decizie de Casare (Scoatere din Uz)',
                'body_template' => $this->bodyCasare(),
                'custom_fields_schema' => [
                    ['key' => 'disposal_committee_members', 'label' => 'Comisie de casare (nume)', 'field_type' => 'text', 'required' => true],
                    ['key' => 'assets', 'label' => 'Active propuse pentru casare', 'field_type' => 'assets', 'required' => true],
                    ['key' => 'recycling_center_name', 'label' => 'Centru reciclare', 'field_type' => 'text', 'required' => false],
                ],
            ],
        ];

        foreach ($templates as $template) {
            DecisionTemplate::updateOrCreate(
                ['company_id' => null, 'code' => $template['code']],
                [
                    'name' => $template['name'],
                    'category' => 'Decizii Administrative',
                    'body_template' => $template['body_template'],
                    'custom_fields_schema' => $template['custom_fields_schema'],
                    'is_active' => true,
                ]
            );
        }
    }

    private function bodyNumerotare(): string
    {
        return <<<'TPL'
<h2>DECIZIE Nr. {{decision.number}} / {{decision.date}}</h2>
<p><strong>Art. 1.</strong> Se aprobă seriile și numerele documentelor pentru anul {{attr.fiscal_year}}:</p>
<p>Facturi: Seria {{attr.invoice_series}}, de la nr. {{attr.invoice_start_number}} la {{attr.invoice_end_number}}.</p>
<p>Chitanțe: Seria {{attr.receipt_series}}, de la nr. {{attr.receipt_start_number}} la {{attr.receipt_end_number}}.</p>
<p><strong>Art. 2.</strong> Responsabil de emitere: {{attr.responsible_emission_person}}.</p>
TPL;
    }

    private function bodyInventar(): string
    {
        return <<<'TPL'
<h2>DECIZIE Nr. {{decision.number}} / {{decision.date}}</h2>
<p><strong>Art. 1.</strong> Se constituie comisia de inventariere a patrimoniului formată din: {{attr.commission_president}} și {{attr.commission_member}}.</p>
<p><strong>Art. 2.</strong> Inventarierea se desfășoară în perioada {{attr.inventory_start_date}} - {{attr.inventory_end_date}}.</p>
<p><strong>Art. 3.</strong> Rezultatele vor fi consemnate în procesul-verbal de inventariere și transmise contabilității.</p>
TPL;
    }

    private function bodyDecontare(): string
    {
        return <<<'TPL'
<h2>DECIZIE Nr. {{decision.number}} / {{decision.date}}</h2>
<p><strong>Art. 1.</strong> Se aprobă plafonul maxim de avans spre decontare în valoare de {{attr.daily_advance_limit_ron}} RON/zi.</p>
<p><strong>Art. 2.</strong> Persoanele autorizate să ridice avansuri: {{attr.authorized_persons}}.</p>
<p><strong>Art. 3.</strong> Justificarea avansului (prin facturi/bonuri) se face în termen de maxim {{attr.justification_term_working_days}} zile lucrătoare de la primirea banilor.</p>
TPL;
    }

    private function bodyNormaCombustibil(): string
    {
        return <<<'TPL'
<h2>DECIZIE Nr. {{decision.number}} / {{decision.date}}</h2>
<p><strong>Art. 1.</strong> Pentru autoturismul marca {{attr.vehicle_make}}, nr. înmatriculare {{attr.vehicle_registration_number}}, se stabilește o normă de consum de {{attr.consumption_l_per_100km}} litri/100 km.</p>
<p><strong>Art. 2.</strong> Norma de consum se bazează pe {{attr.norm_basis}}.</p>
<p><strong>Art. 3.</strong> Alimentarea se va justifica prin bonuri fiscale pe care se va menționa obligatoriu CUI-ul firmei și foi de parcurs.</p>
TPL;
    }

    private function bodyCasare(): string
    {
        return <<<'TPL'
<h2>DECIZIE Nr. {{decision.number}} / {{decision.date}}</h2>
<p><strong>Art. 1.</strong> Se numește comisia de casare formată din: {{attr.disposal_committee_members}}.</p>
<p><strong>Art. 2.</strong> Se aprobă scoaterea din uz a următoarelor mijloace fixe/obiecte de inventar: {{attr.assets}}.</p>
<p><strong>Art. 3.</strong> Obiectele vor fi predate către un centru de reciclare autorizat: {{attr.recycling_center_name}}.</p>
TPL;
    }
}
