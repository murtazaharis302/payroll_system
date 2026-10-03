<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\Company;
use App\Models\Department;
use App\Models\EmployeeMaster;
use App\Models\PayrollPeriod;
use App\Models\PayrollTransaction;
use App\Models\ValeLoan;
use App\Models\ValeLoanPayment;
use App\Models\GoodsPurchase;
use App\Models\GoodsPurchasePayment;
use App\Models\GovernmentLoan;
use App\Models\GovernmentLoanPayment;

class ReportsDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('');
        $this->command->info('🌱 ============================================');
        $this->command->info('🌱 REPORTS DATA SEEDER — Starting...');
        $this->command->info('🌱 For Reports: #5 Income History, #6 Deduction History, #7 13th Month');
        $this->command->info('🌱 ============================================');

        // ── Find active company ──────────────────────────────────
        $company = Company::where('status', 1)->first();
        if (!$company) {
            $this->command->error('❌ No active company found! Create a company first via /company or admin panel.');
            return;
        }
        $companyId = $company->id;
        $this->command->info("✅ Company: {$company->company_name} (ID: {$companyId})");

        // ── STEP 1: Departments ──────────────────────────────────
        $this->command->info('');
        $this->command->info('📁 Step 1: Creating Departments...');

        $deptData = [
            ['code' => 'HR001',  'name' => 'Human Resources'],
            ['code' => 'ACC001', 'name' => 'Accounting & Finance'],
            ['code' => 'OPS001', 'name' => 'Operations'],
            ['code' => 'SAL001', 'name' => 'Sales & Marketing'],
            ['code' => 'IT001',  'name' => 'Information Technology'],
        ];

        $departments = [];
        foreach ($deptData as $d) {
            $dept = Department::firstOrCreate(
                ['company_id' => $companyId, 'code' => $d['code']],
                ['name' => $d['name'], 'is_active' => true]
            );
            $departments[] = $dept;
        }
        $this->command->info('   ✅ ' . count($departments) . ' departments ready');

        // ── STEP 2: Employees ────────────────────────────────────
        $this->command->info('');
        $this->command->info('👥 Step 2: Creating Employees...');

        $empData = [
            // first, middle, last, monthly_rate, dept_index, position
            ['Juan',     'Dela',    'Santos',    28000, 0, 'HR Officer'],
            ['Maria',    'Grace',   'Garcia',    35000, 1, 'Accountant'],
            ['Pedro',    'Jose',    'Lopez',     22000, 2, 'Operations Staff'],
            ['Ana',      'Rose',    'Cruz',      30000, 3, 'Sales Executive'],
            ['Carlos',   'Manuel',  'Ramos',     42000, 4, 'IT Developer'],
            ['Sofia',    'Anne',    'Mendoza',   26000, 0, 'HR Assistant'],
            ['Miguel',   'Luis',    'Fernandez', 38000, 1, 'Senior Accountant'],
            ['Isabella', 'Marie',   'Morales',   24000, 2, 'Operations Staff'],
            ['Luis',     'Antonio', 'Castro',    32000, 3, 'Sales Manager'],
            ['Carmen',   'Joyce',   'Hernandez', 29000, 4, 'IT Support'],
            ['Roberto',  'Paul',    'Reyes',     45000, 1, 'Finance Manager'],
            ['Elena',    'Faith',   'Torres',    27000, 2, 'Supervisor'],
        ];

        $employees = [];
        foreach ($empData as $idx => $e) {
            $code = 'EMP-' . str_pad($idx + 1, 4, '0', STR_PAD_LEFT);

            // Check if employee already exists
            $existing = EmployeeMaster::where('company_id', $companyId)
                ->where('employee_code', $code)->first();

            if ($existing) {
                $employees[] = $existing;
                continue;
            }

            $monthly = $e[3];
            $daily   = round($monthly / 22, 2);
            $hourly  = round($daily / 8, 2);

            $emp = EmployeeMaster::create([
                'company_id'         => $companyId,
                'dept_id'            => $departments[$e[4]]->id,
                'employee_code'      => $code,
                'first_name'         => $e[0],
                'middle_name'        => $e[1],
                'last_name'          => $e[2],
                'position'           => $e[5],
                'department'         => $departments[$e[4]]->name,
                'monthly_rate'       => $monthly,
                'daily_rate'         => $daily,
                'hourly_rate'        => $hourly,
                'status'             => 'active',
                'date_hired'         => Carbon::now()->subMonths(rand(8, 30)),
                'date_of_birth'      => Carbon::now()->subYears(rand(22, 45)),
                'sss_number'         => '03-' . rand(1000000, 9999999) . '-' . rand(1, 9),
                'philhealth_number'  => (string) rand(10000000000, 99999999999),
                'pagibig_number'     => (string) rand(1000000000, 9999999999),
                'tin_number'         => rand(100, 999) . '-' . rand(100, 999) . '-' . rand(100, 999),
                'acct_num'           => '0' . rand(100000000, 999999999),
                'atm_num'            => str_pad(rand(1000000000000000, 9999999999999999), 16, '0'),
                'tax_code'           => ['ME', 'S', 'HF', 'S1'][rand(0, 3)],
                'email'              => strtolower($e[0]) . '.' . strtolower($e[2]) . rand(1, 99) . '@company.com',
                'phone'              => '09' . rand(100000000, 999999999),
            ]);

            $employees[] = $emp;
        }
        $this->command->info('   ✅ ' . count($employees) . ' employees ready');

        // ── STEP 3: Payroll Periods (12 months = 24 bi-monthly) ─
        $this->command->info('');
        $this->command->info('📅 Step 3: Creating Payroll Periods (12 months)...');

        $periods = [];
        // Start from 12 months ago — good for 13th month computation
        $startMonth = Carbon::now()->startOfMonth()->subMonths(11);

        for ($m = 0; $m < 12; $m++) {
            $monthDate  = $startMonth->copy()->addMonths($m);
            $monthName  = $monthDate->format('F Y');

            // 1st cutoff: 1–15
            $p1Start = $monthDate->copy()->startOfMonth();
            $p1End   = $monthDate->copy()->day(15);
            $p1Pay   = $monthDate->copy()->day(16);

            // 2nd cutoff: 16–end
            $p2Start = $monthDate->copy()->day(16);
            $p2End   = $monthDate->copy()->endOfMonth();
            $p2Pay   = $monthDate->copy()->addMonth()->day(1);

            $isCompleted = $m < 11; // Last month = open

            // Check existing
            $existing1 = PayrollPeriod::where('company_id', $companyId)
                ->where('period_start', $p1Start->toDateString())
                ->where('period_end',   $p1End->toDateString())->first();

            if (!$existing1) {
                $existing1 = PayrollPeriod::create([
                    'company_id'  => $companyId,
                    'period_name' => $monthName . ' — 1st Cutoff',
                    'period_start'=> $p1Start->toDateString(),
                    'period_end'  => $p1End->toDateString(),
                    'pay_date'    => $p1Pay->toDateString(),
                    'status'      => $isCompleted ? 'completed' : 'open',
                ]);
            }
            $periods[] = $existing1;

            $existing2 = PayrollPeriod::where('company_id', $companyId)
                ->where('period_start', $p2Start->toDateString())
                ->where('period_end',   $p2End->toDateString())->first();

            if (!$existing2) {
                $existing2 = PayrollPeriod::create([
                    'company_id'  => $companyId,
                    'period_name' => $monthName . ' — 2nd Cutoff',
                    'period_start'=> $p2Start->toDateString(),
                    'period_end'  => $p2End->toDateString(),
                    'pay_date'    => $p2Pay->toDateString(),
                    'status'      => $isCompleted ? 'completed' : 'open',
                ]);
            }
            $periods[] = $existing2;
        }
        $this->command->info('   ✅ ' . count($periods) . ' payroll periods ready');

        // ── STEP 4: Payroll Transactions ─────────────────────────
        $this->command->info('');
        $this->command->info('💰 Step 4: Creating Payroll Transactions...');
        $txCount = 0;

        foreach ($periods as $period) {
            if ($period->status !== 'completed') continue;

            foreach ($employees as $employee) {
                // Skip if transaction already exists
                $exists = PayrollTransaction::where('payroll_period_id', $period->id)
                    ->where('employee_id', $employee->id)->exists();
                if ($exists) continue;

                $monthly   = (float) $employee->monthly_rate;
                $daysWork  = rand(11, 15);
                $otHours   = rand(0, 12);
                $hourlyRate = (float) $employee->hourly_rate;

                $basicPay       = round($monthly / 2, 2); // Bi-monthly
                $overtimePay    = round($otHours * $hourlyRate * 1.25, 2);
                $allowances     = rand(500, 2000);
                $bonuses        = (rand(0, 4) === 0) ? rand(1000, 5000) : 0;
                $otherIncome    = (rand(0, 5) === 0) ? rand(500, 2000) : 0;
                $grossIncome    = $basicPay + $overtimePay + $allowances + $bonuses + $otherIncome;

                // Government contributions
                $sss        = $this->computeSSS($monthly);
                $philhealth = $this->computePhilHealth($monthly);
                $pagibig    = $this->computePagibig($monthly);
                $tax        = $this->computeTax($grossIncome * 2); // annualize for bi-monthly

                // Loans & deductions
                $sssLoan    = (rand(0, 4) === 0) ? rand(500, 2000)  : 0;
                $pagibigLoan= (rand(0, 4) === 0) ? rand(500, 1500)  : 0;
                $valeDed    = (rand(0, 4) === 0) ? rand(300, 1500)  : 0;
                $goodsDed   = (rand(0, 5) === 0) ? rand(200, 1000)  : 0;
                $otherDed   = (rand(0, 6) === 0) ? rand(100, 500)   : 0;

                $totalDed = $sss + $philhealth + $pagibig + $tax
                          + $sssLoan + $pagibigLoan + $valeDed + $goodsDed + $otherDed;
                $netPay = round($grossIncome - $totalDed, 2);

                PayrollTransaction::create([
                    'payroll_period_id'       => $period->id,
                    'employee_id'             => $employee->id,
                    'company_id'              => $companyId,
                    'total_days_worked'       => $daysWork,
                    'total_hours_worked'      => $daysWork * 8,
                    'overtime_hours'          => $otHours,
                    'absences'                => 15 - $daysWork,
                    'lates'                   => rand(0, 3),
                    'basic_pay'               => $basicPay,
                    'overtime_pay'            => $overtimePay,
                    'allowances'              => $allowances,
                    'bonuses'                 => $bonuses,
                    'other_income'            => $otherIncome,
                    'gross_income'            => round($grossIncome, 2),
                    'sss_contribution'        => $sss,
                    'philhealth_contribution' => $philhealth,
                    'pagibig_contribution'    => $pagibig,
                    'withholding_tax'         => $tax,
                    'sss_loan'                => $sssLoan,
                    'pagibig_loan'            => $pagibigLoan,
                    'vale_deduction'          => $valeDed,
                    'goods_deduction'         => $goodsDed,
                    'other_deductions'        => $otherDed,
                    'total_deductions'        => round($totalDed, 2),
                    'net_pay'                 => $netPay,
                    'status'                  => 'approved',
                    'approved_at'             => $period->pay_date,
                ]);
                $txCount++;
            }
        }
        $this->command->info("   ✅ {$txCount} payroll transactions created");

        // ── STEP 5: Vale Loans ────────────────────────────────────
        $this->command->info('');
        $this->command->info('💵 Step 5: Creating Vale Loans...');
        $valeCount = 0;

        $valeType = \App\Models\DeductionType::firstOrCreate(
            ['code' => 'VALE'],
            ['name' => 'Vale', 'description' => 'Cash advance deduction', 'type' => 'loan', 'is_active' => true]
        );

        foreach ($employees as $emp) {
            // Give each employee at least 1 vale loan for report testing
            $existing = ValeLoan::where('company_id', $companyId)
                ->where('employee_id', $emp->id)->count();
            if ($existing > 0) continue;

            $principal   = rand(3000, 15000);
            $installment = rand(500, 2000);
            $paid        = round($principal * rand(20, 70) / 100);
            $balance     = $principal - $paid;

            $loan = ValeLoan::create([
                'company_id'           => $companyId,
                'employee_id'          => $emp->id,
                'deduction_type_id'    => $valeType->id,
                'loan_number'          => 'VL-' . strtoupper(uniqid()),
                'loan_date'            => Carbon::now()->subMonths(rand(2, 8))->toDateString(),
                'principal_amount'     => $principal,
                'installment_amount'   => $installment,
                'installment_count'    => (int) ceil($principal / $installment),
                'total_paid'           => $paid,
                'balance'              => $balance,
                'start_deduction_date' => Carbon::now()->subMonths(rand(1, 6))->toDateString(),
                'status'               => $balance <= 0 ? 'completed' : 'active',
                'notes'                => 'Vale/cash advance — seeded data',
            ]);

            if ($paid > 0) {
                $numPay = (int) floor($paid / $installment);
                $currBal = $principal;
                for ($p = 0; $p < $numPay && $p < 5; $p++) {
                    $currBal -= $installment;
                    ValeLoanPayment::create([
                        'vale_loan_id' => $loan->id,
                        'payment_date' => Carbon::now()->subMonths($numPay - $p)->toDateString(),
                        'amount_paid'  => $installment,
                        'balance_after'=> max(0, $currBal),
                        'notes'        => 'Payment #' . ($p + 1),
                    ]);
                }
            }
            $valeCount++;
        }
        $this->command->info("   ✅ {$valeCount} vale loans created");

        // ── STEP 6: Goods Purchases ───────────────────────────────
        $this->command->info('');
        $this->command->info('🛒 Step 6: Creating Goods Purchases...');
        $goodsCount = 0;

        $goodsType = \App\Models\DeductionType::firstOrCreate(
            ['code' => 'GOODS'],
            ['name' => 'Goods Purchase', 'description' => 'Employee goods purchase deduction', 'type' => 'other', 'is_active' => true]
        );

        $items = ['Laptop (HP)', 'Mobile Phone (Samsung)', 'Electric Fan', 'Rice Cooker',
                  'Refrigerator', 'Electric Kettle', 'Desk Chair', 'Blender'];

        foreach ($employees as $emp) {
            $existing = GoodsPurchase::where('company_id', $companyId)
                ->where('employee_id', $emp->id)->count();
            if ($existing > 0) continue;

            if (rand(0, 2) > 0) { // 67% chance
                $total       = rand(2000, 12000);
                $installment = rand(500, 1500);
                $paid        = round($total * rand(10, 60) / 100);
                $balance     = $total - $paid;

                $goods = GoodsPurchase::create([
                    'company_id'           => $companyId,
                    'employee_id'          => $emp->id,
                    'deduction_type_id'    => $goodsType->id,
                    'purchase_number'      => 'GP-' . strtoupper(uniqid()),
                    'purchase_date'        => Carbon::now()->subMonths(rand(1, 6))->toDateString(),
                    'item_description'     => $items[array_rand($items)],
                    'total_amount'         => $total,
                    'installment_amount'   => $installment,
                    'installment_count'    => (int) ceil($total / $installment),
                    'total_paid'           => $paid,
                    'balance'              => $balance,
                    'start_deduction_date' => Carbon::now()->subMonths(rand(1, 5))->toDateString(),
                    'status'               => $balance <= 0 ? 'completed' : 'active',
                ]);

                if ($paid > 0) {
                    $numPay = (int) floor($paid / $installment);
                    $currBal = $total;
                    for ($p = 0; $p < $numPay && $p < 5; $p++) {
                        $currBal -= $installment;
                        GoodsPurchasePayment::create([
                            'goods_purchase_id' => $goods->id,
                            'payment_date'      => Carbon::now()->subMonths($numPay - $p)->toDateString(),
                            'amount_paid'       => $installment,
                            'balance_after'     => max(0, $currBal),
                            'notes'             => 'Payment #' . ($p + 1),
                        ]);
                    }
                }
                $goodsCount++;
            }
        }
        $this->command->info("   ✅ {$goodsCount} goods purchases created");

        // ── STEP 7: Government Loans ──────────────────────────────
        $this->command->info('');
        $this->command->info('🏛️  Step 7: Creating Government Loans...');
        $govCount = 0;

        foreach ($employees as $emp) {
            $existing = GovernmentLoan::where('company_id', $companyId)
                ->where('employee_id', $emp->id)->count();
            if ($existing > 0) continue;

            // SSS Loan
            if (rand(0, 2) > 0) {
                $principal  = rand(15000, 48000);
                $monthly    = rand(1000, 3000);
                $paid       = round($principal * rand(20, 60) / 100);
                $balance    = $principal - $paid;
                $term       = (int) ceil($principal / $monthly);

                $sssLoan = GovernmentLoan::create([
                    'company_id'        => $companyId,
                    'employee_id'       => $emp->id,
                    'loan_type'         => 'sss',
                    'loan_number'       => 'SSS-' . rand(10000000, 99999999),
                    'principal_amount'  => $principal,
                    'monthly_deduction' => $monthly,
                    'balance'           => $balance,
                    'start_date'        => Carbon::now()->subMonths(rand(3, 12))->toDateString(),
                    'end_date'          => Carbon::now()->addMonths($term - rand(1, 3))->toDateString(),
                    'term_months'       => $term,
                    'status'            => $balance <= 0 ? 'completed' : 'active',
                    'notes'             => 'SSS Calamity/Salary Loan',
                ]);

                $numPay = (int) floor($paid / $monthly);
                $currBal = $principal;
                for ($p = 0; $p < $numPay && $p < 8; $p++) {
                    $currBal -= $monthly;
                    GovernmentLoanPayment::create([
                        'government_loan_id' => $sssLoan->id,
                        'payment_date'       => Carbon::now()->subMonths($numPay - $p)->toDateString(),
                        'amount_paid'        => $monthly,
                        'balance_after'      => max(0, $currBal),
                        'notes'              => 'Monthly SSS loan deduction',
                    ]);
                }
                $govCount++;
            }

            // Pag-IBIG Loan
            if (rand(0, 3) === 0) {
                $principal  = rand(10000, 35000);
                $monthly    = rand(800, 2000);
                $paid       = round($principal * rand(10, 50) / 100);
                $balance    = $principal - $paid;
                $term       = (int) ceil($principal / $monthly);

                $pagibigLoan = GovernmentLoan::create([
                    'company_id'        => $companyId,
                    'employee_id'       => $emp->id,
                    'loan_type'         => 'pagibig',
                    'loan_number'       => 'PAG-' . rand(10000000, 99999999),
                    'principal_amount'  => $principal,
                    'monthly_deduction' => $monthly,
                    'balance'           => $balance,
                    'start_date'        => Carbon::now()->subMonths(rand(2, 10))->toDateString(),
                    'end_date'          => Carbon::now()->addMonths($term - rand(1, 2))->toDateString(),
                    'term_months'       => $term,
                    'status'            => $balance <= 0 ? 'completed' : 'active',
                    'notes'             => 'Pag-IBIG Multi-Purpose Loan',
                ]);

                $numPay = (int) floor($paid / $monthly);
                $currBal = $principal;
                for ($p = 0; $p < $numPay && $p < 5; $p++) {
                    $currBal -= $monthly;
                    GovernmentLoanPayment::create([
                        'government_loan_id' => $pagibigLoan->id,
                        'payment_date'       => Carbon::now()->subMonths($numPay - $p)->toDateString(),
                        'amount_paid'        => $monthly,
                        'balance_after'      => max(0, $currBal),
                        'notes'              => 'Monthly Pag-IBIG loan deduction',
                    ]);
                }
                $govCount++;
            }
        }
        $this->command->info("   ✅ {$govCount} government loans created");

        // ── FINAL SUMMARY ─────────────────────────────────────────
        $this->command->info('');
        $this->command->info('🎉 ============================================');
        $this->command->info('🎉 SEEDING COMPLETE!');
        $this->command->info('🎉 ============================================');
        $this->command->info('');

        $totalTx      = PayrollTransaction::where('company_id', $companyId)->count();
        $totalPeriods = PayrollPeriod::where('company_id', $companyId)->count();
        $totalEmp     = EmployeeMaster::where('company_id', $companyId)->where('status','active')->count();

        $this->command->info("📊 Final DB Counts for company [{$company->company_name}]:");
        $this->command->info("   Departments     : " . Department::where('company_id', $companyId)->count());
        $this->command->info("   Employees       : {$totalEmp}");
        $this->command->info("   Payroll Periods : {$totalPeriods}");
        $this->command->info("   Transactions    : {$totalTx}");
        $this->command->info("   Vale Loans      : " . ValeLoan::where('company_id', $companyId)->count());
        $this->command->info("   Goods Purchases : " . GoodsPurchase::where('company_id', $companyId)->count());
        $this->command->info("   Gov. Loans      : " . GovernmentLoan::where('company_id', $companyId)->count());
        $this->command->info('');
        $this->command->info('✅ You can now test:');
        $this->command->info('   → Report #5: Income Payroll History  (select any 2-6 periods)');
        $this->command->info('   → Report #6: Deduction Payroll History (select any 2-6 periods)');
        $this->command->info('   → Report #7: 13th Month Computation  (select full year range)');
        $this->command->info('');
        $this->command->info("   🌐 Go to: http://localhost:8000/admin/reports");
        $this->command->info('');
    }

    // ── Helper: SSS Contribution (Employee Share) ────────────────
    private function computeSSS(float $monthlySalary): float
    {
        // 2024 SSS table (simplified employee share)
        $table = [
            [4250,  135.00],
            [4750,  157.50],
            [5250,  180.00],
            [5750,  202.50],
            [6250,  225.00],
            [6750,  247.50],
            [7250,  270.00],
            [7750,  292.50],
            [8250,  315.00],
            [8750,  337.50],
            [9250,  360.00],
            [9750,  382.50],
            [10250, 405.00],
            [10750, 427.50],
            [11250, 450.00],
            [11750, 472.50],
            [12250, 495.00],
            [12750, 517.50],
            [13250, 540.00],
            [13750, 562.50],
            [14250, 585.00],
            [14750, 607.50],
            [15250, 630.00],
            [15750, 652.50],
            [16250, 675.00],
            [16750, 697.50],
            [17250, 720.00],
            [17750, 742.50],
            [18250, 765.00],
            [18750, 787.50],
            [19250, 810.00],
            [19750, 832.50],
            [20250, 855.00],
        ];

        foreach ($table as [$ceiling, $contribution]) {
            if ($monthlySalary <= $ceiling) return $contribution;
        }
        return 900.00; // Maximum employee share
    }

    // ── Helper: PhilHealth (Employee Share = 2.5%) ───────────────
    private function computePhilHealth(float $monthlySalary): float
    {
        $premium = $monthlySalary * 0.05; // 5% total
        $share   = $premium / 2;          // Employee = half
        return round(max(250, min($share, 2500)), 2); // Min 250, Max 2500
    }

    // ── Helper: Pag-IBIG ─────────────────────────────────────────
    private function computePagibig(float $monthlySalary): float
    {
        if ($monthlySalary <= 1500) {
            return round($monthlySalary * 0.01, 2);
        }
        return min(round($monthlySalary * 0.02, 2), 100.00); // Max 100
    }

    // ── Helper: Withholding Tax (Bi-monthly simplified) ──────────
    private function computeTax(float $annualizedIncome): float
    {
        // Annual brackets, return half (bi-monthly share)
        $annual = $annualizedIncome;
        if ($annual <= 250000) $tax = 0;
        elseif ($annual <= 400000) $tax = ($annual - 250000) * 0.15;
        elseif ($annual <= 800000) $tax = 22500 + ($annual - 400000) * 0.20;
        elseif ($annual <= 2000000) $tax = 102500 + ($annual - 800000) * 0.25;
        elseif ($annual <= 8000000) $tax = 402500 + ($annual - 2000000) * 0.30;
        else $tax = 2202500 + ($annual - 8000000) * 0.35;

        return round($tax / 24, 2); // Bi-monthly (24 periods/year)
    }
}
