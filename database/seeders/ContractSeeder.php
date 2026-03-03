<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Company;
use App\Models\Contract;
use App\Models\ContractTemplate;
use Illuminate\Database\Seeder;

class ContractSeeder extends Seeder
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

        $template = ContractTemplate::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('name', 'Contract cadru DDD')
            ->first();

        $clientReea = Client::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('cif', 'RO10966500')
            ->first();

        $clientPaintball = Client::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('cif', '36408451')
            ->first();

        if (! $template || ! $clientReea || ! $clientPaintball) {
            return;
        }

        Contract::updateOrCreate(
            [
                'company_id' => $company->id,
                'number' => '1',
            ],
            [
                'client_id' => $clientReea->id,
                'contract_template_id' => $template->id,
                'signed_date' => '2026-02-24',
                'start_date' => '2026-02-24',
                'end_date' => null,
                'value' => 4000.00,
                'currency' => 'RON',
                'status' => 'activ',
                'billing_cycle' => 'trimestrial',
                'additional_attributes' => [
                    'frequency'     => 'Trimestrial',
                    'locations'     => null,
                    'service_scope' => null,
                ],
                'notes' => null,
            ]
        );

        Contract::updateOrCreate(
            [
                'company_id' => $company->id,
                'number' => '2',
            ],
            [
                'client_id' => $clientPaintball->id,
                'contract_template_id' => $template->id,
                'signed_date' => '2026-02-24',
                'start_date' => '2026-02-24',
                'end_date' => '2036-02-24',
                'value' => 400.00,
                'currency' => 'RON',
                'status' => 'activ',
                'billing_cycle' => 'anual',
                'additional_attributes' => [
                    'frequency'     => 'Anual',
                    'locations'     => null,
                    'service_scope' => null,
                ],
                'notes' => null,
            ]
        );
    }
}
