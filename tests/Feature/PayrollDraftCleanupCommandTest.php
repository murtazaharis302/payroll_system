<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\EmployeeMaster;
use App\Models\PayHeader;
use App\Models\PayrollPeriod;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollDraftCleanupCommandTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected Company $companyA;
    protected Company $companyB;
    protected PayrollPeriod $periodA;
    protected EmployeeMaster $empA;
    protected EmployeeMaster $empB;
    protected PayHeader $draftHeaderA;
    protected PayHeader $approvedHeaderA;
    protected PayHeader $draftHeaderB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role_id' => Role::SUPER_ADMIN,
            'role' => 'admin',
        ]);

        $this->companyA = Company::create([
            'user_id' => $this->adminUser->id,
            'name' => 'Company A',
            'company_code' => 'CMP-A',
            'email' => 'a@test.com',
            'status' => 1,
        ]);

        $this->companyB = Company::create([
            'user_id' => $this->adminUser->id,
            'name' => 'Company B',
            'company_code' => 'CMP-B',
            'email' => 'b@test.com',
            'status' => 1,
        ]);

        $this->periodA = PayrollPeriod::create([
            'company_id' => $this->companyA->id,
            'period_name' => 'Jan 2025 Cutoff 1',
            'period_start' => '2025-01-01',
            'period_end' => '2025-01-15',
            'pay_date' => '2025-01-20',
            'status' => 'open',
        ]);

        $this->empA = EmployeeMaster::create([
            'company_id' => $this->companyA->id,
            'employee_code' => 'EMP-A',
            'first_name' => 'Alice',
            'last_name' => 'Alpha',
            'monthly_rate' => 30000.00,
            'status' => 'active',
        ]);

        $this->empB = EmployeeMaster::create([
            'company_id' => $this->companyB->id,
            'employee_code' => 'EMP-B',
            'first_name' => 'Bob',
            'last_name' => 'Beta',
            'monthly_rate' => 35000.00,
            'status' => 'active',
        ]);

        // Draft header for Company A
        $this->draftHeaderA = PayHeader::create([
            'payroll_period_id' => $this->periodA->id,
            'employee_id' => $this->empA->id,
            'status' => 'draft',
            'total_gross_pay' => 15000.00,
            'total_deductions' => 1000.00,
            'net_pay' => 14000.00,
        ]);

        // Approved header for Company A (MUST NOT BE DELETED)
        $this->approvedHeaderA = PayHeader::create([
            'payroll_period_id' => $this->periodA->id,
            'employee_id' => $this->empA->id,
            'status' => 'approved',
            'total_gross_pay' => 15000.00,
            'total_deductions' => 1000.00,
            'net_pay' => 14000.00,
        ]);

        // Draft header for Company B (MUST NOT BE DELETED WHEN CLEANING COMPANY A)
        $this->draftHeaderB = PayHeader::create([
            'payroll_period_id' => $this->periodA->id,
            'employee_id' => $this->empB->id,
            'status' => 'draft',
            'total_gross_pay' => 17500.00,
            'total_deductions' => 1200.00,
            'net_pay' => 16300.00,
        ]);
    }

    /** @test */
    public function command_refuses_execution_in_production_environment(): void
    {
        app()['env'] = 'production';

        $this->artisan('payroll:cleanup-drafts', [
            '--company' => $this->companyA->id,
            '--period' => $this->periodA->id,
        ])
        ->expectsOutput('Draft cleanup command is strictly disabled in production environments.')
        ->assertExitCode(1);
    }

    /** @test */
    public function command_requires_company_and_period_options(): void
    {
        $this->artisan('payroll:cleanup-drafts')
            ->expectsOutput('Both --company and --period options are required.')
            ->assertExitCode(1);
    }

    /** @test */
    public function dry_run_mode_outputs_counts_and_does_not_delete_records(): void
    {
        $this->artisan('payroll:cleanup-drafts', [
            '--company' => $this->companyA->id,
            '--period' => $this->periodA->id,
            '--dry-run' => true,
        ])
        ->expectsOutputToContain('DRY-RUN INSPECTION RESULT')
        ->assertExitCode(0);

        // Database records MUST still exist
        $this->assertDatabaseHas('pay_headers', ['id' => $this->draftHeaderA->id]);
        $this->assertDatabaseHas('pay_headers', ['id' => $this->approvedHeaderA->id]);
        $this->assertDatabaseHas('pay_headers', ['id' => $this->draftHeaderB->id]);
    }

    /** @test */
    public function command_deletes_only_draft_records_for_specified_company_and_period(): void
    {
        $this->artisan('payroll:cleanup-drafts', [
            '--company' => $this->companyA->id,
            '--period' => $this->periodA->id,
            '--confirm-token' => 'CLEANUP-DRAFTS-CONFIRM',
        ])
        ->expectsOutputToContain("Successfully deleted 1 draft payroll records")
        ->assertExitCode(0);

        // Company A draft MUST be deleted
        $this->assertDatabaseMissing('pay_headers', ['id' => $this->draftHeaderA->id]);

        // Approved record for Company A MUST NOT be deleted
        $this->assertDatabaseHas('pay_headers', ['id' => $this->approvedHeaderA->id]);

        // Draft record for Company B MUST NOT be deleted
        $this->assertDatabaseHas('pay_headers', ['id' => $this->draftHeaderB->id]);
    }

    /** @test */
    public function removed_purge_and_delete_all_web_endpoints_return_404(): void
    {
        $this->actingAs($this->adminUser);

        $responseDeleteAll = $this->post('/payroll-drafts/delete-all');
        $this->assertEquals(404, $responseDeleteAll->status());

        $responsePurge = $this->post('/settings/purge-system');
        $this->assertEquals(404, $responsePurge->status());
    }
}
