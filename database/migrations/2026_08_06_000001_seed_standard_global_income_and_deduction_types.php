<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('income_types')) {
            $standardIncomeTypes = [
                [
                    'code' => 'BASIC',
                    'system_key' => 'basic',
                    'name' => 'Basic Pay',
                    'description' => 'Base monthly salary',
                    'company_id' => null,
                    'is_taxable' => 1,
                    'is_active' => 1,
                ],
                [
                    'code' => 'OVERTIME',
                    'system_key' => 'overtime',
                    'name' => 'Overtime Pay',
                    'description' => 'Payment for overtime hours worked',
                    'company_id' => null,
                    'is_taxable' => 1,
                    'is_active' => 1,
                ],
                [
                    'code' => 'ALLOWANCE',
                    'system_key' => null,
                    'name' => 'Allowance',
                    'description' => 'Standard monthly allowances',
                    'company_id' => null,
                    'is_taxable' => 0,
                    'is_active' => 1,
                ],
                [
                    'code' => 'BONUS',
                    'system_key' => null,
                    'name' => 'Bonus',
                    'description' => 'Performance or performance-based bonus',
                    'company_id' => null,
                    'is_taxable' => 1,
                    'is_active' => 1,
                ],
                [
                    'code' => 'OTHER',
                    'system_key' => null,
                    'name' => 'Other Income',
                    'description' => 'Miscellaneous income',
                    'company_id' => null,
                    'is_taxable' => 1,
                    'is_active' => 1,
                ],
            ];

            $hasIncomeSysKey = Schema::hasColumn('income_types', 'system_key');
            foreach ($standardIncomeTypes as $type) {
                if (!$hasIncomeSysKey) {
                    unset($type['system_key']);
                }
                DB::table('income_types')->updateOrInsert(
                    ['code' => $type['code']],
                    array_merge($type, [
                        'updated_at' => now(),
                    ])
                );
            }
        }

        if (Schema::hasTable('deduction_types')) {
            $standardDeductionTypes = [
                [
                    'code' => 'SSS',
                    'system_key' => 'sss',
                    'name' => 'SSS Contribution',
                    'description' => 'Social Security System monthly contribution',
                    'type' => 'statutory',
                    'company_id' => null,
                    'is_active' => 1,
                ],
                [
                    'code' => 'PHILHEALTH',
                    'system_key' => 'philhealth',
                    'name' => 'PhilHealth Contribution',
                    'description' => 'Philippine Health Insurance contribution',
                    'type' => 'statutory',
                    'company_id' => null,
                    'is_active' => 1,
                ],
                [
                    'code' => 'PAGIBIG',
                    'system_key' => 'pagibig',
                    'name' => 'Pag-IBIG Contribution',
                    'description' => 'Home Development Mutual Fund contribution',
                    'type' => 'statutory',
                    'company_id' => null,
                    'is_active' => 1,
                ],
                [
                    'code' => 'TAX',
                    'system_key' => 'tax',
                    'name' => 'Withholding Tax',
                    'description' => 'Income tax withholding',
                    'type' => 'statutory',
                    'company_id' => null,
                    'is_active' => 1,
                ],
                [
                    'code' => 'SSS_LOAN',
                    'system_key' => null,
                    'name' => 'SSS Loan',
                    'description' => 'SSS salary loan deduction',
                    'type' => 'loan',
                    'company_id' => null,
                    'is_active' => 1,
                ],
                [
                    'code' => 'PAGIBIG_LOAN',
                    'system_key' => null,
                    'name' => 'Pag-IBIG Loan',
                    'description' => 'Pag-IBIG multi-purpose loan deduction',
                    'type' => 'loan',
                    'company_id' => null,
                    'is_active' => 1,
                ],
                [
                    'code' => 'VALE',
                    'system_key' => null,
                    'name' => 'Vale',
                    'description' => 'Cash advance deduction',
                    'type' => 'loan',
                    'company_id' => null,
                    'is_active' => 1,
                ],
                [
                    'code' => 'COMPANY_LOAN',
                    'system_key' => null,
                    'name' => 'Company Loan',
                    'description' => 'Company loan deduction',
                    'type' => 'loan',
                    'company_id' => null,
                    'is_active' => 1,
                ],
                [
                    'code' => 'GOODS',
                    'system_key' => null,
                    'name' => 'Goods Purchase',
                    'description' => 'Employee goods purchase deduction',
                    'type' => 'other',
                    'company_id' => null,
                    'is_active' => 1,
                ],
                [
                    'code' => 'TARDINESS',
                    'system_key' => 'tardiness',
                    'name' => 'Tardiness',
                    'description' => 'Deduction for late arrival',
                    'type' => 'other',
                    'company_id' => null,
                    'is_active' => 1,
                ],
                [
                    'code' => 'ABSENCES',
                    'system_key' => null,
                    'name' => 'Absences',
                    'description' => 'Deduction for absences',
                    'type' => 'other',
                    'company_id' => null,
                    'is_active' => 1,
                ],
                [
                    'code' => 'UNDERTIME',
                    'system_key' => 'undertime',
                    'name' => 'Undertime',
                    'description' => 'Deduction for undertime',
                    'type' => 'other',
                    'company_id' => null,
                    'is_active' => 1,
                ],
                [
                    'code' => 'OTHER',
                    'system_key' => null,
                    'name' => 'Other Deduction',
                    'description' => 'Miscellaneous deduction',
                    'type' => 'other',
                    'company_id' => null,
                    'is_active' => 1,
                ],
            ];

            $hasDeductionSysKey = Schema::hasColumn('deduction_types', 'system_key');
            foreach ($standardDeductionTypes as $type) {
                if (!$hasDeductionSysKey) {
                    unset($type['system_key']);
                }
                DB::table('deduction_types')->updateOrInsert(
                    ['code' => $type['code']],
                    array_merge($type, [
                        'updated_at' => now(),
                    ])
                );
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Data seeding migration: down operation intentionally no-op to preserve catalog data integrity
    }
};
