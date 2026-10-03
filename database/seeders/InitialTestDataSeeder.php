<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Company;
use App\Models\Department;
use App\Models\SubDepartment;
use App\Models\EmployeeMaster;
use App\Models\LoanType;

class InitialTestDataSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Get or create a company
        $company = Company::firstOrCreate(
            ['email' => 'company@gmail.com'],
            [
                'name' => 'Payrole Company',
                'detail' => 'Main Payroll Company',
                'status' => 1,
            ]
        );

        // 2. Get or create department
        $department = Department::firstOrCreate(
            ['company_id' => $company->id, 'code' => 'IT'],
            [
                'name' => 'Information Technology',
                'is_active' => true,
            ]
        );

        // 3. Get or create sub-department
        $subDepartment = SubDepartment::firstOrCreate(
            ['company_id' => $company->id, 'department_id' => $department->id, 'code' => 'DEV'],
            [
                'name' => 'Software Development',
                'is_active' => true,
            ]
        );

        // 4. Create test employees in employees_master
        EmployeeMaster::firstOrCreate(
            ['employee_code' => 'EMP-001'],
            [
                'company_id' => $company->id,
                'first_name' => 'John',
                'middle_name' => 'M',
                'last_name' => 'Doe',
                'email' => 'john.doe@example.com',
                'phone' => '1234567890',
                'date_of_birth' => '1990-01-01',
                'date_hired' => '2020-01-01',
                'position' => 'Software Engineer',
                'dept_id' => $department->id,
                'sub_dept_id' => $subDepartment->id,
                'monthly_rate' => 30000.00,
                'daily_rate' => 1500.00,
                'hourly_rate' => 187.50,
                'status' => 'active',
            ]
        );

        // 5. Create test loan types
        $loanTypes = ['SSS Loan', 'Pag-IBIG Loan', 'Vale', 'Company Loan'];
        foreach ($loanTypes as $name) {
            LoanType::firstOrCreate([
                'company_id' => $company->id,
                'name' => $name,
            ]);
        }
    }
}
