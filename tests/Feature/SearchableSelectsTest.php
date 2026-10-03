<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Company;
use App\Models\Department;
use App\Models\EmployeeMaster;
use App\Models\PayrollPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SearchableSelectsTest extends TestCase
{
    use RefreshDatabase;

    protected function createCompanyWithUser()
    {
        $user = User::factory()->create();

        $company = Company::create([
            'user_id'      => $user->id,
            'name'         => 'Test Company',
            'company_code' => 'TC01',
            'email'        => 'test@company.com',
            'status'       => 'active',
        ]);

        $user->update(['company_id' => $company->id]);

        return [$user, $company];
    }

    public function test_api_employee_search_returns_paginated_results()
    {
        [$user, $company] = $this->createCompanyWithUser();

        $dept = Department::create([
            'company_id' => $company->id,
            'name' => 'Engineering',
            'code' => 'ENG',
        ]);

        $employee = EmployeeMaster::create([
            'company_id'    => $company->id,
            'employee_code' => 'EMP-101',
            'first_name'    => 'John',
            'last_name'     => 'Doe',
            'dept_id'       => $dept->id,
            'status'        => 'active',
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/employees/search?q=John&company_id=' . $company->id);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'results' => [
                    '*' => ['id', 'text', 'employee_code', 'full_name', 'department']
                ],
                'pagination' => ['more']
            ]);

        $this->assertStringContainsString('John', $response->json('results.0.full_name'));
        $this->assertEquals('EMP-101', $response->json('results.0.employee_code'));
        $this->assertEquals('Engineering', $response->json('results.0.department'));
    }

    public function test_employee_select_component_renders_correctly()
    {
        $view = $this->blade('<x-employee-select name="employee_id" placeholder="Choose Employee" />');

        $view->assertSee('select2-employee-search');
        $view->assertSee('data-employee-search="true"', false);
        $view->assertSee('Choose Employee');
    }

    public function test_payroll_period_select_component_renders_and_groups_by_year()
    {
        [$user, $company] = $this->createCompanyWithUser();

        $period = PayrollPeriod::create([
            'company_id'   => $company->id,
            'period_name'  => '1st Half July 2026',
            'period_start' => '2026-07-01',
            'period_end'   => '2026-07-15',
            'pay_date'     => '2026-07-20',
            'status'       => 'open',
        ]);

        $view = $this->blade('<x-payroll-period-select name="payroll_period_id" :companyId="$companyId" />', [
            'companyId' => $company->id
        ]);

        $view->assertSee('optgroup label="2026"', false);
        $view->assertSee('1st Half July 2026');
        $view->assertSee('selected');
    }
}
