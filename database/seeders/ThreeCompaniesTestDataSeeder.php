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
use App\Models\AttendanceLog;
use App\Models\PayrollPeriod;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class ThreeCompaniesTestDataSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Disable FK constraints and clear tables
        \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();
        DB::table('companies')->delete();
        DB::table('users')->delete();
        DB::table('employees')->delete();
        DB::table('employees_master')->delete();
        DB::table('emp_income')->delete();
        DB::table('emp_deductions')->delete();
        DB::table('emp_loans')->delete();
        DB::table('attendance_logs')->delete();
        DB::table('payroll_periods')->delete();
        DB::table('pay_headers')->delete();
        DB::table('pay_ins')->delete();
        DB::table('pay_outs')->delete();
        \Illuminate\Support\Facades\Schema::enableForeignKeyConstraints();

        // 2. Ensure Roles Exist
        $roles = [
            ['id' => 1, 'name' => 'SuperAdmin'],
            ['id' => 2, 'name' => 'CompanyAdmin'],
            ['id' => 3, 'name' => 'HR'],
            ['id' => 4, 'name' => 'Employee'],
        ];
        foreach ($roles as $role) {
            DB::table('roles')->updateOrInsert(['id' => $role['id']], $role);
        }

        // 3. Create Super Admin User
        User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@example.com',
            'password' => Hash::make('password'),
            'role_id' => 1,
            'status' => 1,
            'company_id' => null,
        ]);

        // Define 3 Companies to seed
        $companiesData = [
            [
                'name' => 'Alpha Technologies',
                'code' => 'ALPHA',
                'domain' => 'alpha-tech.com',
                'detail' => 'Software & cloud computing provider.',
            ],
            [
                'name' => 'Beta Logistics',
                'code' => 'BETA',
                'domain' => 'beta-logistics.com',
                'detail' => 'Supply chain & delivery networks.',
            ],
            [
                'name' => 'Gamma Healthcare',
                'code' => 'GAMMA',
                'domain' => 'gamma-health.com',
                'detail' => 'Clinics and medical research services.',
            ],
        ];

        foreach ($companiesData as $companyIdx => $comp) {
            // A. Create Company Admin User
            $adminUser = User::create([
                'name' => $comp['name'] . ' Admin',
                'email' => 'admin@' . $comp['domain'],
                'password' => Hash::make('password'),
                'role_id' => 2, // CompanyAdmin
                'status' => 1,
            ]);

            // B. Create Company record
            $company = Company::create([
                'user_id' => $adminUser->id,
                'company_code' => $comp['code'],
                'name' => $comp['name'],
                'email' => 'admin@' . $comp['domain'],
                'phone' => 123456789 + $companyIdx,
                'website' => 'www.' . $comp['domain'],
                'detail' => $comp['detail'],
                'status' => 1,
            ]);

            // Link company admin user to the company
            User::withoutGlobalScopes()->where('id', $adminUser->id)->update([
                'company_id' => $company->id,
            ]);

            // C. Create Departments
            $deptEngineering = Department::create([
                'company_id' => $company->id,
                'code' => 'ENG',
                'name' => 'Engineering',
                'is_active' => true,
            ]);
            $subDeptDev = SubDepartment::create([
                'company_id' => $company->id,
                'department_id' => $deptEngineering->id,
                'code' => 'DEV',
                'name' => 'Software Development',
                'is_active' => true,
            ]);

            $deptHR = Department::create([
                'company_id' => $company->id,
                'code' => 'HR',
                'name' => 'Human Resources',
                'is_active' => true,
            ]);
            $subDeptOps = SubDepartment::create([
                'company_id' => $company->id,
                'department_id' => $deptHR->id,
                'code' => 'OPS',
                'name' => 'HR Operations',
                'is_active' => true,
            ]);

            // D. Seed Income and Deduction types for company
            $allowanceType = IncomeType::firstOrCreate(['code' => 'ALLOWANCE'], ['name' => 'Allowance']);
            $bonusType = IncomeType::firstOrCreate(['code' => 'BONUS'], ['name' => 'Bonus']);
            $otherDeductionType = DeductionType::firstOrCreate(['code' => 'OTHER'], ['name' => 'Other Deduction']);
            $sssLoanDeductionType = DeductionType::firstOrCreate(['code' => 'SSS_LOAN'], ['name' => 'SSS Loan']);

            // E. Seed Loan Types
            $valeLoanType = LoanType::create([
                'company_id' => $company->id,
                'name' => 'Vale',
            ]);
            $companyLoanType = LoanType::create([
                'company_id' => $company->id,
                'name' => 'Company Loan',
            ]);

            // F. Create 10 Employees: 2 HR and 8 normal Employees
            // 2 HR users
            for ($i = 1; $i <= 2; $i++) {
                $employeeCode = 'EMP-' . $comp['code'] . '-HR-0' . $i;
                $firstName = 'HR_User_' . $i;
                $lastName = $comp['code'];
                $email = 'hr' . $i . '@' . $comp['domain'];

                // Create User
                $user = User::create([
                    'name' => $firstName . ' ' . $lastName,
                    'email' => $email,
                    'password' => Hash::make('password'),
                    'role_id' => 3, // HR
                    'status' => 1,
                    'company_id' => $company->id,
                ]);

                // Create Employee Mapping
                Employee::create([
                    'owner_user_id' => $adminUser->id,
                    'e_user_id' => $user->id,
                    'designation' => 'Hr',
                    'id_number' => $employeeCode,
                    'status' => 1,
                ]);

                // Create Employee Master
                $empMaster = EmployeeMaster::create([
                    'company_id' => $company->id,
                    'employee_code' => $employeeCode,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $email,
                    'phone' => '987654321' . $i,
                    'date_of_birth' => '1985-05-1' . $i,
                    'date_hired' => '2023-01-10',
                    'position' => 'HR Operations Specialist',
                    'dept_id' => $deptHR->id,
                    'sub_dept_id' => $subDeptOps->id,
                    'monthly_rate' => 40000.00 + ($i * 1000),
                    'daily_rate' => 2000.00 + ($i * 50),
                    'hourly_rate' => 250.00 + ($i * 6),
                    'status' => 'active',
                    'biometric_id' => 'BIO-' . $employeeCode,
                ]);

                // Link User to EmployeeMaster
                User::withoutGlobalScopes()->where('id', $user->id)->update([
                    'emp_id' => $empMaster->id,
                ]);

                // Seed Random Incomes and Deductions
                EmpIncome::create([
                    'employee_id' => $empMaster->id,
                    'income_type_id' => $allowanceType->id,
                    'rate' => 1500.00,
                    'frequency' => 'monthly',
                    'is_active' => true,
                ]);
                EmpDeduction::create([
                    'employee_id' => $empMaster->id,
                    'deduction_type_id' => $otherDeductionType->id,
                    'rate' => 300.00,
                    'frequency' => 'monthly',
                    'is_active' => true,
                ]);
            }

            // 8 normal Employees
            for ($i = 1; $i <= 8; $i++) {
                $employeeCode = 'EMP-' . $comp['code'] . '-0' . $i;
                $firstName = 'Employee_' . $i;
                $lastName = $comp['code'];
                $email = 'emp' . $i . '@' . $comp['domain'];

                // Create User
                $user = User::create([
                    'name' => $firstName . ' ' . $lastName,
                    'email' => $email,
                    'password' => Hash::make('password'),
                    'role_id' => 4, // Employee
                    'status' => 1,
                    'company_id' => $company->id,
                ]);

                // Create Employee Mapping
                Employee::create([
                    'owner_user_id' => $adminUser->id,
                    'e_user_id' => $user->id,
                    'designation' => 'Employee',
                    'id_number' => $employeeCode,
                    'status' => 1,
                ]);

                // Create Employee Master
                $empMaster = EmployeeMaster::create([
                    'company_id' => $company->id,
                    'employee_code' => $employeeCode,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $email,
                    'phone' => '912345678' . $i,
                    'date_of_birth' => '1992-08-1' . $i,
                    'date_hired' => '2024-06-15',
                    'position' => 'Software Engineer',
                    'dept_id' => $deptEngineering->id,
                    'sub_dept_id' => $subDeptDev->id,
                    'monthly_rate' => 45000.00 + ($i * 2000),
                    'daily_rate' => 2250.00 + ($i * 100),
                    'hourly_rate' => 281.25 + ($i * 12),
                    'status' => 'active',
                    'biometric_id' => 'BIO-' . $employeeCode,
                ]);

                // Link User to EmployeeMaster
                User::withoutGlobalScopes()->where('id', $user->id)->update([
                    'emp_id' => $empMaster->id,
                ]);

                // Seed Random Incomes/Deductions/Loans/Attendance
                EmpIncome::create([
                    'employee_id' => $empMaster->id,
                    'income_type_id' => $allowanceType->id,
                    'rate' => 2000.00,
                    'frequency' => 'monthly',
                    'is_active' => true,
                ]);

                EmpDeduction::create([
                    'employee_id' => $empMaster->id,
                    'deduction_type_id' => $otherDeductionType->id,
                    'rate' => 500.00,
                    'frequency' => 'monthly',
                    'is_active' => true,
                ]);

                // Seed a random loan for even employees
                if ($i % 2 === 0) {
                    EmpLoan::create([
                        'employee_id' => $empMaster->id,
                        'loan_type_id' => $valeLoanType->id,
                        'principal_amount' => 5000.00,
                        'current_balance' => 5000.00,
                    ]);
                }

                // Seed some random attendance logs (clock-in/clock-out) for testing
                $startDate = Carbon::now()->subDays(5);
                for ($d = 0; $d < 5; $d++) {
                    $logDate = $startDate->copy()->addDays($d);
                    // Skip weekend
                    if ($logDate->isWeekend()) {
                        continue;
                    }

                    // Clock In (around 8 AM)
                    AttendanceLog::create([
                        'employee_id' => $empMaster->id,
                        'company_id' => $company->id,
                        'log_datetime' => $logDate->copy()->setHour(8)->setMinute(rand(0, 15))->toDateTimeString(),
                        'log_date' => $logDate->toDateString(),
                        'log_type' => 'in',
                        'biometric_id' => 'BIO-' . $employeeCode,
                    ]);

                    // Clock Out (around 5 PM)
                    AttendanceLog::create([
                        'employee_id' => $empMaster->id,
                        'company_id' => $company->id,
                        'log_datetime' => $logDate->copy()->setHour(17)->setMinute(rand(0, 10))->toDateTimeString(),
                        'log_date' => $logDate->toDateString(),
                        'log_type' => 'out',
                        'biometric_id' => 'BIO-' . $employeeCode,
                    ]);
                }
            }
        }
    }
}
