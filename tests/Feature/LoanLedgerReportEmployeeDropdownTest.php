<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\EmpLoan;
use App\Models\EmployeeMaster;
use App\Models\LoanType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoanLedgerReportEmployeeDropdownTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Company $company;
    protected EmployeeMaster $activeEmployee;
    protected EmployeeMaster $inactiveEmployeeWithLoan;
    protected LoanType $loanType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->company = Company::create([
            'user_id' => $this->user->id,
            'name' => 'Loan Ledger Test Co',
            'email' => 'loan_test@example.com',
            'company_code' => 'COMP-8888',
            'status' => 1,
        ]);

        $this->activeEmployee = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'employee_code' => 'EMP-ACTIVE-01',
            'first_name' => 'Alice',
            'last_name' => 'Active',
            'status' => 'active',
            'date_hired' => '2026-01-01',
        ]);

        $this->inactiveEmployeeWithLoan = EmployeeMaster::create([
            'company_id' => $this->company->id,
            'employee_code' => 'EMP-INACTIVE-02',
            'first_name' => 'Bob',
            'last_name' => 'InactiveWithLoan',
            'status' => 'inactive',
            'date_hired' => '2026-01-01',
        ]);

        $this->seed(\Database\Seeders\DeductionTypesSeeder::class);
        $valeDeduction = \App\Models\DeductionType::where('code', 'VALE')->first() ?? \App\Models\DeductionType::first();

        $this->loanType = LoanType::create([
            'company_id' => $this->company->id,
            'deduction_type_id' => $valeDeduction?->id,
            'name' => 'Vale/Cash Advance',
            'code' => 'VALE',
            'is_active' => true,
        ]);

        EmpLoan::create([
            'employee_id' => $this->inactiveEmployeeWithLoan->id,
            'loan_type_id' => $this->loanType->id,
            'principal_amount' => 5000.00,
            'current_balance' => 5000.00,
        ]);
    }

    public function test_reports_hub_index_includes_employees_with_loans(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('reports.index', ['company_id' => $this->company->id]));

        $response->assertStatus(200);
        $response->assertSee($this->activeEmployee->first_name);
        $response->assertSee($this->inactiveEmployeeWithLoan->first_name);
        $response->assertSee('option value="' . $this->inactiveEmployeeWithLoan->id . '"', false);
    }

    public function test_api_employee_search_returns_employees_with_loans(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/employees/search?company_id=' . $this->company->id . '&q=InactiveWithLoan');

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'id' => $this->inactiveEmployeeWithLoan->id,
        ]);
    }

    public function test_loan_ledger_report_pdf_generation_succeeds(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('reports.loan-ledger'), [
                'company_id' => $this->company->id,
                'employee_id' => $this->inactiveEmployeeWithLoan->id,
                'loan_type' => 'all',
            ]);

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
    }
}
