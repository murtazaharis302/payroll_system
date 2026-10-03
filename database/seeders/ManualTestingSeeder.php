<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Department;
use App\Models\SubDepartment;
use App\Models\EmployeeMaster;
use App\Models\Employee;
use App\Models\User;
use App\Models\Role;
use App\Models\IncomeType;
use App\Models\DeductionType;
use App\Models\LoanType;
use App\Models\EmpLoan;
use App\Models\EmpIncome;
use App\Models\EmpDeduction;
use App\Models\HRAdjustment;
use App\Models\AttendanceLog;
use App\Models\PayrollPeriod;
use App\Models\PayHeader;
use App\Models\PayIn;
use App\Models\PayOut;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class ManualTestingSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Disable FK constraints and clear tables to prevent duplicates
        \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();
        DB::table('companies')->delete();
        DB::table('users')->delete();
        DB::table('employees')->delete();
        DB::table('employees_master')->delete();
        DB::table('emp_income')->delete();
        DB::table('emp_deductions')->delete();
        DB::table('emp_loans')->delete();
        DB::table('hr_adjustments')->delete();
        DB::table('attendance_logs')->delete();
        DB::table('payroll_periods')->delete();
        DB::table('pay_headers')->delete();
        DB::table('pay_ins')->delete();
        DB::table('pay_outs')->delete();
        \Illuminate\Support\Facades\Schema::enableForeignKeyConstraints();

        // 2. Ensure system roles exist
        $roles = [
            ['id' => 1, 'name' => 'SuperAdmin'],
            ['id' => 2, 'name' => 'CompanyAdmin'],
            ['id' => 3, 'name' => 'HR'],
            ['id' => 4, 'name' => 'Employee'],
        ];
        foreach ($roles as $role) {
            DB::table('roles')->updateOrInsert(['id' => $role['id']], $role);
        }

        // 3. Create Super Admin User (Global, no company)
        $superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@example.com',
            'password' => Hash::make('password'),
            'role_id' => 1,
            'status' => 1,
            'company_id' => null,
        ]);

        // 4. Create Company Admin User
        $companyAdmin = User::create([
            'name' => 'Acme Company Admin',
            'email' => 'companyadmin@acme.com',
            'password' => Hash::make('password'),
            'role_id' => 2,
            'status' => 1,
        ]);

        // 5. Create Acme Company Tenant
        $company = Company::create([
            'user_id' => $companyAdmin->id,
            'company_code' => 'ACME01',
            'name' => 'Acme Payroll Inc.',
            'email' => 'companyadmin@acme.com',
            'phone' => 288881111,
            'website' => 'acmepayroll.com',
            'status' => 1,
            'detail' => 'Acme Primary Testing Tenant',
        ]);

        // Link company admin to company
        User::withoutGlobalScopes()->where('id', $companyAdmin->id)->update([
            'company_id' => $company->id,
        ]);

        // 6. Create HR Manager User
        $hrUser = User::create([
            'name' => 'Acme HR Manager',
            'email' => 'hrmanager@acme.com',
            'password' => Hash::make('password'),
            'role_id' => 3,
            'status' => 1,
            'company_id' => $company->id,
        ]);

        Employee::create([
            'owner_user_id' => $companyAdmin->id,
            'e_user_id' => $hrUser->id,
            'designation' => 'Hr',
            'id_number' => 'HR-001',
            'status' => 1,
        ]);



        // 8. Seed Departments & Sub-Departments
        $deptHR = Department::create([
            'company_id' => $company->id,
            'code' => 'HRD',
            'name' => 'Human Resources Department',
            'is_active' => true,
        ]);
        $subDeptHROP = SubDepartment::create([
            'company_id' => $company->id,
            'department_id' => $deptHR->id,
            'code' => 'HROP',
            'name' => 'HR Operations',
            'is_active' => true,
        ]);

        $deptENG = Department::create([
            'company_id' => $company->id,
            'code' => 'ENG',
            'name' => 'Engineering Department',
            'is_active' => true,
        ]);
        $subDeptDEV = SubDepartment::create([
            'company_id' => $company->id,
            'department_id' => $deptENG->id,
            'code' => 'SOFT',
            'name' => 'Software Engineering',
            'is_active' => true,
        ]);

        // 9. Seed 5 Employees in employees_master
        $employeesData = [
            [
                'employee_code' => 'EMP-ACME-01',
                'first_name' => 'Alice',
                'last_name' => 'Smith',
                'email' => 'employee@acme.com',
                'position' => 'Software Engineer I',
                'monthly_rate' => 50000.00,
                'daily_rate' => 2500.00,
                'hourly_rate' => 312.50,
                'acct_num' => '1234567890',
                'dept_id' => $deptENG->id,
                'sub_dept_id' => $subDeptDEV->id,
                'status' => 'active',
                'date_hired' => '2025-01-01',
                'biometric_id' => 'BIO-001',
            ],
            [
                'employee_code' => 'EMP-ACME-02',
                'first_name' => 'Bob',
                'last_name' => 'Jones',
                'email' => 'bob@acme.com',
                'position' => 'QA Specialist',
                'daily_rate' => 1800.00,
                'hourly_rate' => 225.00,
                'acct_num' => '0987654321',
                'dept_id' => $deptENG->id,
                'sub_dept_id' => $subDeptDEV->id,
                'status' => 'active',
                'date_hired' => '2025-06-01',
                'biometric_id' => 'BIO-002',
            ],
            [
                'employee_code' => 'EMP-ACME-03',
                'first_name' => 'Charlie',
                'last_name' => 'Brown',
                'email' => 'charlie@acme.com',
                'position' => 'HR Generalist',
                'monthly_rate' => 45000.00,
                'daily_rate' => 2250.00,
                'hourly_rate' => 281.25,
                'acct_num' => '1122334455',
                'dept_id' => $deptHR->id,
                'sub_dept_id' => $subDeptHROP->id,
                'status' => 'active',
                'date_hired' => '2024-03-15',
                'biometric_id' => 'BIO-003',
            ],
            [
                'employee_code' => 'EMP-ACME-04',
                'first_name' => 'Diana',
                'last_name' => 'Prince',
                'email' => 'diana@acme.com',
                'position' => 'Senior Developer',
                'daily_rate' => 2500.00,
                'hourly_rate' => 312.50,
                'acct_num' => '5566778899',
                'dept_id' => $deptENG->id,
                'sub_dept_id' => $subDeptDEV->id,
                'status' => 'active',
                'date_hired' => '2026-02-01',
                'biometric_id' => 'BIO-004',
            ],
            [
                'employee_code' => 'EMP-ACME-05',
                'first_name' => 'Ethan',
                'last_name' => 'Hunt',
                'email' => 'ethan@acme.com',
                'position' => 'Security Analyst',
                'monthly_rate' => 75000.00,
                'daily_rate' => 3750.00,
                'hourly_rate' => 468.75,
                'acct_num' => '6677889900',
                'dept_id' => $deptENG->id,
                'sub_dept_id' => $subDeptDEV->id,
                'status' => 'active',
                'date_hired' => '2023-11-01',
                'biometric_id' => 'BIO-005',
            ],
        ];

        $seededEmployees = [];
        foreach ($employeesData as $emp) {
            $emp['company_id'] = $company->id;
            $seededEmployees[] = EmployeeMaster::create($emp);
        }

        // 10. Create Standard Employee User linked to Alice (emp_id)
        $alice = $seededEmployees[0];
        $empUser = User::create([
            'name' => 'Acme Employee Alice',
            'email' => 'employee@acme.com',
            'password' => Hash::make('password'),
            'role_id' => 4,
            'status' => 1,
            'company_id' => $company->id,
            'emp_id' => $alice->id,
        ]);

        Employee::create([
            'owner_user_id' => $companyAdmin->id,
            'e_user_id' => $empUser->id,
            'designation' => 'Employee',
            'id_number' => 'EMP-001',
            'status' => 1,
        ]);

        // 11. Seed Recurring Incomes (`emp_income`)
        $allowanceType = IncomeType::where('code', 'ALLOWANCE')->first() ?: IncomeType::create(['code' => 'ALLOWANCE', 'name' => 'Allowance']);
        $bonusType = IncomeType::where('code', 'BONUS')->first() ?: IncomeType::create(['code' => 'BONUS', 'name' => 'Bonus']);

        EmpIncome::create([
            'employee_id' => $alice->id,
            'income_type_id' => $allowanceType->id,
            'rate' => 2000.00,
            'frequency' => 'monthly',
            'is_active' => true,
        ]);

        EmpIncome::create([
            'employee_id' => $seededEmployees[2]->id, // Charlie
            'income_type_id' => $allowanceType->id,
            'rate' => 1500.00,
            'frequency' => 'monthly',
            'is_active' => true,
        ]);

        // 12. Seed Recurring Deductions (`emp_deductions`)
        $otherDeductionType = DeductionType::where('code', 'OTHER')->first() ?: DeductionType::create(['code' => 'OTHER', 'name' => 'Other Deduction']);
        $sssLoanDeductionType = DeductionType::where('code', 'SSS_LOAN')->first() ?: DeductionType::create(['code' => 'SSS_LOAN', 'name' => 'SSS Loan']);

        EmpDeduction::create([
            'employee_id' => $alice->id,
            'deduction_type_id' => $otherDeductionType->id,
            'rate' => 500.00,
            'frequency' => 'monthly',
            'is_active' => true,
        ]);

        // 13. Seed Active Loans (`emp_loans`)
        $sssLoanType = LoanType::firstOrCreate(
            ['company_id' => $company->id, 'name' => 'SSS Loan']
        );

        EmpLoan::create([
            'employee_id' => $alice->id,
            'loan_type_id' => $sssLoanType->id,
            'principal_amount' => 10000.00,
            'current_balance' => 8000.00,
        ]);

        // Setup matching amortization deduction
        EmpDeduction::create([
            'employee_id' => $alice->id,
            'deduction_type_id' => $sssLoanDeductionType->id,
            'rate' => 1000.00,
            'frequency' => 'monthly',
            'is_active' => true,
        ]);

        // 14. Seed Payroll Periods
        // 14.1 June 16-30 2026 (Completed / Closed)
        $junePeriod = PayrollPeriod::create([
            'company_id' => $company->id,
            'period_name' => 'June 16-30 2026',
            'period_start' => '2026-06-16',
            'period_end' => '2026-06-30',
            'pay_date' => '2026-06-30',
            'status' => 'completed',
        ]);

        // 14.2 July 1-15 2026 (Open)
        $julyPeriod = PayrollPeriod::create([
            'company_id' => $company->id,
            'period_name' => 'July 1-15 2026',
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-15',
            'pay_date' => '2026-07-15',
            'status' => 'open',
        ]);

        // 15. Seed Approved June Payroll Headers and Transactions (For 13th month historical accumulation)
        $basicPayType = IncomeType::where('code', 'BASIC')->first() ?: IncomeType::create(['code' => 'BASIC', 'name' => 'Basic Pay']);

        foreach ($seededEmployees as $emp) {
            $basicAmount = ($emp->monthly_rate > 0) ? ($emp->monthly_rate / 2) : ($emp->daily_rate * 11);

            $payHeader = PayHeader::create([
                'payroll_period_id' => $junePeriod->id,
                'employee_id' => $emp->id,
                'total_gross_pay' => $basicAmount,
                'total_deductions' => 0.00,
                'net_pay' => $basicAmount,
                'status' => 'approved',
                'posted_at' => now(),
            ]);

            PayIn::create([
                'pay_id' => $payHeader->id,
                'income_type_id' => $basicPayType->id,
                'rate' => $basicAmount,
                'number_input' => 1.00,
            ]);
        }

        // 16. Seed Attendance Logs for July Period
        foreach ($seededEmployees as $emp) {
            // Log in and out daily for the period July 1-10 (10 days)
            for ($day = 1; $day <= 10; $day++) {
                $dateStr = sprintf('2026-07-%02d', $day);

                // Check-in
                AttendanceLog::create([
                    'employee_id' => $emp->id,
                    'company_id' => $company->id,
                    'biometric_id' => $emp->biometric_id,
                    'log_date' => $dateStr,
                    'log_time' => '08:00:00',
                    'log_datetime' => "{$dateStr} 08:00:00",
                    'log_type' => 'in',
                    'import_status' => 'processed',
                    'imported_by' => $hrUser->id,
                    'imported_at' => now(),
                ]);

                // Check-out
                AttendanceLog::create([
                    'employee_id' => $emp->id,
                    'company_id' => $company->id,
                    'biometric_id' => $emp->biometric_id,
                    'log_date' => $dateStr,
                    'log_time' => '17:00:00',
                    'log_datetime' => "{$dateStr} 17:00:00",
                    'log_type' => 'out',
                    'import_status' => 'processed',
                    'imported_by' => $hrUser->id,
                    'imported_at' => now(),
                ]);
            }
        }

        // 17. Seed Approved HR Adjustments for July Period
        HRAdjustment::create([
            'employee_id' => $alice->id,
            'company_id' => $company->id,
            'adjustment_type' => 'income',
            'income_type_id' => $bonusType->id,
            'amount' => 3000.00,
            'reason' => 'Sign-off performance bonus',
            'effective_date' => '2026-07-10',
            'status' => 'approved',
            'created_by' => $hrUser->id,
            'approved_by' => $companyAdmin->id,
            'approved_at' => now(),
        ]);
    }
}
