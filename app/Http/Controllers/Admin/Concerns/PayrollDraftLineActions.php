<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Http\Controllers\Controller;
use App\Models\PayHeader;
use App\Models\PayIn;
use App\Models\PayOut;
use App\Models\EmployeeMaster;
use App\Models\PayrollPeriod;
use App\Models\EmpIncome;
use App\Models\EmpDeduction;
use App\Models\EmpLoan;
use App\Models\AttendanceLog;
use App\Models\ActivityLog;
use App\Models\IncomeType;
use App\Models\DeductionType;
use App\Models\PayrollPolicy;
use App\Models\MenuRoleAccess;
use App\Models\Role;
use App\Services\PayrollProcessingService;
use App\Services\PayrollLineCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Http\Requests\AddPayrollDraftEmployeeRequest;
use App\Http\Requests\StorePayrollDraftRequest;
use Carbon\Carbon;

trait PayrollDraftLineActions
{
    /**
     * Show the form for editing a specific payroll draft (HR input).
     */
    public function edit($id)
    {
        $currentUser = auth()->user();
        $draft = PayHeader::withoutGlobalScopes()->with([
            'employee',
            'payrollPeriod',
        ])->findOrFail($id);

        $companyId = $this->getCompanyId();
        if ($companyId && $draft->employee && (int) $draft->employee->company_id !== (int) $companyId) {
            abort(403, 'Unauthorized access to this payroll draft.');
        }
        if ($companyId && $draft->company_id && (int) $draft->company_id !== (int) $companyId) {
            abort(403, 'Unauthorized access to this payroll draft.');
        }


        $employee = $draft->employee;
        $period = $draft->payrollPeriod;

        if (!$employee) {
            return redirect()->route('payroll-drafts.index')
                ->with('statusDanger', 'The employee record associated with this payroll draft no longer exists or was purged.');
        }

        if (!$period) {
            return redirect()->route('payroll-drafts.index')
                ->with('statusDanger', 'The payroll period associated with this payroll draft no longer exists.');
        }

        // Get attendance data for the period
        $attendanceLogs = AttendanceLog::where('employee_id', $employee->id)
            ->whereBetween('log_date', [$period->period_start, $period->period_end])
            ->orderBy('log_date')
            ->get();

        // Get employee incomes
        $empIncomes = EmpIncome::with('incomeType')
            ->where('employee_id', $employee->id)
            ->where('is_active', true)
            ->get();

        // Get employee deductions active during this payroll period
        $empDeductions = EmpDeduction::with('deductionType')
            ->where('employee_id', $employee->id)
            ->where('is_active', true)
            ->where(function($query) use ($period) {
                $query->whereNull('start_date')
                      ->orWhere('start_date', '<=', $period->period_end);
            })
            ->where(function($query) use ($period) {
                $query->whereNull('end_date')
                      ->orWhere('end_date', '>=', $period->period_start);
            })
            ->get();

        // Helper to query loans by mapped deduction type codes
        $filterLoansByDeductionCodes = function (array $codes) use ($employee) {
            return EmpLoan::with(['loanType.deductionType'])
                ->where('employee_id', $employee->id)
                ->where('current_balance', '>', 0)
                ->whereHas('loanType.deductionType', function ($q) use ($codes) {
                    $q->withoutGlobalScopes()->whereIn('code', $codes);
                })
                ->get();
        };

        // Get active vale/loans and goods purchases
        $valeLoans = $filterLoansByDeductionCodes(['VALE', 'CASH_ADVANCE']);
        $goodsPurchases = $filterLoansByDeductionCodes(['GOODS', 'MERCHANDISE']);

        // Get remaining active loans
        $excludedLoanIds = $valeLoans->pluck('id')->merge($goodsPurchases->pluck('id'))->filter()->unique();
        $governmentLoansQuery = EmpLoan::with(['loanType.deductionType'])->where('employee_id', $employee->id)
            ->where('current_balance', '>', 0);
        if ($excludedLoanIds->isNotEmpty()) {
            $governmentLoansQuery->whereNotIn('id', $excludedLoanIds);
        }
        $governmentLoans = $governmentLoansQuery->get();
        $activeCompanyId = $companyId ?? ($employee->company_id ?? getActiveCompanyId());

        $periodDrafts = PayHeader::withoutGlobalScopes()
            ->with(['employee'])
            ->where('payroll_period_id', $period->id)
            ->whereHas('employee', function($q) use ($activeCompanyId) {
                if ($activeCompanyId) {
                    $q->where('company_id', $activeCompanyId);
                }
            })
            ->get();

        $payIns = PayIn::with('incomeType')->where('pay_id', $draft->id)->get();
        $payOuts = PayOut::with('deductionType')->where('pay_id', $draft->id)->get();

        $allCompanyIncomeTypes = IncomeType::query()
            ->where('is_active', true)
            ->where(function ($query) use ($activeCompanyId) {
                $query->whereNull('company_id')
                      ->orWhere('company_id', $activeCompanyId);
            })
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        $allCompanyDeductionTypes = DeductionType::query()
            ->where('is_active', true)
            ->where(function ($query) use ($activeCompanyId) {
                $query->whereNull('company_id')
                      ->orWhere('company_id', $activeCompanyId);
            })
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'is_loan_enabled']);

        $policy = PayrollPolicy::forCompany($employee->company_id, (string) $period->period_start);
        $hasIncomeInputPermission = MenuRoleAccess::hasModuleAccess($currentUser, 'payroll_income_inputs');
        $hasIncomeRateVisibilityPermission = MenuRoleAccess::hasModuleAccess($currentUser, 'payroll_income_rate_visibility');
        $hasDeductionInputPermission = MenuRoleAccess::hasModuleAccess($currentUser, 'payroll_deduction_inputs');
        $isHr = ($currentUser->role === 'hr' || $currentUser->role_id === Role::HR);
        $existingIncomeTypeIds = $payIns->pluck('income_type_id')->filter()->all();
        $existingOrdinaryDeductionTypeIds = $payOuts->whereNull('loan_id')->pluck('deduction_type_id')->filter()->all();
        $existingLoanIds = $payOuts->pluck('loan_id')->filter()->all();

        $availableIncomeTypes = $allCompanyIncomeTypes
            ->reject(fn (IncomeType $type) => in_array($type->id, $existingIncomeTypeIds, true))
            ->values();
        $activeLoansForDraft = EmpLoan::withoutGlobalScopes()
            ->with(['loanType.deductionType'])
            ->where('employee_id', $employee->id)
            ->whereIn('status', ['ACTIVE', 'active'])
            ->where('current_balance', '>', 0)
            ->whereHas('loanType', fn ($query) => $query->where('is_active', true))
            ->get();
        $activeLoanDeductionTypeIds = $activeLoansForDraft
            ->map(fn (EmpLoan $loan) => $loan->loanType?->deduction_type_id)
            ->filter()
            ->unique()
            ->all();
        $availableLoanDeductions = $activeLoansForDraft
            ->filter(function (EmpLoan $loan) use ($existingLoanIds, $activeCompanyId) {
                $deductionType = $loan->loanType?->deductionType;

                return $deductionType
                    && ($deductionType->company_id === null || (int) $deductionType->company_id === (int) $activeCompanyId)
                    && (bool) $deductionType->is_active
                    && !in_array($loan->id, $existingLoanIds, true);
            })
            ->values();
        $loanDeductionTypeIds = $availableLoanDeductions
            ->map(fn (EmpLoan $loan) => $loan->loanType?->deduction_type_id)
            ->filter()
            ->unique()
            ->all();
        $availableDeductionTypes = $allCompanyDeductionTypes
            ->reject(fn (DeductionType $type) => in_array($type->id, $existingOrdinaryDeductionTypeIds, true)
                || in_array($type->id, $loanDeductionTypeIds, true)
                || in_array($type->id, $activeLoanDeductionTypeIds, true)
                || (bool) $type->is_loan_enabled)
            ->values();
        $canEditDraft = $draft->canBeEditedBy($currentUser);
        $canAddIncome = $canEditDraft && $hasIncomeInputPermission;
        $canAddDeduction = $canEditDraft && $hasDeductionInputPermission;
        $userRoleId     = (int) ($currentUser->role_id ?? 0);
        $isCompanyAdmin = $userRoleId === \App\Models\Role::COMPANY_ADMIN;
        $isSuperAdmin   = $userRoleId === \App\Models\Role::SUPER_ADMIN
            || strtolower((string) ($currentUser->role ?? '')) === 'admin';
        $canReopen = ($draft->status === 'approved') && (
            $isCompanyAdmin
            || $isSuperAdmin
            || MenuRoleAccess::hasModuleAccess($currentUser, 'payroll_locked_edit_override')
            || MenuRoleAccess::hasModuleAccess($currentUser, 'payroll_approvals')
        );

        return view('admin.payroll-drafts.edit', compact(
            'draft', 'employee', 'period', 'periodDrafts', 'payIns', 'payOuts', 'attendanceLogs',
            'empIncomes', 'empDeductions', 'valeLoans',
            'goodsPurchases', 'governmentLoans',
            'allCompanyIncomeTypes', 'allCompanyDeductionTypes',
            'policy', 'hasIncomeInputPermission', 'hasIncomeRateVisibilityPermission',
            'hasDeductionInputPermission', 'availableIncomeTypes', 'availableDeductionTypes',
            'availableLoanDeductions', 'canAddIncome', 'canAddDeduction', 'canEditDraft', 'canReopen'
        ));
    }

    /**
     * Add a draft-only income line without changing Employee Master assignments.
     */
    public function addIncome(Request $request, $id)
    {
        $currentUser = auth()->user();
        $hasIncomeInputPermission = MenuRoleAccess::hasModuleAccess($currentUser, 'payroll_income_inputs');
        $hasIncomeRateVisibilityPermission = MenuRoleAccess::hasModuleAccess($currentUser, 'payroll_income_rate_visibility');

        if (!$hasIncomeInputPermission) {
            abort(403, 'User is restricted from adding payroll income lines without authorization.');
        }

        if (!$hasIncomeRateVisibilityPermission && ($request->has('rate') || $request->has('amount'))) {
            abort(403, 'User is restricted from submitting income rates or monetary amounts.');
        }

        $validated = $request->validate([
            'income_type_id' => 'required|integer',
            'number_input' => 'required|numeric|min:0',
            'rate' => 'nullable|numeric|min:0',
        ]);

        $draft = DB::transaction(function () use ($id, $hasIncomeRateVisibilityPermission, $validated) {
            $draft = $this->lockEditableDraftForLineAddition((int) $id);
            $companyId = (int) $draft->employee->company_id;
            $incomeType = IncomeType::withoutGlobalScopes()
                ->forCompany($companyId)
                ->where('is_active', true)
                ->find($validated['income_type_id']);

            if (!$incomeType) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'income_type_id' => ['Invalid or inactive income type selected.'],
                ]);
            }

            if (PayIn::withoutGlobalScopes()
                ->where('pay_id', $draft->id)
                ->where('income_type_id', $incomeType->id)
                ->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'income_type_id' => ['This income type already exists on the payroll draft.'],
                ]);
            }

            $line = app(PayrollProcessingService::class)->resolveAddedIncomeLine(
                $draft,
                $incomeType,
                (float) $validated['number_input'],
                $hasIncomeRateVisibilityPermission ? ($validated['rate'] ?? null) : null
            );

            PayIn::create([
                'pay_id' => $draft->id,
                'income_type_id' => $incomeType->id,
                'number_input' => $line['number_input'],
                'rate' => $line['rate'],
            ]);

            // Match the normal income-edit path so withholding tax and other
            // income-dependent statutory deductions do not become stale.
            app(PayrollProcessingService::class)->recalculateDraftStatutoryDeductions($draft);
            $draft->recalculateTotals();
            ActivityLog::log('add_income_line', 'payroll_draft', "Added {$incomeType->name} to payroll draft #{$draft->id}");

            return $draft;
        });

        if ($request->wantsJson()) {
            return response()->json(['status' => 'success', 'draft_id' => $draft->id], 201);
        }

        return redirect()->route('payroll-drafts.edit', ['id' => $draft->id, 'payroll_period_id' => $draft->payroll_period_id])
            ->with('status', 'Income line added to the payroll draft.');
    }

    /**
     * Add a draft-only deduction line without changing Employee Master assignments.
     */
}
