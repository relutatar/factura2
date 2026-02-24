<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Company;
use Illuminate\Database\Seeder;

class ClientSeeder extends Seeder
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

        Client::updateOrCreate(
            [
                'company_id' => $company->id,
                'cif' => '36408451',
            ],
            [
                'type' => 'persoana_juridica',
                'name' => 'PAINTBALL MURES SRL',
                'cnp' => null,
                'reg_com' => 'J26/1106/2016',
                'address' => 'JUD. MUREŞ, SAT IVĂNEŞTI COM. LIVEZENI, IVĂNEŞTI, NR.75',
                'city' => 'Sat Ivăneşti Com. Livezeni',
                'county' => 'MUREŞ',
                'phone' => '0741424169',
                'email' => null,
                'notes' => null,
            ]
        );

        Client::updateOrCreate(
            [
                'company_id' => $company->id,
                'cif' => 'RO10966500',
            ],
            [
                'type' => 'persoana_juridica',
                'name' => 'REEA SRL',
                'cnp' => null,
                'reg_com' => 'J1998000628265',
                'address' => 'JUD. MUREŞ, MUN. TÂRGU MUREŞ, PŢA. REPUBLICII, NR.41',
                'city' => 'Mun. Târgu Mureş',
                'county' => 'MUREŞ',
                'phone' => '264856',
                'email' => null,
                'notes' => null,
            ]
        );
    }
}
