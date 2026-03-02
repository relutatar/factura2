<?php

namespace Database\Seeders;

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
            ['name' => 'DDD'],
            [
                'description' => 'Servicii de dezinfecție, dezinsecție și deratizare.',
                'color'       => 'success',
                'is_active'   => true,
                'sort_order'  => 1,
            ]
        );

        $typePaintball = \App\Models\CompanyType::updateOrCreate(
            ['name' => 'Paintball'],
            [
                'description' => 'Evenimente paintball și activități recreative.',
                'color'       => 'warning',
                'is_active'   => true,
                'sort_order'  => 2,
            ]
        );

        // ── 2. Seed companies ────────────────────────────────────────────────
        \App\Models\Company::updateOrCreate(
            ['cif' => 'RO27864858'],
            [
                'name'            => 'NOD CONSULTING SRL',
                'company_type_id' => $typeDdd->id,
                'administrator'   => 'Claudia Oprea',
                'reg_com'         => 'J2010000868267',
                'address'         => 'JUD. MUREŞ, MUN. TÂRGU MUREŞ, STR. VIŞEULUI, NR.6, AP.1',
                'city'            => 'Mun. Târgu Mureş',
                'county'          => 'MUREŞ',
                'modules'         => ['acte_aditionale', 'procese_verbale', 'stocuri', 'efactura'],
                'efactura_certificate_password' => 'password',
                'efactura_test_mode' => true,
                'efactura_cif' => 'admin@factura2.ro',
            ]
        );

        \App\Models\Company::updateOrCreate(
            ['cif' => '36408451'],
            [
                'name'            => 'PAINTBALL MURES SRL',
                'company_type_id' => $typePaintball->id,
                'administrator'   => 'Relu Tătar',
                'reg_com'         => 'J26/1106/2016',
                'address'         => 'JUD. MUREŞ, SAT IVĂNEŞTI COM. LIVEZENI, IVĂNEŞTI, NR.75',
                'city'            => 'Sat Ivăneşti Com. Livezeni',
                'county'          => 'MUREŞ',
                'modules'         => ['bonuri_fiscale', 'stocuri'],
                'efactura_certificate_password' => 'password',
                'efactura_test_mode' => true,
                'efactura_cif' => 'admin@factura2.ro',
            ]
        );
    }
}
