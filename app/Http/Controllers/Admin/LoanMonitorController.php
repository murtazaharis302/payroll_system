<?php

namespace App\Http\Controllers\Admin;

use App\Exports\LoanFormExport;
use App\Http\Controllers\Admin\Concerns\LoanMonitorPaymentActions;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\DeductionType;
use App\Models\EmployeeMaster;
use App\Models\EmpLoan;
use App\Models\LoanType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class LoanMonitorController extends Controller
{
    use LoanMonitorPaymentActions;

    private function companyId(): ?int
    {
        $id = getActiveCompanyId();
        return $id ? (int) $id : null;
    }

    // ─── 1. Main Monitor Page ────────────────────────────────
    public function index()
    {
        $companyId = $this->companyId();
        $employees = EmployeeMaster::where('status', 'active')
            ->when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->orderBy('first_name')
            ->get();

        $activeLoanEmployees = EmployeeMaster::where('status', 'active')
            ->when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->whereHas('loans', fn(Builder $q) => $q->where('current_balance', '>', 0))
            ->orderBy('first_name')
            ->get();

        $loanQuery = EmpLoan::query()
            ->when($companyId, fn($q) => $q->whereHas('employee', fn(Builder $eq) => $eq->where('company_id', $companyId)));

        $stats = [
            'total_loans'     => (clone $loanQuery)->count(),
            'active_loans'    => (clone $loanQuery)->where('current_balance', '>', 0)->count(),
            'total_principal' => (clone $loanQuery)->sum('principal_amount'),
            'total_paid'      => (clone $loanQuery)->get()->sum(fn($l) => (float) $l->payments()->sum('amount_paid')),
            'total_balance'   => (clone $loanQuery)->sum('current_balance'),
        ];

        $loans = (clone $loanQuery)->with(['loanType', 'employee', 'payments'])->get();
        
        $loanTypesQuery = LoanType::withoutGlobalScopes()->with('deductionType');
        if ($companyId) {
            $loanTypesQuery->where(function ($q) use ($companyId) {
                $q->where('company_id', $companyId)->orWhereNull('company_id');
            });
        }
        $loanTypes = $loanTypesQuery->orderBy('name')->get();
        if ($loanTypes->isEmpty()) {
            $loanTypes = $this->ensureDefaultLoanTypes($companyId ?? Company::first()?->id);
        }

        return view('admin.loan_monitor.index', compact('employees', 'activeLoanEmployees', 'stats', 'loans', 'loanTypes'));
    }

    private function ensureDefaultLoanTypes(?int $targetCompanyId)
    {
        if (!$targetCompanyId) {
            return collect();
        }
        $defaultsMap = [
            'Cash Advance / Vale' => 'VALE',
            'SSS Loan' => 'SSS_LOAN',
            'Pag-IBIG Loan' => 'PAGIBIG_LOAN',
            'Goods Purchase / Merchandise' => 'GOODS',
            'Salary Loan' => 'LOAN',
            'Emergency Loan' => 'OTHER',
        ];
        foreach ($defaultsMap as $typeName => $code) {
            $dt = DeductionType::withoutGlobalScopes()->where('code', $code)->first();
            if (!$dt) {
                $dt = DeductionType::create([
                    'company_id' => $targetCompanyId,
                    'name' => $typeName,
                    'code' => $code,
                    'type' => 'loan',
                    'is_active' => true,
                    'is_loan_enabled' => true,
                ]);
            } else {
                $dt->update(['is_loan_enabled' => true]);
            }

            LoanType::firstOrCreate(
                ['company_id' => $targetCompanyId, 'deduction_type_id' => $dt->id],
                ['name' => $typeName, 'is_active' => true]
            );
        }
        return LoanType::withoutGlobalScopes()->with('deductionType')->orderBy('name')->get();
    }

    // ─── 2. AJAX: Get Employee Loans ─────────────────────────
    public function getEmployeeLoans(Request $request, $employeeId)
    {
        $companyId = $this->companyId();
        $employee = EmployeeMaster::find($employeeId);
        
        if (!$employee) {
            return response()->json(['success' => false, 'message' => 'Employee not found.'], 404);
        }

        if ($companyId && (int) $employee->company_id !== (int) $companyId) {
            return response()->json(['success' => false, 'message' => 'Unauthorized access to employee data.'], 403);
        }

        $loans = collect();

        $empLoanQuery = EmpLoan::with(['loanType', 'employee'])
            ->where('employee_id', $employeeId);

        if ($companyId) {
            $empLoanQuery->whereHas('employee', function (Builder $query) use ($companyId) {
                $query->where('company_id', $companyId);
            });
        }

        $empLoanQuery->get()->each(function ($l) use ($loans) {
            $principal = (float) $l->principal_amount;
            $totalAmount = (float) ($l->total_amount ?? $principal);
            $balance = (float) $l->current_balance;
            $paid = (float) $l->payments()->sum('amount_paid');
            $pct = $totalAmount > 0 ? min(100.0, max(0.0, round(($paid / $totalAmount) * 100, 1))) : 0;

            $termsMonths = (int) ($l->terms_months ?? 1);
            $totalInstallments = max(1, $termsMonths * 2);

            $paymentCount = $l->payments()->count();
            if ($balance <= 0) {
                $paidInstallments = $totalInstallments;
                $remainingInstallments = 0;
            } else {
                $paidInstallments = min($totalInstallments, $paymentCount);
                $remainingInstallments = max(0, $totalInstallments - $paidInstallments);
            }

            $loans->push([
                'id'                     => $l->id,
                'type'                   => 'emp_loan',
                'loan_type'              => $l->loanType ? $l->loanType->name : 'Company Loan',
                'loan_type_code'         => $l->loanType ? strtolower(str_replace(' ', '_', $l->loanType->name)) : 'loan',
                'loan_number'            => 'LN-' . str_pad((string) $l->id, 5, '0', STR_PAD_LEFT),
                'description'            => $l->loanType ? $l->loanType->name : 'Unified Loan',
                'principal_amount'       => $principal,
                'interest_amount'        => (float) ($l->interest_amount ?? 0.00),
                'total_amount'           => $totalAmount,
                'monthly_interest_rate'  => (float) ($l->monthly_interest_rate ?? 0.00),
                'terms_months'           => $termsMonths,
                'purpose'                => $l->purpose,
                'amount_paid'            => $paid,
                'balance'                => $balance,
                'installment_amount'     => (float) $l->per_cutoff_amount,
                'total_installments'     => $totalInstallments,
                'remaining_installments' => $remainingInstallments,
                'paid_installments'      => $paidInstallments,
                'progress_percent'       => $pct,
                'start_date'             => $l->start_date ? \Carbon\Carbon::parse($l->start_date)->format('M d, Y') : '—',
                'end_date'               => '—',
                'status'                 => $balance > 0 ? 'active' : 'completed',
                'edit_url'               => '#',
                'print_url'              => panelRoute('loan-monitor.print-form', $l->id),
                'excel_url'              => panelRoute('loan-monitor.export-excel', $l->id),
            ]);
        });

        return response()->json([
            'success'  => true,
            'employee' => $employee->full_name . ' (' . $employee->employee_code . ')',
            'loans'    => $loans,
            'summary'  => [
                'total'           => $loans->count(),
                'active'          => $loans->where('status', 'active')->count(),
                'total_principal' => $loans->sum('principal_amount'),
                'total_paid'      => $loans->sum('amount_paid'),
                'total_balance'   => $loans->sum('balance'),
            ],
        ]);
    }

    // ─── 3. Store a New Loan ─────────────────────────────────
    public function storeLoan(Request $request)
    {
        $request->validateWithBag('addLoan', [
            'employee_id'           => 'required|exists:employees_master,id',
            'loan_type_id'          => 'required|exists:loan_types,id',
            'principal_amount'      => 'required|numeric|min:0.01',
            'start_date'            => 'required|date',
            'purpose'               => 'required|string|max:255',
            'terms_months'          => 'required|integer|min:1',
            'monthly_interest_rate' => 'required|numeric|min:0',
        ]);

        $companyId = $this->companyId();
        $employee = EmployeeMaster::findOrFail($request->employee_id);
        if ($companyId && (int) $employee->company_id !== (int) $companyId) {
            abort(403, 'Unauthorized access to this employee record.');
        }

        $loanType = LoanType::with('deductionType')->findOrFail($request->loan_type_id);
        if ($companyId && $loanType->company_id && (int) $loanType->company_id !== (int) $companyId) {
            abort(403, 'Unauthorized access to this loan type.');
        }
        if (!$loanType->deductionType || !$loanType->deductionType->is_loan_enabled) {
            throw ValidationException::withMessages([
                'loan_type_id' => ['The selected loan configuration is not linked to a loan-enabled Deduction Type.'],
            ])->errorBag('addLoan');
        }

        $principal = (float) $request->principal_amount;
        $termsMonths = (int) $request->terms_months;
        $monthlyRate = (float) $request->monthly_interest_rate;

        [$termsMonths, $interestAmount, $totalAmount, $perCutoffAmount] = $this->calculateLoanTerms($principal, $termsMonths, $monthlyRate);

        $loan = EmpLoan::create([
            'employee_id'           => $request->employee_id,
            'loan_type_id'          => $request->loan_type_id,
            'principal_amount'      => $principal,
            'interest_amount'       => $interestAmount,
            'total_amount'          => $totalAmount,
            'per_cutoff_amount'     => $perCutoffAmount,
            'current_balance'       => $totalAmount,
            'amount_paid'           => 0.00,
            'status'                => 'ACTIVE',
            'start_date'            => $request->start_date,
            'purpose'               => $request->purpose,
            'terms_months'          => $termsMonths,
            'monthly_interest_rate' => $monthlyRate,
        ]);

        $printUrl = panelRoute('loan-monitor.print-form', $loan->id);

        return back()
            ->with('statusSuccess', 'New cash loan added successfully.')
            ->with('loanPrintUrl', $printUrl);
    }

    private function calculateLoanTerms(float $principal, int $termsMonths, float $monthlyRate): array
    {
        $termsMonths = max(1, $termsMonths);
        $interestAmount = round($principal * ($monthlyRate / 100.00) * $termsMonths, 2);
        $totalAmount = round($principal + $interestAmount, 2);
        $perCutoffAmount = round($totalAmount / ($termsMonths * 2), 2);

        return [$termsMonths, $interestAmount, $totalAmount, $perCutoffAmount];
    }

    // ─── 4. Print Loan Form View ───────────────────────────────
    public function printForm(Request $request, $id)
    {
        $companyId = $this->companyId();
        $loan = EmpLoan::with(['employee.company', 'loanType'])->findOrFail($id);

        if ($companyId && (int) $loan->employee->company_id !== (int) $companyId) {
            abort(403, 'Unauthorized access to this loan record.');
        }

        return view('admin.loan_monitor.print_form', compact('loan'));
    }

    // ─── 5. Export Loan Form Excel ──────────────────────────────
    public function exportExcel(Request $request, $id)
    {
        $companyId = $this->companyId();
        $loan = EmpLoan::with(['employee.company', 'loanType'])->findOrFail($id);

        if ($companyId && (int) $loan->employee->company_id !== (int) $companyId) {
            abort(403, 'Unauthorized access to this loan record.');
        }

        $fileName = 'Loan_Form_LN_' . str_pad((string) $loan->id, 5, '0', STR_PAD_LEFT) . '.xlsx';

        return Excel::download(new LoanFormExport($loan), $fileName);
    }
}
