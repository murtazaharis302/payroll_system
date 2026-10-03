<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolesTableSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['id' => 1, 'name' => 'SuperAdmin'],
            ['id' => 2, 'name' => 'CompanyAdmin'],
            ['id' => 3, 'name' => 'HR'],
            ['id' => 4, 'name' => 'Employee'],
        ];

        foreach ($roles as $role) {
            DB::table('roles')->updateOrInsert(['id' => $role['id']], $role);
        }
    }
}
