<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\DeductionType;
use App\Models\EmployeeMaster;
use App\Models\PayHeader;
use App\Models\PayOut;
use App\Models\PayrollPeriod;
use App\Models\Role;
use App\Models\User;
use App\Services\PayrollProcessingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyDeductionDefaultsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['id' => 1, 'name' => 'SuperAdmin']);
        Role::firstOrCreate(['id' => 2, 'name' => 'CompanyAdmin']);
        Role::firstOrCreate(['id' => 3, 'name' => 'HR']);
        Role::firstOrCreate(['id' => 4, 'name' => 'Employee']);

        \App\Models\IncomeType::firstOrCreate(['code' => 'BASIC'], ['name' => 'Basic Pay', 'category' => 'basic']);
        \App\Models\IncomeType::firstOrCreate(['code' => 'OVERTIME'], ['name' => 'Overtime Pay', 'category' => 'overtime']);
        DeductionType::firstOrCreate(['code' => 'SSS'], ['name' => 'SSS Contribution', 'category' => 'statutory']);
        DeductionType::firstOrCreate(['code' => 'PHILHEALTH'], ['name' => 'PhilHealth Contribution', 'category' => 'statutory']);
        DeductionType::firstOrCreate(['code' => 'PAGIBIG'], ['name' => 'Pag-IBIG Contribution', 'category' => 'statutory']);
        DeductionType::firstOrCreate(['code' => 'TAX'], ['name' => 'Withholding Tax', 'category' => 'tax']);
        DeductionType::firstOrCreate(['code' => 'VALE'], ['name' => 'Vale / Cash Advance', 'category' => 'loan']);
        DeductionType::firstOrCreate(['code' => 'TARDINESS'], ['name' => 'Late / Tardiness', 'category' => 'attendance']);
    }

    public function test_can_save_company_wide_deduction_defaults(): void
    {
        $admin = User::factory()->create(['role_id' => 1, 'role' => 'admin']);
        $company = Company::create([
            'user_id' => $admin->id,
            'name' => 'Defaults Comp',
            'company_code' => 'DFC',
            'email' => 'dfc@test.com',
            'status' => 1,
        ]);

        $response = $this->actingAs($admin)
            ->post(panelRoute('payroll-drafts.save-company-deduction-defaults'), [
                'company_id' => $company->id,
                'use_fixed_amount' => '1',
                'deductions' => [
                    ['deduction_type' => 'PHILHEALTH', 'display' => '1', 'amount' => 450.00],
                    ['deduction_type' => 'VALE', 'display' => '1', 'amount' => 600.00],
                ],
            ]);

        $response->assertSessionHas('status', 'Company-wide deduction defaults saved and enforced successfully.');

        $company->refresh();
        $defaults = $company->deduction_defaults;

        $this->assertTrue($defaults['use_fixed_amount']);
        $this->assertCount(2, $defaults['deductions']);
        $this->assertEquals(450.00, $defaults['deductions'][0]['amount']);
        $this->assertEquals(600.00, $defaults['deductions'][1]['amount']);
    }

    public function test_draft_generation_automatically_enforces_company_deduction_defaults(): void
    {
        $admin = User::factory()->create(['role_id' => 1, 'role' => 'admin']);
        $company = Company::create([
            'user_id' => $admin->id,
            'name' => 'Enforced Comp',
            'company_code' => 'EFC',
            'email' => 'efc@test.com',
            'status' => 1,
        ]);

        // Save company defaults: Fixed mode with PhilHealth = 480.00 and Tardiness = 300.00
        $company->setDeductionDefaults([
            'use_fixed_amount' => true,
            'deductions' => [
                ['deduction_type' => 'PHILHEALTH', 'display' => true, 'amount' => 480.00],
                ['deduction_type' => 'TARDINESS', 'display' => true, 'amount' => 300.00],
            ],
        ]);

        $dept = \App\Models\Department::create([
            'company_id' => $company->id,
            'name'       => 'Default Dept',
            'code'       => 'DEF',
            'is_active'  => true,
        ]);

        $employee = EmployeeMaster::create([
            'company_id' => $company->id,
            'dept_id'    => $dept->id,
            'employee_code' => 'EMP-EFC-01',
            'first_name' => 'David',
            'last_name' => 'Enforced',
            'monthly_rate' => 35000.00,
            'status' => 'active',
        ]);

        $period = PayrollPeriod::create([
            'company_id' => $company->id,
            'period_name' => 'Enforced Period',
            'period_start' => '2026-10-01',
            'period_end' => '2026-10-15',
            'status' => 'open',
        ]);

        $service = app(PayrollProcessingService::class);

        // Generate draft WITHOUT passing explicit options (service auto-loads company defaults)
        $drafts = $service->generateDraft($period, [$employee->id]);

        $this->assertCount(1, $drafts);
        $header = $drafts->first();

        $phType = DeductionType::where('code', 'PHILHEALTH')->first();
        $tardinessType = DeductionType::where('code', 'TARDINESS')->first();

        $phPayOut = PayOut::where('pay_id', $header->id)->where('deduction_type_id', $phType->id)->first();
        $this->assertNotNull($phPayOut);
        $this->assertEquals(480.00, (float) $phPayOut->rate);

        $tardinessPayOut = PayOut::where('pay_id', $header->id)->where('deduction_type_id', $tardinessType->id)->first();
        $this->assertNotNull($tardinessPayOut);
        $this->assertEquals(300.00, (float) $tardinessPayOut->rate);
    }
}
