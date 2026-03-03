<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@factura2.ro'],
            [
                'name'     => 'Administrator',
                'password' => Hash::make('password'),
            ]
        );

        // One regular user per company
        $companyUsers = [
            'RO27864858' => ['name' => 'User NOD',      'email' => 'user@nod.ro'],
            '36408451' => ['name' => 'User Paintball','email' => 'user@paintball.ro'],
        ];

        $companies = Company::withoutGlobalScopes()->get();

        foreach ($companies as $company) {
            $userData = $companyUsers[$company->cif] ?? null;
            if (! $userData) {
                continue;
            }

            $user = User::firstOrCreate(
                ['email' => $userData['email']],
                [
                    'name'     => $userData['name'],
                    'password' => Hash::make('password'),
                ]
            );

            // Assign only their own company (idempotent).
            $user->companies()->syncWithoutDetaching([$company->id]);
        }
    }
}
