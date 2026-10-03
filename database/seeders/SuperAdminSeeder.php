<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    /**
     * Create or update the local bootstrap super-admin account.
     *
     * Set the SUPER_ADMIN_* values in .env before running this seeder.
     */
    public function run(): void
    {
        Role::query()->updateOrCreate(
            ['id' => Role::SUPER_ADMIN],
            ['name' => 'SuperAdmin']
        );

        $email = env('SUPER_ADMIN_EMAIL', 'admin@payroll.local');

        User::withoutGlobalScopes()->updateOrCreate(
            ['email' => $email],
            [
                'name' => env('SUPER_ADMIN_NAME', 'Super Admin'),
                'password' => Hash::make(env('SUPER_ADMIN_PASSWORD', 'ChangeMe123!')),
                'role_id' => Role::SUPER_ADMIN,
                'company_id' => null,
                'emp_id' => null,
                'status' => 1,
            ]
        );
    }
}
