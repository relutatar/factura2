<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\Company::updateOrCreate(
            ['cif' => '27864858'],
            [
                'name'           => 'NOD CONSULTING SRL',
                'reg_com'        => 'J2010000868267',
                'address'        => 'Str. Vișeului 6, Ap. 1',
                'city'           => 'Târgu Mureș',
                'county'         => 'Mureș',
                'invoice_prefix' => 'NOD',
            ]
        );

        \App\Models\Company::updateOrCreate(
            ['cif' => '36408451'],
            [
                'name'           => 'PAINTBALL MUREȘ SRL',
                'reg_com'        => 'J26/1106/2016',
                'address'        => 'Sat Ivănești 75',
                'city'           => 'Ivănești',
                'county'         => 'Mureș',
                'invoice_prefix' => 'PBM',
            ]
        );
    }
}
