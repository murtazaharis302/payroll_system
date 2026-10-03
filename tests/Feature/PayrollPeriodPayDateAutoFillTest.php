<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\PayrollPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollPeriodPayDateAutoFillTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Company $company;
    protected PayrollPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->company = Company::create([
            'user_id' => $this->user->id,
            'name' => 'Pay Date Auto-Fill Test Co',
            'email' => 'autofill_test@example.com',
            'company_code' => 'COMP-7777',
            'status' => 1,
        ]);

        $this->period = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'period_name' => 'July 2026 - 2nd Half',
            'period_start' => '2026-07-16',
            'period_end' => '2026-07-31',
            'pay_date' => '2026-07-31',
            'status' => 'open',
        ]);
    }

    public function test_create_payroll_period_view_contains_pay_date_autofill_script(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('payroll-periods.create'));

        $response->assertStatus(200);
        $response->assertSee('id="period_end"', false);
        $response->assertSee('id="pay_date"', false);
        $response->assertSee('updatePayDate', false);
    }

    public function test_edit_payroll_period_view_contains_pay_date_autofill_script(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('payroll-periods.edit', $this->period->id));

        $response->assertStatus(200);
        $response->assertSee('id="period_end"', false);
        $response->assertSee('id="pay_date"', false);
        $response->assertSee('updatePayDate', false);
    }
}
