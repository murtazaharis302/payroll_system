<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\DeductionType;
use App\Models\EmployeeMaster;
use App\Models\EmpLoan;
use App\Models\LoanPayment;
use App\Models\LoanType;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashLoanFormCalculationAndPrintTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected User $admin;
    protected EmployeeMaster $employee;
    protected DeductionType $deductionType;
    protected LoanType $loanType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name'     => 'Company Admin',
            'email'    => 'admin@cashform.test',
            'password' => bcrypt('password'),
            'role_id'  => Role::COMPANY_ADMIN,
            'role'     => 'company',
            'status'   => 1,
        ]);

        $this->company = Company::create([
            'name'         => 'Acme Financial Corp',
            'email'        => 'acme@cashform.test',
            'user_id'      => $this->admin->id,
            'company_code' => 'ACME01',
        ]);

        $this->admin->update(['company_id' => $this->company->id]);

        $this->employee = EmployeeMaster::create([
            'company_id'    => $this->company->id,
            'employee_code' => 'EMP-CF-01',
            'first_name'    => 'John',
            'last_name'     => 'Doe',
            'status'        => 'active',
        ]);

        $this->deductionType = DeductionType::withoutGlobalScopes()->where('code', 'VALE')->first();
        if (!$this->deductionType) {
            $this->deductionType = DeductionType::create([
                'company_id'      => $this->company->id,
                'name'            => 'Cash Advance',
                'code'            => 'VALE',
                'type'            => 'loan',
                'is_active'       => true,
                'is_loan_enabled' => true,
            ]);
        } else {
            $this->deductionType->update(['is_loan_enabled' => true]);
        }

        $this->loanType = LoanType::withoutGlobalScopes()->create([
            'company_id'        => $this->company->id,
            'name'              => 'Cash Loan',
            'deduction_type_id' => $this->deductionType->id,
        ]);
    }

    public function test_store_loan_calculates_interest_total_and_per_cutoff_amortization(): void
    {
        $response = $this->actingAs($this->admin)->post(route('loan-monitor.store-loan'), [
            'employee_id'           => $this->employee->id,
            'loan_type_id'          => $this->loanType->id,
            'principal_amount'      => 10000.00,
            'terms_months'          => 6,
            'monthly_interest_rate' => 1.00, // 1% per month
            'start_date'            => '2026-08-01',
            'purpose'               => 'Medical Expenses',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('statusSuccess');

        $this->assertDatabaseHas('emp_loans', [
            'employee_id'           => $this->employee->id,
            'principal_amount'      => 10000.00,
            'terms_months'          => 6,
            'monthly_interest_rate' => 1.00,
            'interest_amount'       => 600.00,  // 10000 * 0.01 * 6
            'total_amount'          => 10600.00, // 10000 + 600
            'per_cutoff_amount'     => 883.33,  // 10600 / (6 * 2) = 883.3333 -> 883.33
            'current_balance'       => 10600.00,
            'purpose'               => 'Medical Expenses',
        ]);
    }

    public function test_store_loan_requires_all_cash_loan_form_fields_in_dedicated_add_loan_error_bag(): void
    {
        $response = $this->actingAs($this->admin)->post(route('loan-monitor.store-loan'), [
            'employee_id'      => $this->employee->id,
            'loan_type_id'     => $this->loanType->id,
            'principal_amount' => 10000.00,
        ]);

        $response->assertSessionHasErrors(['start_date', 'purpose', 'terms_months', 'monthly_interest_rate'], errorBag: 'addLoan');
    }

    public function test_missing_purpose_preserves_all_input_values_in_session_and_old_helper(): void
    {
        $payload = [
            'employee_id'           => $this->employee->id,
            'loan_type_id'          => $this->loanType->id,
            'principal_amount'      => '15000.00',
            'terms_months'          => '3',
            'monthly_interest_rate' => '1.50',
            'start_date'            => '2026-08-15',
            'purpose'               => '', // Missing purpose
        ];

        $response = $this->actingAs($this->admin)->post(route('loan-monitor.store-loan'), $payload);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['purpose'], errorBag: 'addLoan');
        $response->assertSessionHas('_old_input');

        // Check that old values are preserved
        $session = session()->all();
        $this->assertEquals($this->employee->id, $session['_old_input']['employee_id']);
        $this->assertEquals($this->loanType->id, $session['_old_input']['loan_type_id']);
        $this->assertEquals('15000.00', $session['_old_input']['principal_amount']);
        $this->assertEquals('3', $session['_old_input']['terms_months']);
        $this->assertEquals('1.50', $session['_old_input']['monthly_interest_rate']);
        $this->assertEquals('2026-08-15', $session['_old_input']['start_date']);
    }

    public function test_payment_validation_error_does_not_populate_add_loan_error_bag(): void
    {
        $response = $this->actingAs($this->admin)->post(route('loan-monitor.record-payment'), [
            'emp_loan_id' => 999999, // Invalid
        ]);

        $response->assertSessionHasErrors(['emp_loan_id', 'amount_paid', 'payment_date']);
        $errors = session('errors');
        $this->assertFalse($errors->getBag('addLoan')->any());
    }

    public function test_updating_principal_recalculates_loan_values_using_stored_rate_and_terms(): void
    {
        $loan = EmpLoan::create([
            'employee_id'           => $this->employee->id,
            'loan_type_id'          => $this->loanType->id,
            'principal_amount'      => 10000.00,
            'terms_months'          => 6,
            'monthly_interest_rate' => 1.00,
            'interest_amount'       => 600.00,
            'total_amount'          => 10600.00,
            'per_cutoff_amount'     => 883.33,
            'current_balance'       => 10600.00,
            'status'                => 'ACTIVE',
        ]);

        // Edit principal from 10000 to 12000
        $response = $this->actingAs($this->admin)->post(route('loan-monitor.update-loan', $loan->id), [
            'principal_amount' => 12000.00,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('statusSuccess');

        $freshLoan = $loan->fresh();
        $this->assertEquals(12000.00, (float) $freshLoan->principal_amount);
        $this->assertEquals(720.00, (float) $freshLoan->interest_amount);  // 12000 * 0.01 * 6
        $this->assertEquals(12720.00, (float) $freshLoan->total_amount);   // 12000 + 720
        $this->assertEquals(1060.00, (float) $freshLoan->per_cutoff_amount); // 12720 / 12
        $this->assertEquals(12720.00, (float) $freshLoan->current_balance);
    }

    public function test_updating_principal_with_existing_payment_preserves_paid_amount_in_balance(): void
    {
        $loan = EmpLoan::create([
            'employee_id'           => $this->employee->id,
            'loan_type_id'          => $this->loanType->id,
            'principal_amount'      => 10000.00,
            'terms_months'          => 6,
            'monthly_interest_rate' => 1.00,
            'interest_amount'       => 600.00,
            'total_amount'          => 10600.00,
            'per_cutoff_amount'     => 883.33,
            'current_balance'       => 10600.00,
            'status'                => 'ACTIVE',
        ]);

        // Record manual payment of 1500.00
        LoanPayment::create([
            'emp_loan_id'  => $loan->id,
            'amount_paid'  => 1500.00,
            'payment_date' => '2026-08-05',
            'notes'        => 'Manual Payment',
            'source'       => 'MANUAL',
        ]);
        $loan->recalculateBalance();

        $this->assertEquals(9100.00, (float) $loan->fresh()->current_balance); // 10600 - 1500

        // Update principal from 10000 to 12000
        $response = $this->actingAs($this->admin)->post(route('loan-monitor.update-loan', $loan->id), [
            'principal_amount' => 12000.00,
        ]);

        $response->assertRedirect();

        $freshLoan = $loan->fresh();
        $this->assertEquals(12720.00, (float) $freshLoan->total_amount);
        $this->assertEquals(1500.00, (float) $freshLoan->amount_paid);
        $this->assertEquals(11220.00, (float) $freshLoan->current_balance); // 12720 - 1500
    }

    public function test_print_loan_form_renders_compact_form_without_interest_rows_and_with_2x2_signatures(): void
    {
        $loan = EmpLoan::create([
            'employee_id'           => $this->employee->id,
            'loan_type_id'          => $this->loanType->id,
            'principal_amount'      => 10000.00,
            'terms_months'          => 6,
            'monthly_interest_rate' => 1.00,
            'interest_amount'       => 600.00,
            'total_amount'          => 10600.00,
            'per_cutoff_amount'     => 883.33,
            'current_balance'       => 10600.00,
            'purpose'               => 'Tuition Fee',
            'start_date'            => '2026-08-01',
            'status'                => 'ACTIVE',
        ]);

        $response = $this->actingAs($this->admin)->get(route('loan-monitor.print-form', $loan->id));

        $response->assertStatus(200);
        $content = $response->getContent();

        // Check page margins and verify single source of physical print spacing
        $this->assertStringContainsString('margin: 0;', $content);
        $this->assertStringContainsString('size: A4 portrait;', $content);
        $this->assertStringContainsString('padding: 10mm 12mm !important;', $content);

        // Check header and core loan details
        $this->assertStringContainsString('Cash Loan Application & Summary', $content);
        $this->assertStringContainsString('John Doe', $content);
        $this->assertStringContainsString('Tuition Fee', $content);
        $this->assertStringContainsString('10,000.00', $content);
        $this->assertStringContainsString('10,600.00', $content);
        $this->assertStringContainsString('883.33', $content);

        // Assert removed interest rows from the Part 1 table
        $this->assertStringNotContainsString('<th>Monthly Interest Rate</th>', $content);
        $this->assertStringNotContainsString('<th>Computed Interest</th>', $content);

        // Assert updated promise text
        $this->assertStringContainsString('PROMISE TO PAY', $content);
        $this->assertStringContainsString('For value received ₱10,000.00, I, <strong>John Doe</strong>, promise to pay every payday the amount of ₱883.33 twice a month until the loan is settled.', $content);
        $this->assertStringContainsString('Any default in payment will cause an additional penalty charge of 1% per month on the principal amount.', $content);
        $this->assertStringNotContainsString('including computed interest', $content);

        // Assert 4 signature labels in 2x2 grid
        $this->assertStringContainsString('Prepared by', $content);
        $this->assertStringContainsString('Signature', $content);
        $this->assertStringContainsString('Approved by', $content);
        $this->assertStringContainsString('Checked by', $content);

        // Assert old signatures are gone
        $this->assertStringNotContainsString('Borrower Signature', $content);
        $this->assertStringNotContainsString('Authorized Representative', $content);

        // Assert Download Excel button present
        $this->assertStringContainsString('Download Excel', $content);
    }

    public function test_print_loan_form_enforces_multitenancy_company_scoping(): void
    {
        $adminOther = User::create([
            'name'     => 'Other Admin',
            'email'    => 'other@cashform.test',
            'password' => bcrypt('password'),
            'role_id'  => Role::COMPANY_ADMIN,
            'role'     => 'company',
            'status'   => 1,
        ]);

        $companyOther = Company::create([
            'name'         => 'Other Corp',
            'email'        => 'other@corp.test',
            'user_id'      => $adminOther->id,
            'company_code' => 'OTHER01',
        ]);

        $adminOther->update(['company_id' => $companyOther->id]);

        $loan = EmpLoan::create([
            'employee_id'      => $this->employee->id,
            'loan_type_id'     => $this->loanType->id,
            'principal_amount' => 5000.00,
            'total_amount'     => 5000.00,
            'current_balance'  => 5000.00,
            'status'           => 'ACTIVE',
        ]);

        // Attempting to print loan from Company A as Admin of Company B
        $response = $this->actingAs($adminOther)->get(route('loan-monitor.print-form', $loan->id));
        $this->assertTrue(in_array($response->status(), [403, 404], true));
    }

    public function test_export_excel_endpoint_downloads_identical_structure_and_signatures(): void
    {
        $loan = EmpLoan::create([
            'employee_id'           => $this->employee->id,
            'loan_type_id'          => $this->loanType->id,
            'principal_amount'      => 10000.00,
            'terms_months'          => 6,
            'monthly_interest_rate' => 1.00,
            'interest_amount'       => 600.00,
            'total_amount'          => 10600.00,
            'per_cutoff_amount'     => 883.33,
            'current_balance'       => 10600.00,
            'purpose'               => 'Medical Emergency',
            'start_date'            => '2026-08-01',
            'status'                => 'ACTIVE',
        ]);

        $response = $this->actingAs($this->admin)->get(route('loan-monitor.export-excel', $loan->id));

        $response->assertStatus(200);
        $this->assertTrue(
            str_contains((string) $response->headers->get('content-disposition'), 'Loan_Form_LN_') ||
            str_contains((string) $response->headers->get('content-type'), 'spreadsheet')
        );

        // Verify LoanFormExport contents directly
        $export = new \App\Exports\LoanFormExport($loan);
        $data = $export->array();

        $flattened = collect($data)->flatten()->implode(' ');
        $this->assertStringContainsString('CASH LOAN APPLICATION & SUMMARY', $flattened);
        $this->assertStringContainsString('John Doe', $flattened);
        $this->assertStringContainsString('Medical Emergency', $flattened);
        $this->assertStringContainsString('10,000.00', $flattened);
        $this->assertStringContainsString('10,600.00', $flattened);
        $this->assertStringContainsString('883.33', $flattened);
        $this->assertStringContainsString('For value received ₱10,000.00, I, John Doe, promise to pay every payday the amount of ₱883.33 twice a month until the loan is settled.', $flattened);
        $this->assertStringContainsString('Prepared by', $flattened);
        $this->assertStringContainsString('Signature', $flattened);
        $this->assertStringContainsString('Approved by', $flattened);
        $this->assertStringContainsString('Checked by', $flattened);
    }

    public function test_export_excel_enforces_multitenancy_company_scoping(): void
    {
        $adminOther = User::create([
            'name'     => 'Other Admin 2',
            'email'    => 'other2@cashform.test',
            'password' => bcrypt('password'),
            'role_id'  => Role::COMPANY_ADMIN,
            'role'     => 'company',
            'status'   => 1,
        ]);

        $companyOther = Company::create([
            'name'         => 'Other Corp 2',
            'email'        => 'other2@corp.test',
            'user_id'      => $adminOther->id,
            'company_code' => 'OTHER02',
        ]);

        $adminOther->update(['company_id' => $companyOther->id]);

        $loan = EmpLoan::create([
            'employee_id'      => $this->employee->id,
            'loan_type_id'     => $this->loanType->id,
            'principal_amount' => 5000.00,
            'total_amount'     => 5000.00,
            'current_balance'  => 5000.00,
            'status'           => 'ACTIVE',
        ]);

        $response = $this->actingAs($adminOther)->get(route('loan-monitor.export-excel', $loan->id));
        $this->assertTrue(in_array($response->status(), [403, 404], true));
    }
}
