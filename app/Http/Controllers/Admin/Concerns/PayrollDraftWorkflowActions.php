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

trait PayrollDraftWorkflowActions
{
    /**
     * Submit payroll draft for accounting approval.
     */
    public function submit($id)
    {
        $draft = PayHeader::withoutGlobalScopes()->findOrFail($id);

        $companyId = $this->getCompanyId();
        if ($companyId && $draft->employee && (int) $draft->employee->company_id !== (int) $companyId) {
            abort(403, 'Unauthorized access.');
        }

        if (!$draft->canSubmit()) {
            return back()->with('statusDanger', "Cannot submit payroll draft. Status is currently '{$draft->status}'.");
        }

        if ($draft->requires_review) {
            $empCode = $draft->employee->employee_code ?? 'EMP-' . $draft->employee_id;
            $empName = $draft->employee->full_name ?? 'Employee';
            $msg = "Cannot submit payroll draft. Employee {$empCode} - {$empName} requires review: " . ($draft->review_reason ?? 'Requires review before submission.');
            return back()->with('statusDanger', $msg);
        }

        if ((float) $draft->net_pay < 0) {
            $empCode = $draft->employee->employee_code ?? 'EMP-' . $draft->employee_id;
            $empName = $draft->employee->full_name ?? 'Employee';
            $isHrUser = Auth::user()->role === 'hr' || Auth::user()->role_id === \App\Models\Role::HR;
            $msg = $isHrUser
                ? "Cannot submit payroll draft. Employee {$empCode} - {$empName} has a negative net income. Please adjust attendance or deductions before submitting."
                : "Cannot submit payroll draft. Employee {$empCode} - {$empName} has a negative net income of ₱" . number_format((float) $draft->net_pay, 2) . ". Please adjust income or deductions before submitting.";
            return back()->with('statusDanger', $msg);
        }

        $workflowService = app(\App\Services\PayrollWorkflowService::class);
        $workflowService->transition($draft, \App\Services\PayrollWorkflowService::STATE_PENDING_APPROVAL, ['user' => Auth::user()]);

        ActivityLog::log(
            'submit',
            'payroll_draft',
            "Submitted payroll draft for approval",
            PayHeader::class,
            $draft->id
        );

        return back()->with('status', 'Payroll draft submitted for approval successfully.');
    }

    /**
     * Batch submit multiple drafts.
     */
    public function batchSubmit(Request $request)
    {
        $validated = $request->validate([
            'draft_ids' => 'required|array',
            'draft_ids.*' => 'exists:pay_headers,id',
        ]);

        $companyId = $this->getCompanyId();

        $isHrUser = Auth::user()->role === 'hr' || Auth::user()->role_id === \App\Models\Role::HR;
        foreach ($validated['draft_ids'] as $draftId) {
            $draft = PayHeader::find($draftId);
            if ($draft && (float) $draft->net_pay < 0) {
                $empCode = $draft->employee->employee_code ?? 'EMP-' . $draft->employee_id;
                $empName = $draft->employee->full_name ?? 'Employee';
                $negativeList[] = $isHrUser ? "{$empCode} - {$empName}" : "{$empCode} - {$empName} (₱" . number_format((float) $draft->net_pay, 2) . ")";
            }
        }

        if (!empty($negativeList)) {
            $listStr = implode(', ', $negativeList);
            return back()->with('statusDanger', "Cannot submit payroll drafts for approval. The following employee(s) have a negative net income: {$listStr}. Please adjust attendance or deductions before submitting.");
        }

        $submitted = 0;
        foreach ($validated['draft_ids'] as $draftId) {
            $draft = PayHeader::withoutGlobalScopes()->with('employee')->find($draftId);

            if ($draft) {
                $draftCompanyId = (int) ($draft->employee?->company_id ?? 0);
                if ($companyId && $draftCompanyId > 0 && $draftCompanyId !== (int) $companyId) {
                    ActivityLog::log(
                        'security_alert',
                        'payroll_draft',
                        "Unauthorized batch submit attempt on PayHeader ID {$draftId}",
                        PayHeader::class,
                        $draftId,
                        null,
                        null,
                        $draftCompanyId
                    );
                    continue;
                }

                if ($draft->canSubmit()) {
                    $workflowService = app(\App\Services\PayrollWorkflowService::class);
                    $workflowService->transition($draft, \App\Services\PayrollWorkflowService::STATE_PENDING_APPROVAL, ['user' => Auth::user()]);
                    $submitted++;
                }
            }
        }

        ActivityLog::log(
            'batch_submit',
            'payroll_draft',
            "Batch submitted {$submitted} payroll drafts for approval"
        );

        return back()->with('status', "Submitted {$submitted} payroll drafts for approval.");
    }

