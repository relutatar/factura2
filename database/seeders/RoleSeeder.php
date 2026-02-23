<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure the superadmin role exists
        $role = Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);

        // Assign superadmin role to the primary admin user (idempotent)
        $admin = User::where('email', 'admin@factura2.ro')->first();

        if ($admin) {
            if (! $admin->hasRole('superadmin')) {
                $admin->assignRole($role);
            }

            // Superadmin gets access to all companies (idempotent sync)
            $allCompanyIds = \App\Models\Company::withoutGlobalScopes()->pluck('id')->all();
            $admin->companies()->syncWithoutDetaching($allCompanyIds);
        }
    }
}
