<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\User;
use App\Models\Company;
use App\Models\EmployeeMaster;
use App\Models\Department;
use App\Models\SubDepartment;
use App\Models\PayHeader;
use App\Models\EmpLoan;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Temporarily disable foreign key checks for clean truncation
        Schema::disableForeignKeyConstraints();

        // 1. Flush all tenant, transactional, and employee records
        $tablesToTruncate = [
            'activity_logs',
            'attendance_logs',
            'government_contributions',
            'loan_payments',
            'pay_ins',
            'pay_outs',
            'pay_headers',
            'payroll_drafts',
            'hr_adjustments',
            'payroll_periods',
            'loan_requests',
            'emp_loans',
            'emp_income',
            'emp_deductions',
            'employees',
            'employees_master',
            'sub_departments',
            'departments',
            'company_bank_settings',
            'user_company_access',
            'loan_types',
            'companies',
        ];

        foreach ($tablesToTruncate as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->truncate();
            }
        }

        // 2. Wipe all users except the SuperAdmin (role_id = 1)
        User::where('role_id', '!=', 1)->orWhereNull('role_id')->delete();

        // Ensure SuperAdmin user exists and credentials (admin@gmail.com / password) are explicitly set/updated
        $superAdmin = User::updateOrCreate(
            ['role_id' => 1],
            [
                'name' => 'Super Admin',
                'email' => 'admin@gmail.com',
                'password' => bcrypt('password'),
                'status' => 1,
                'company_id' => null,
            ]
        );

        Schema::enableForeignKeyConstraints();

        // ==============================================================================
        // 3. SEEDING COMPANY A: Alpha Technologies (Tenant 1)
        // ==============================================================================
        $alphaCompany = Company::create([
            'company_code' => 'ALPHA',
            'user_id' => $superAdmin->id,
            'name' => 'Alpha Technologies',
            'email' => 'admin@alpha-tech.com',
            'phone' => 123456789,
            'website' => 'www.alpha-tech.com',
            'status' => 1,
        ]);

        $alphaAdmin = User::create([
            'name' => 'Alpha Technologies Admin',
            'email' => 'admin@alpha-tech.com',
            'password' => bcrypt('password'),
            'status' => 1,
            'role_id' => 2, // CompanyAdmin
            'company_id' => $alphaCompany->id,
        ]);

        $alphaDept = Department::create([
            'company_id' => $alphaCompany->id,
            'code' => 'ENG',
            'name' => 'Engineering',
            'is_active' => 1,
        ]);

        $alphaSubDept = SubDepartment::create([
            'company_id' => $alphaCompany->id,
            'department_id' => $alphaDept->id,
            'code' => 'DEV',
            'name' => 'Software Development',
            'is_active' => 1,
        ]);

        $alphaEmp = EmployeeMaster::create([
            'company_id' => $alphaCompany->id,
            'employee_code' => 'EMP-ALPHA-01',
            'first_name' => 'Alice',
            'last_name' => 'Turing',
            'email' => 'alice@alpha-tech.com',
            'date_hired' => '2024-01-15',
            'position' => 'Senior Engineer',
            'dept_id' => $alphaDept->id,
            'sub_dept_id' => $alphaSubDept->id,
            'monthly_rate' => 60000.00,
            'status' => 'active',
        ]);

        User::create([
            'name' => 'Alice Turing',
            'email' => 'alice@alpha-tech.com',
            'password' => bcrypt('password'),
            'status' => 1,
            'role_id' => 4, // Employee
            'company_id' => $alphaCompany->id,
            'emp_id' => $alphaEmp->id,
        ]);

        // ==============================================================================
        // 4. SEEDING COMPANY B: Omega Global Logistics (Tenant 2 - Multi-Tenancy Check)
        // ==============================================================================
        $omegaCompany = Company::create([
            'company_code' => 'OMEGA',
            'user_id' => $superAdmin->id,
            'name' => 'Omega Global Logistics',
            'email' => 'admin@omegacorp.com',
            'phone' => 18005559876,
            'website' => 'www.omegacorp.com',
            'status' => 1,
        ]);

        $omegaAdmin = User::create([
            'name' => 'Omega Company Admin',
            'email' => 'admin@omegacorp.com',
            'password' => bcrypt('password'),
            'status' => 1,
            'role_id' => 2, // CompanyAdmin
            'company_id' => $omegaCompany->id,
        ]);

        $omegaDept = Department::create([
            'company_id' => $omegaCompany->id,
            'code' => 'LOG',
            'name' => 'Logistics & Fleet',
            'is_active' => 1,
        ]);

        $omegaSubDept = SubDepartment::create([
            'company_id' => $omegaCompany->id,
            'department_id' => $omegaDept->id,
            'code' => 'DIST',
            'name' => 'Distribution & Dispatch',
            'is_active' => 1,
        ]);

        $omegaEmp = EmployeeMaster::create([
            'company_id' => $omegaCompany->id,
            'employee_code' => 'OMEGA-001',
            'first_name' => 'Grace',
            'last_name' => 'Hopper',
            'email' => 'grace@omegacorp.com',
            'date_hired' => '2023-05-10',
            'position' => 'Fleet Operations Manager',
            'dept_id' => $omegaDept->id,
            'sub_dept_id' => $omegaSubDept->id,
            'monthly_rate' => 65000.00,
            'status' => 'active',
        ]);

        User::create([
            'name' => 'Grace Hopper',
            'email' => 'grace@omegacorp.com',
            'password' => bcrypt('password'),
            'status' => 1,
            'role_id' => 4, // Employee
            'company_id' => $omegaCompany->id,
            'emp_id' => $omegaEmp->id,
        ]);

        $this->call([
            IncomeTypesSeeder::class,
            DeductionTypesSeeder::class,
        ]);
    }
}

