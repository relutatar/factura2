<?php

namespace Database\Seeders;

use App\Models\VatRate;
use Illuminate\Database\Seeder;

class VatRateSeeder extends Seeder
{
    /**
     * Seed Romania's standard VAT rates (idempotent).
     */
    public function run(): void
    {
        $rates = [
            [
                'value'       => 21.00,
                'label'       => '21% – Standard',
                'description' => 'Cotă standard TVA',
                'is_default'  => true,
                'is_active'   => true,
                'sort_order'  => 1,
            ],
            [
                'value'       => 11.00,
                'label'       => '11% – Redusă',
                'description' => 'Cotă redusă (alimente, cazare, cărți)',
                'is_default'  => false,
                'is_active'   => true,
                'sort_order'  => 2,
            ],
            [
                'value'       => 0.00,
                'label'       => '0% – Scutit',
                'description' => 'Scutit de TVA / Export',
                'is_default'  => false,
                'is_active'   => true,
                'sort_order'  => 3,
            ],
        ];

        foreach ($rates as $rate) {
            VatRate::updateOrCreate(
                ['value' => $rate['value']],
                $rate
            );
        }
    }
}