    /**
     * Delete a single created payroll draft.
     */
    public function destroy($id)
    {
        $draft = PayHeader::findOrFail($id);

        $this->authorize('delete', $draft);

        $rawStatus = $draft->getRawOriginal('status') ?? $draft->status;
        if (!in_array($rawStatus, ['draft', 'rejected'], true)) {
            return back()->with('statusDanger', 'Only draft or rejected payroll records can be deleted.');
        }

        DB::transaction(function () use ($draft) {
            PayIn::where('pay_id', $draft->id)->delete();
            PayOut::where('pay_id', $draft->id)->delete();
            \App\Models\GovernmentContribution::where('payroll_period_id', $draft->payroll_period_id)
                ->where('employee_id', $draft->employee_id)
                ->delete();

            ActivityLog::log(
                'delete',
                'payroll_draft',
                "Deleted payroll draft #{$draft->id} for employee #{$draft->employee_id}",
                PayHeader::class,
                $draft->id
            );

            $draft->delete();
        });

        return redirect()->route('payroll-drafts.index')
            ->with('status', 'Payroll draft deleted successfully.');
    }

    /**
     * Batch delete multiple payroll drafts.
     */
    public function batchDestroy(Request $request)
    {
        $validated = $request->validate([
            'draft_ids' => 'required|array',
            'draft_ids.*' => 'exists:pay_headers,id',
        ]);

        $companyId = $this->getCompanyId();
        $deletedCount = 0;

        DB::transaction(function () use ($validated, $companyId, &$deletedCount) {
            foreach ($validated['draft_ids'] as $draftId) {
                $draft = PayHeader::withoutGlobalScopes()->with('employee')->find($draftId);
                if (!$draft) continue;

                $draftCompanyId = (int) ($draft->employee?->company_id ?? 0);
                if ($companyId && $draftCompanyId > 0 && $draftCompanyId !== (int) $companyId) {
                    ActivityLog::log(
                        'security_alert',
                        'payroll_draft',
                        "Unauthorized batch delete attempt on PayHeader ID {$draftId}",
                        PayHeader::class,
                        $draftId,
                        null,
                        null,
                        $draftCompanyId
                    );
                    continue;
                }

                $rawStatus = $draft->getRawOriginal('status') ?? $draft->status;
                if (!in_array($rawStatus, ['draft', 'rejected'], true)) {
                    continue;
                }

                PayIn::where('pay_id', $draft->id)->delete();
                PayOut::where('pay_id', $draft->id)->delete();
                \App\Models\GovernmentContribution::where('payroll_period_id', $draft->payroll_period_id)
                    ->where('employee_id', $draft->employee_id)
                    ->delete();

                $draft->delete();
                $deletedCount++;
            }
        });

        ActivityLog::log(
            'batch_delete',
            'payroll_draft',
            "Batch deleted {$deletedCount} payroll draft(s)"
        );

        return redirect()->route('payroll-drafts.index')
            ->with('status', "Deleted {$deletedCount} payroll draft(s) successfully.");
    }

    /**
     * Save company-wide default deduction options (enforced across payroll runs).
     */
    public function saveCompanyDeductionDefaults(Request $request)
    {
        $currentUser = auth()->user();
        $isSuperAdmin = $currentUser->role === 'admin' || $currentUser->role_id === Role::SUPER_ADMIN;
        $isCompanyAdmin = in_array($currentUser->role, ['company', 'company_admin'], true)
            || $currentUser->role_id === Role::COMPANY_ADMIN;
        if (!$isSuperAdmin && !$isCompanyAdmin) {
            abort(403, 'Unauthorized to save company deduction defaults.');
        }

        $companyId = $request->input('company_id')
            ?? getActiveCompanyId()
            ?? $currentUser->company_id
            ?? session('active_company_id');

        if (!$companyId) {
            $firstCompany = \App\Models\Company::first();
            $companyId = $firstCompany ? $firstCompany->id : null;
        }

        if (!$isSuperAdmin && (int) $currentUser->company_id !== (int) $companyId) {
            abort(403, 'Unauthorized to save company deduction defaults for this company.');
        }

        if (!$companyId) {
            return back()->with('statusDanger', 'Unable to resolve target company for deduction defaults.');
        }

        $company = \App\Models\Company::find($companyId);
        if (!$company) {
            return back()->with('statusDanger', 'Target company not found.');
        }

        $validated = $request->validate([
            'use_fixed_amount' => 'nullable',
            'deductions' => 'nullable|array',
            'deductions.*.deduction_type' => 'required|string',
            'deductions.*.display' => 'nullable',
            'deductions.*.amount' => 'nullable|numeric|min:0',
        ]);

        $deductions = [];
        if (!empty($validated['deductions'])) {
            foreach ($validated['deductions'] as $item) {
                $deductions[] = [
                    'deduction_type' => (string) $item['deduction_type'],
                    'display' => isset($item['display']) && ((string)$item['display'] === '1' || $item['display'] === true),
                    'amount' => isset($item['amount']) ? (float) $item['amount'] : 0.00,
                ];
            }
        }

        $defaults = [
            'use_fixed_amount' => !empty($validated['use_fixed_amount']),
            'deductions' => $deductions,
        ];

        $company->setDeductionDefaults($defaults);

        ActivityLog::log(
            'update_settings',
            'company',
            "Updated company-wide default deduction rules for company #{$company->id}"
        );

        return back()->with('status', 'Company-wide deduction defaults saved and enforced successfully.');
    }

