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
        // ── 1. Seed company types ────────────────────────────────────────────
        $typeDdd = \App\Models\CompanyType::updateOrCreate(
            ['slug' => 'ddd'],
            [
                'name'        => 'DDD (Pest Control)',
                'description' => 'Servicii de dezinfecție, dezinsecție și deratizare.',
                'color'       => 'success',
                'is_active'   => true,
                'sort_order'  => 1,
            ]
        );

        $typePaintball = \App\Models\CompanyType::updateOrCreate(
            ['slug' => 'paintball'],
            [
                'name'        => 'Paintball & Leisure',
                'description' => 'Evenimente paintball și activități recreative.',
                'color'       => 'warning',
                'is_active'   => true,
                'sort_order'  => 2,
            ]
        );

        // ── 2. Seed companies ────────────────────────────────────────────────
        \App\Models\Company::updateOrCreate(
            ['cif' => '27864858'],
            [
                'name'            => 'NOD CONSULTING SRL',
                'company_type_id' => $typeDdd->id,
                'reg_com'         => 'J2010000868267',
                'address'         => 'Str. Vișeului 6, Ap. 1',
                'city'            => 'Târgu Mureș',
                'county'          => 'Mureș',
                'invoice_prefix'  => 'NOD',
            ]
        );

        \App\Models\Company::updateOrCreate(
            ['cif' => '36408451'],
            [
                'name'            => 'PAINTBALL MUREȘ SRL',
                'company_type_id' => $typePaintball->id,
                'reg_com'         => 'J26/1106/2016',
                'address'         => 'Sat Ivănești 75',
                'city'            => 'Ivănești',
                'county'          => 'Mureș',
                'invoice_prefix'  => 'PBM',
            ]
        );
    }
}
