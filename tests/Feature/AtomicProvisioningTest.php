<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use App\Services\CompanyProvisioningService;
use App\Services\EmployeeProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AtomicProvisioningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['id' => 1, 'name' => 'SuperAdmin']);
        Role::firstOrCreate(['id' => 2, 'name' => 'CompanyAdmin']);
        Role::firstOrCreate(['id' => 3, 'name' => 'HR']);
        Role::firstOrCreate(['id' => 4, 'name' => 'Employee']);
    }

    public function test_employee_creation_rolls_back_user_if_employee_write_fails(): void
    {
        $companyUser = User::factory()->create([
            'role_id' => 2,
            'role' => 'company',
        ]);
        $company = Company::create([
            'user_id' => $companyUser->id,
            'name' => 'Acme Corp',
            'company_code' => 'ACME-0001',
            'email' => 'acme@test.com',
            'status' => 1,
        ]);

        $service = new EmployeeProvisioningService();

        // Listen for Employee creating/saving event and throw an exception to simulate second write failure
        Employee::saving(function ($employee) {
            throw new \RuntimeException('Simulated second-write database failure');
        });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Simulated second-write database failure');

        try {
            $service->provision([
                'name' => 'Orphan Test User',
                'email' => 'orphan@test.com',
                'password' => 'secret123',
                'phone' => '1234567890',
                'role' => 'employee',
                'address' => '123 Street',
            ], $companyUser->id, $company->id);
        } finally {
            // Verify that NO orphan User record exists in DB
            $this->assertDatabaseMissing('users', ['email' => 'orphan@test.com']);
            $this->assertDatabaseMissing('employees', ['address' => '123 Street']);
        }
    }

    public function test_company_creation_rolls_back_user_if_company_write_fails(): void
    {
        $service = new CompanyProvisioningService();

        // Listen for Company saving event and throw exception
        Company::saving(function ($company) {
            throw new \RuntimeException('Simulated company write failure');
        });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Simulated company write failure');

        try {
            $service->provision([
                'name' => 'Company Owner',
                'email' => 'owner@failcompany.com',
                'password' => 'secret123',
                'companyName' => 'Fail Company',
                'company_code' => 'FAIL-001',
                'companyEmail' => 'info@failcompany.com',
                'phone' => '1234567890',
                'website' => 'https://failcompany.com',
            ]);
        } finally {
            // Verify that NO orphan User record exists in DB
            $this->assertDatabaseMissing('users', ['email' => 'owner@failcompany.com']);
            $this->assertDatabaseMissing('companies', ['email' => 'info@failcompany.com']);
        }
    }

    public function test_unique_constraint_exception_returns_user_safe_validation_error(): void
    {
        User::factory()->create(['email' => 'existing@test.com']);

        $service = new EmployeeProvisioningService();

        $this->expectException(ValidationException::class);

        $service->provision([
            'name' => 'Duplicate Email User',
            'email' => 'existing@test.com',
            'password' => 'secret123',
            'phone' => '1234567890',
            'role' => 'employee',
            'address' => '123 Street',
        ], 1, 1);
    }

    public function test_successful_employee_and_company_provisioning_creates_complete_aggregate(): void
    {
        Mail::fake();

        $companyService = new CompanyProvisioningService();
        $companyResult = $companyService->provision([
            'name' => 'Owner Person',
            'email' => 'owner@success.com',
            'password' => 'secret123',
            'companyName' => 'Success Company',
            'company_code' => 'SUCC-001',
            'companyEmail' => 'info@success.com',
            'phone' => '1234567890',
            'website' => 'https://success.com',
        ]);

        $this->assertDatabaseHas('users', ['email' => 'owner@success.com']);
        $this->assertDatabaseHas('companies', ['company_code' => 'SUCC-001']);
        $this->assertEquals($companyResult['user']->id, $companyResult['company']->user_id);

        $employeeService = new EmployeeProvisioningService();
        $employeeResult = $employeeService->provision([
            'name' => 'Staff Person',
            'email' => 'staff@success.com',
            'password' => 'secret123',
            'phone' => '1234567890',
            'role' => 'employee',
            'address' => '456 Staff Ave',
        ], $companyResult['user']->id, $companyResult['company']->id);

        $this->assertDatabaseHas('users', ['email' => 'staff@success.com']);
        $this->assertDatabaseHas('employees', ['e_user_id' => $employeeResult['user']->id]);
    }

    public function test_post_commit_mail_failure_does_not_rollback_committed_aggregate(): void
    {
        config(['mail.notifications_enabled' => true]);

        // Cause mail sending to throw an exception
        Mail::shouldReceive('to->queue')->andThrow(new \Exception('Mail server connection failed'));

        $companyService = new CompanyProvisioningService();
        $result = $companyService->provision([
            'name' => 'Mail Fail Owner',
            'email' => 'mailfail@test.com',
            'password' => 'secret123',
            'companyName' => 'Mail Fail Corp',
            'company_code' => 'MFAIL-01',
            'companyEmail' => 'info@mailfail.com',
            'phone' => '1234567890',
            'website' => 'https://mailfail.com',
        ]);

        // Aggregate must remain committed in DB despite mail failure
        $this->assertDatabaseHas('users', ['email' => 'mailfail@test.com']);
        $this->assertDatabaseHas('companies', ['company_code' => 'MFAIL-01']);
    }
}
