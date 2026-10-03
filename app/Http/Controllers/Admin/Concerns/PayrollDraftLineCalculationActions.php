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

trait PayrollDraftLineCalculationActions
{
    public function addDeduction(Request $request, $id)
    {
        $currentUser = auth()->user();
        $hasDeductionInputPermission = MenuRoleAccess::hasModuleAccess($currentUser, 'payroll_deduction_inputs');

        if (!$hasDeductionInputPermission) {
            abort(403, 'User is restricted from adding payroll deduction lines without authorization.');
        }

        $validated = $request->validate([
            'deduction_type_id' => 'required|integer',
            'loan_id' => 'nullable|integer',
            'number_input' => 'nullable|numeric|min:0',
            'rate' => 'nullable|numeric|min:0',
        ]);

        $draft = DB::transaction(function () use ($id, $validated) {
            $draft = $this->lockEditableDraftForLineAddition((int) $id);
            $companyId = (int) $draft->employee->company_id;
            $deductionType = DeductionType::withoutGlobalScopes()
                ->forCompany($companyId)
                ->where('is_active', true)
                ->find($validated['deduction_type_id']);

            if (!$deductionType) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'deduction_type_id' => ['The selected deduction type is not available for this company.'],
                ]);
            }

            $loan = null;
            if (!empty($validated['loan_id'])) {
                $loan = EmpLoan::withoutGlobalScopes()
                    ->with('loanType')
                    ->where('id', $validated['loan_id'])
                    ->where('employee_id', $draft->employee_id)
                    ->whereIn('status', ['ACTIVE', 'active'])
                    ->where('current_balance', '>', 0)
                    ->whereHas('loanType', fn ($query) => $query->where('is_active', true))
                    ->first();

                if (!$loan || (int) ($loan->loanType?->deduction_type_id ?? 0) !== (int) $deductionType->id) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'loan_id' => ['The selected active loan does not match this payroll deduction.'],
                    ]);
                }

                if (PayOut::withoutGlobalScopes()->where('pay_id', $draft->id)->where('loan_id', $loan->id)->exists()) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'loan_id' => ['This loan already has a deduction line on the payroll draft.'],
                    ]);
                }
            } else {
                $requiresLoanSelection = EmpLoan::withoutGlobalScopes()
                    ->where('employee_id', $draft->employee_id)
                    ->whereIn('status', ['ACTIVE', 'active'])
                    ->where('current_balance', '>', 0)
                    ->whereHas('loanType', fn ($query) => $query
                        ->where('deduction_type_id', $deductionType->id)
                        ->where('is_active', true))
                    ->exists();

                if ($requiresLoanSelection) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'loan_id' => ['Select the specific active loan for this payroll deduction.'],
                    ]);
                }

                if (PayOut::withoutGlobalScopes()
                    ->where('pay_id', $draft->id)
                    ->where('deduction_type_id', $deductionType->id)
                    ->exists()) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'deduction_type_id' => ['This deduction type already exists on the payroll draft.'],
                    ]);
                }
            }

            $line = app(PayrollProcessingService::class)->resolveAddedDraftDeductionLine(
                $draft,
                $deductionType,
                $loan,
                (array_key_exists('number_input', $validated) && $validated['number_input'] !== null) ? (float) $validated['number_input'] : null,
                $validated['rate'] ?? null
            );

            PayOut::create([
                'pay_id' => $draft->id,
                'deduction_type_id' => $deductionType->id,
                'loan_id' => $loan?->id,
                'number_input' => $line['number_input'],
                'rate' => $line['rate'],
            ]);

            $draft->recalculateTotals();
            ActivityLog::log('add_deduction_line', 'payroll_draft', "Added {$deductionType->name} to payroll draft #{$draft->id}");

            return $draft;
        });

        if ($request->wantsJson()) {
            return response()->json(['status' => 'success', 'draft_id' => $draft->id], 201);
        }

        return redirect()->route('payroll-drafts.edit', ['id' => $draft->id, 'payroll_period_id' => $draft->payroll_period_id])
            ->with('status', 'Deduction line added to the payroll draft.');
    }

    protected function lockEditableDraftForLineAddition(int $id): PayHeader
    {
        $draft = PayHeader::withoutGlobalScopes()
            ->with(['employee', 'payrollPeriod'])
            ->lockForUpdate()
            ->findOrFail($id);
        $companyId = $this->getCompanyId();

        if ($companyId && ((int) $draft->employee?->company_id !== (int) $companyId
            || ($draft->company_id && (int) $draft->company_id !== (int) $companyId))) {
            abort(403, 'Unauthorized access to this payroll draft.');
        }

        if (!$draft->canBeEditedBy(auth()->user())) {
            abort(422, 'This payroll record is locked and cannot be edited.');
        }

        return $draft;
    }

    protected function statutoryPeriodFactor(?string $frequency): float
    {
        return match ($frequency) {
            'monthly' => 1.0,
            'weekly' => 4.0,
            'bi_weekly' => 2.0,
            default => 2.0,
        };
    }

    /**
     * Update the payroll draft (HR saves their input).
     */
}
