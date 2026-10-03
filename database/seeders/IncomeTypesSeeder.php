<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class IncomeTypesSeeder extends Seeder
{
    public function run(): void
    {
        $incomeTypes = [
            [
                'id' => 1,
                'name' => 'Basic Pay',
                'code' => 'BASIC',
                'system_key' => 'basic',
                'description' => 'Base monthly salary',
                'company_id' => null,
                'is_taxable' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'name' => 'Overtime Pay',
                'code' => 'OVERTIME',
                'system_key' => 'overtime',
                'description' => 'Payment for overtime hours worked',
                'company_id' => null,
                'is_taxable' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 3,
                'name' => 'Allowance',
                'code' => 'ALLOWANCE',
                'system_key' => null,
                'description' => 'Standard monthly allowances',
                'company_id' => null,
                'is_taxable' => false,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 4,
                'name' => 'Bonus',
                'code' => 'BONUS',
                'system_key' => null,
                'description' => 'Performance or performance-based bonus',
                'company_id' => null,
                'is_taxable' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 5,
                'name' => 'Other Income',
                'code' => 'OTHER',
                'system_key' => null,
                'description' => 'Miscellaneous income',
                'company_id' => null,
                'is_taxable' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($incomeTypes as $type) {
            DB::table('income_types')->updateOrInsert(['id' => $type['id']], $type);
        }
    }
}
