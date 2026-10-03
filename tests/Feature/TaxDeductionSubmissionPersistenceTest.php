<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\DeductionType;
use App\Models\Department;
use App\Models\EmployeeMaster;
use App\Models\PayHeader;
use App\Models\PayOut;
use App\Models\PayrollPeriod;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DeductionTypesSeeder;
use Database\Seeders\IncomeTypesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TaxDeductionSubmissionPersistenceTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected Company $company;
    protected Department $dept;
    protected DeductionType $taxDeduction;
    protected \App\Models\IncomeType $basicIncome;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IncomeTypesSeeder::class);
        $this->seed(DeductionTypesSeeder::class);

        Role::firstOrCreate(['id' => 1, 'name' => 'SuperAdmin']);
        Role::firstOrCreate(['id' => 2, 'name' => 'CompanyAdmin']);

        $this->adminUser = User::create([
            'name' => 'Admin User TaxTest',
            'email' => 'admin_tax@test.com',
            'password' => Hash::make('password'),
            'role' => 'company',
            'role_id' => 2,
        ]);

        $this->company = Company::create([
            'user_id' => $this->adminUser->id,
            'name' => 'Tax Test Corp',
            'company_code' => 'TAXTEST',
            'email' => 'tax@test.com',
            'status' => '1',
        ]);

        $this->adminUser->update(['company_id' => $this->company->id]);

        $this->dept = Department::create([
            'company_id' => $this->company->id,
            'code' => 'MAIN',
            'name' => 'Main Dept',
            'is_active' => true,
        ]);

        $this->taxDeduction = DeductionType::where('code', 'TAX')->firstOrFail();
        $this->basicIncome = \App\Models\IncomeType::where('code', 'BASIC')->firstOrFail();
    }

    /**
     * Test 1: Individual submit via draft update (submit_after_save=1) preserves saved TAX PayOut row ID and rate.
     */
    public function test_individual_submit_after_save_preserves_saved_tax_payout_row_and_amount(): void
    {
        $period = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'period_name' => 'Aug 2026 Cutoff',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'cut_off_start' => '2026-08-01',
            'cut_off_end' => '2026-08-15',
            'pay_date' => '2026-08-20',
            'period_number' => 1,
            'status' => 'open',
        ]);

        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-TAX-01',
            'first_name' => 'Tanya',
            'last_name' => 'Tax',
            'monthly_rate' => 35000.00,
            'status' => 'active',
        ]);

        $draft = PayHeader::create([
            'company_id' => $this->company->id,
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
            'status' => 'draft',
            'days_worked' => 11.0,
            'total_gross_pay' => 22000.00,
            'total_deductions' => 1500.00,
            'net_pay' => 20500.00,
        ]);

        \App\Models\PayIn::create([
            'pay_id' => $draft->id,
            'income_type_id' => $this->basicIncome->id,
            'number_input' => 11.0,
            'rate' => 2000.00,
        ]);

        $taxPayOut = PayOut::create([
            'pay_id' => $draft->id,
            'deduction_type_id' => $this->taxDeduction->id,
            'number_input' => 1.00,
            'rate' => 1500.00,
        ]);

        $taxPayOutId = $taxPayOut->id;

        $payload = [
            'days_worked' => 11.0,
            'submit_after_save' => '1',
            'pay_outs' => [
                $taxPayOutId => [
                    'id' => $taxPayOutId,
                    'rate' => 1500.00,
                    'number_input' => 1.00,
                ],
            ],
        ];

        $response = $this->actingAs($this->adminUser)
            ->put(panelRoute('payroll-drafts.update', ['id' => $draft->id]), $payload);

        $response->assertStatus(302);

        $draft->refresh();
        $this->assertEquals('pending_approval', $draft->status);

        // Assert existing PayOut row ID remains unchanged and rate is 1500.00
        $savedPayOut = PayOut::where('id', $taxPayOutId)->first();
        $this->assertNotNull($savedPayOut);
        $this->assertEquals($taxPayOutId, $savedPayOut->id);
        $this->assertEquals(1500.00, (float) $savedPayOut->rate);

        // Assert approval review screen renders saved Withholding Tax
        $reviewResponse = $this->actingAs($this->adminUser)
            ->get(panelRoute('payroll-approvals.review', ['id' => $draft->id]));

        $reviewResponse->assertStatus(200);
        $reviewResponse->assertSee('1,500.00');
    }

    /**
     * Test 2: Batch submit preserves saved TAX PayOut row ID and rate intact.
     */
    public function test_batch_submit_preserves_saved_tax_payout_row_and_amount(): void
    {
        $period = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'period_name' => 'Sep 2026 Cutoff',
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-15',
            'cut_off_start' => '2026-09-01',
            'cut_off_end' => '2026-09-15',
            'pay_date' => '2026-09-20',
            'period_number' => 1,
            'status' => 'open',
        ]);

        $employee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'dept_id' => $this->dept->id,
            'employee_code' => 'EMP-TAX-02',
            'first_name' => 'Brian',
            'last_name' => 'Batch',
            'monthly_rate' => 45000.00,
            'status' => 'active',
        ]);

        $draft = PayHeader::create([
            'company_id' => $this->company->id,
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
            'status' => 'draft',
            'days_worked' => 11.0,
            'total_gross_pay' => 22500.00,
            'total_deductions' => 1800.00,
            'net_pay' => 20700.00,
        ]);

        \App\Models\PayIn::create([
            'pay_id' => $draft->id,
            'income_type_id' => $this->basicIncome->id,
            'number_input' => 11.0,
            'rate' => 2045.45,
        ]);

        $taxPayOut = PayOut::create([
            'pay_id' => $draft->id,
            'deduction_type_id' => $this->taxDeduction->id,
            'number_input' => 1.00,
            'rate' => 1800.00,
        ]);

        $taxPayOutId = $taxPayOut->id;

        $response = $this->actingAs($this->adminUser)
            ->post(panelRoute('payroll-drafts.batch-submit'), [
                'draft_ids' => [$draft->id],
            ]);

        $response->assertStatus(302);

        $draft->refresh();
        $this->assertEquals('pending_approval', $draft->status);

        // Assert existing PayOut row ID remains unchanged and rate is 1800.00
        $savedPayOut = PayOut::where('id', $taxPayOutId)->first();
        $this->assertNotNull($savedPayOut);
        $this->assertEquals($taxPayOutId, $savedPayOut->id);
        $this->assertEquals(1800.00, (float) $savedPayOut->rate);

        // Assert approval review screen renders saved Withholding Tax
        $reviewResponse = $this->actingAs($this->adminUser)
            ->get(panelRoute('payroll-approvals.review', ['id' => $draft->id]));

        $reviewResponse->assertStatus(200);
        $reviewResponse->assertSee('1,800.00');
    }
}
