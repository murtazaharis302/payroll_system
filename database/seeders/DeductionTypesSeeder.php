<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DeductionTypesSeeder extends Seeder
{
    public function run(): void
    {
        $deductionTypes = [
            [
                'name' => 'SSS Contribution',
                'code' => 'SSS',
                'system_key' => 'sss',
                'description' => 'Social Security System monthly contribution',
                'type' => 'statutory',
                'company_id' => null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'PhilHealth Contribution',
                'code' => 'PHILHEALTH',
                'system_key' => 'philhealth',
                'description' => 'Philippine Health Insurance contribution',
                'type' => 'statutory',
                'company_id' => null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Pag-IBIG Contribution',
                'code' => 'PAGIBIG',
                'system_key' => 'pagibig',
                'description' => 'Home Development Mutual Fund contribution',
                'type' => 'statutory',
                'company_id' => null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Withholding Tax',
                'code' => 'TAX',
                'system_key' => 'tax',
                'description' => 'Income tax withholding',
                'type' => 'statutory',
                'company_id' => null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'SSS Loan',
                'code' => 'SSS_LOAN',
                'system_key' => null,
                'description' => 'SSS salary loan deduction',
                'type' => 'loan',
                'company_id' => null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Pag-IBIG Loan',
                'code' => 'PAGIBIG_LOAN',
                'system_key' => null,
                'description' => 'Pag-IBIG multi-purpose loan deduction',
                'type' => 'loan',
                'company_id' => null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Vale',
                'code' => 'VALE',
                'system_key' => null,
                'description' => 'Cash advance deduction',
                'type' => 'loan',
                'company_id' => null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Company Loan',
                'code' => 'COMPANY_LOAN',
                'system_key' => null,
                'description' => 'Company loan deduction',
                'type' => 'loan',
                'company_id' => null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Goods Purchase',
                'code' => 'GOODS',
                'system_key' => null,
                'description' => 'Employee goods purchase deduction',
                'type' => 'other',
                'company_id' => null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Tardiness',
                'code' => 'TARDINESS',
                'system_key' => 'tardiness',
                'description' => 'Deduction for late arrival',
                'type' => 'other',
                'company_id' => null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Absences',
                'code' => 'ABSENCES',
                'system_key' => null,
                'description' => 'Deduction for absences',
                'type' => 'other',
                'company_id' => null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Undertime',
                'code' => 'UNDERTIME',
                'system_key' => 'undertime',
                'description' => 'Deduction for undertime',
                'type' => 'other',
                'company_id' => null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Other Deduction',
                'code' => 'OTHER',
                'system_key' => null,
                'description' => 'Miscellaneous deduction',
                'type' => 'other',
                'company_id' => null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($deductionTypes as $type) {
            DB::table('deduction_types')->updateOrInsert(
                ['code' => $type['code'], 'company_id' => null],
                $type
            );
        }
    }
}