    /**
     * Save company-wide default income options (enforced across payroll runs).
     */
    public function saveCompanyIncomeDefaults(Request $request)
    {
        $currentUser = auth()->user();
        $isSuperAdmin = $currentUser->role === 'admin' || $currentUser->role_id === Role::SUPER_ADMIN;
        $isCompanyAdmin = in_array($currentUser->role, ['company', 'company_admin'], true)
            || $currentUser->role_id === Role::COMPANY_ADMIN;
        if (!$isSuperAdmin && !$isCompanyAdmin) {
            abort(403, 'Unauthorized to save company income defaults.');
        }

        $companyId = $request->input('company_id')
            ?? getActiveCompanyId()
            ?? $currentUser->company_id
            ?? session('active_company_id');

        if (!$companyId) {
            $firstCompany = \App\Models\Company::first();
            $companyId = $firstCompany ? $firstCompany->id : null;
        }

        if (!$isSuperAdmin && (int) $currentUser->company_id !== (int) $companyId) {
            abort(403, 'Unauthorized to save company income defaults for this company.');
        }

        if (!$companyId) {
            return back()->with('statusDanger', 'Unable to resolve target company for income defaults.');
        }

        $company = \App\Models\Company::find($companyId);
        if (!$company) {
            return back()->with('statusDanger', 'Target company not found.');
        }

        $hasIncomeInputPermission = MenuRoleAccess::hasModuleAccess($currentUser, 'payroll_income_inputs');
        $hasRateVisibilityPermission = MenuRoleAccess::hasModuleAccess($currentUser, 'payroll_income_rate_visibility');

        if (!$hasIncomeInputPermission) {
            abort(403, 'User is restricted from modifying company income defaults without authorization.');
        }

        if (!$hasRateVisibilityPermission) {
            if ($request->has('use_fixed_income_amount')) {
                abort(403, 'Users without rate visibility are restricted from enabling fixed manual income amount mode in defaults.');
            }

            foreach ((array) $request->input('incomes', []) as $income) {
                if (is_array($income) && (array_key_exists('amount', $income) || array_key_exists('rate', $income))) {
                    abort(403, 'Users without rate visibility are restricted from saving fixed monetary income amounts or rates in defaults.');
                }
            }
        }

        $validated = $request->validate([
            'use_fixed_income_amount' => 'nullable',
            'incomes' => 'nullable|array',
            'incomes.*.income_type' => 'required|string',
            'incomes.*.display' => 'nullable',
            'incomes.*.number_input' => 'nullable|numeric|min:0',
            'incomes.*.amount' => 'nullable|numeric|min:0',
        ]);

        $existingDefaults = $company->income_defaults ?? [];
        $existingIncomes = collect($existingDefaults['incomes'] ?? []);

        $incomes = [];
        if (!empty($validated['incomes'])) {
            foreach ($validated['incomes'] as $item) {
                $type = (string) $item['income_type'];
                $matchedExisting = $existingIncomes->firstWhere('income_type', $type);
                $existingAmount = isset($matchedExisting['amount']) ? (float) $matchedExisting['amount'] : 0.00;

                $incomes[] = [
                    'income_type' => $type,
                    'display' => isset($item['display']) && ((string)$item['display'] === '1' || $item['display'] === true),
                    'number_input' => (isset($item['number_input']) && is_numeric($item['number_input']) && $item['number_input'] !== '') ? (float) $item['number_input'] : null,
                    'amount' => $hasRateVisibilityPermission
                        ? (isset($item['amount']) && is_numeric($item['amount']) ? (float) $item['amount'] : 0.00)
                        : $existingAmount,
                ];
            }
        }

        $defaults = [
            'use_fixed_income_amount' => $hasRateVisibilityPermission
                ? !empty($validated['use_fixed_income_amount'])
                : !empty($existingDefaults['use_fixed_income_amount']),
            'incomes' => $incomes,
        ];

        $company->setIncomeDefaults($defaults);

        ActivityLog::log(
            'update_settings',
            'company',
            "Updated company-wide default income rules for company #{$company->id}"
        );

        return back()->with('status', 'Company-wide income defaults saved and enforced successfully.');
    }
}
