<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            CompanySeeder::class,
            ContractTemplateSeeder::class,
            ClientSeeder::class,
            ContractSeeder::class,
            InvoiceSeeder::class,
            DecisionTemplateSeeder::class,
            NumberingRangeSeeder::class,
            VatRateSeeder::class,
            UserSeeder::class,
            RoleSeeder::class,
        ]);
    }
}
