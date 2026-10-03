<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Concerns;

use App\Exceptions\PayrollBatchChangeValidationException;
use App\Models\Company;
use App\Models\DeductionType;
use App\Models\IncomeType;
use App\Models\MenuRoleAccess;
use App\Models\PayrollPeriod;
use App\Models\Role;
use App\Services\PayrollBatchChangeService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;

trait PayrollDraftBatchChangeActions
{
    /**
     * Show the Make Batch Changes page.
     */
    public function batchChangeCreate(Request $request)
    {
        $currentUser = auth()->user();
        $hasIncomeInputPermission = MenuRoleAccess::hasModuleAccess($currentUser, 'payroll_income_inputs');
        $hasDeductionInputPermission = MenuRoleAccess::hasModuleAccess($currentUser, 'payroll_deduction_inputs');
        $hasRateVisibilityPermission = MenuRoleAccess::hasModuleAccess($currentUser, 'payroll_income_rate_visibility');
        $canEditPending = MenuRoleAccess::hasModuleAccess($currentUser, 'payroll_locked_edit_override');

        if (!$hasIncomeInputPermission && !$hasDeductionInputPermission) {
            abort(403, 'Unauthorized access to payroll batch changes.');
        }

        $selectedCompanyId = $request->input('company_id') ?? getActiveCompanyId();

        $companies = collect();
        if ($currentUser->role === 'admin' || $currentUser->role_id === Role::SUPER_ADMIN) {
            $companies = Company::where('status', '1')->orderBy('name')->get();
            if (!$selectedCompanyId && $companies->count() > 0) {
                $selectedCompanyId = $companies->first()->id;
            }
        } else {
            $selectedCompanyId = $currentUser->company_id ?? getActiveCompanyId();
        }

        $payrollPeriods = collect();
        if ($selectedCompanyId) {
            $payrollPeriods = PayrollPeriod::where('company_id', $selectedCompanyId)
                ->orderBy('period_start', 'desc')
                ->get();
        }

        $selectedPeriodId = $request->input('payroll_period_id') ?? $payrollPeriods->first()?->id;
        $selectedPeriod = $selectedPeriodId ? $payrollPeriods->firstWhere('id', (int) $selectedPeriodId) : null;

        $eligibleDrafts = collect();
        if ($selectedPeriod) {
            $eligibleDrafts = app(PayrollBatchChangeService::class)->getEligiblePeriodDrafts(
                $selectedPeriod,
                (int) $selectedCompanyId,
                $canEditPending
            );
        }

        $allCompanyIncomeTypes = IncomeType::forCompany($selectedCompanyId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        $allCompanyDeductionTypes = DeductionType::forCompany($selectedCompanyId)
            ->where('is_active', true)
            ->with(['loanTypes' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'is_loan_enabled', 'type']);

        $activeTab = $request->input('tab');
        if (!$activeTab) {
            $activeTab = $hasIncomeInputPermission ? 'incomes' : 'deductions';
        }
        if ($activeTab === 'incomes' && !$hasIncomeInputPermission) {
            $activeTab = 'deductions';
        } elseif ($activeTab === 'deductions' && !$hasDeductionInputPermission) {
            $activeTab = 'incomes';
        }

        return view('admin.payroll-drafts.batch-change', compact(
            'companies',
            'selectedCompanyId',
            'payrollPeriods',
            'selectedPeriodId',
            'selectedPeriod',
            'eligibleDrafts',
            'allCompanyIncomeTypes',
            'allCompanyDeductionTypes',
            'hasIncomeInputPermission',
            'hasDeductionInputPermission',
            'hasRateVisibilityPermission',
            'canEditPending',
            'activeTab'
        ));
    }

    /**
     * Store and apply additive batch changes to selected drafts.
     */
    public function batchChangeStore(Request $request)
    {
        $currentUser = auth()->user();
        $hasIncomeInputPermission = MenuRoleAccess::hasModuleAccess($currentUser, 'payroll_income_inputs');
        $hasDeductionInputPermission = MenuRoleAccess::hasModuleAccess($currentUser, 'payroll_deduction_inputs');
        $hasRateVisibilityPermission = MenuRoleAccess::hasModuleAccess($currentUser, 'payroll_income_rate_visibility');

        $validated = $request->validate([
            'payroll_period_id' => 'required|integer|exists:payroll_periods,id',
            'draft_ids' => 'required|array|min:1',
            'draft_ids.*' => 'required|integer|distinct',
            'income_type_ids' => 'nullable|array',
            'income_type_ids.*' => 'required|integer|distinct',
            'income_type_configs' => 'nullable|array',
            'income_type_configs.*.number_input' => 'nullable|numeric|min:0',
            'income_type_configs.*.rate' => 'nullable|numeric|min:0',
            'deduction_type_ids' => 'nullable|array',
            'deduction_type_ids.*' => 'required|integer|distinct',
            'deduction_type_configs' => 'nullable|array',
            'deduction_type_configs.*.number_input' => 'nullable|numeric|min:0',
            'deduction_type_configs.*.rate' => 'nullable|numeric|min:0',
        ]);

        $incomeTypeIds = array_map('intval', $validated['income_type_ids'] ?? []);
        $deductionTypeIds = array_map('intval', $validated['deduction_type_ids'] ?? []);

        if (empty($incomeTypeIds) && empty($deductionTypeIds)) {
            return back()
                ->withInput()
                ->with('statusDanger', 'At least one income or deduction type must be selected.');
        }

        if (!empty($incomeTypeIds) && !$hasIncomeInputPermission) {
            abort(403, 'User is restricted from submitting payroll income batch inputs without authorization.');
        }

        if (!empty($deductionTypeIds) && !$hasDeductionInputPermission) {
            abort(403, 'User is restricted from submitting payroll deduction batch inputs without authorization.');
        }

        if (!empty($incomeTypeIds) && !$hasRateVisibilityPermission) {
            $rawConfigs = (array) ($validated['income_type_configs'] ?? []);
            foreach ($rawConfigs as $cfg) {
                if (is_array($cfg) && isset($cfg['rate']) && $cfg['rate'] !== '' && $cfg['rate'] !== null) {
                    abort(403, 'Users without rate visibility are restricted from submitting monetary income rates.');
                }
            }
        }

        // Validate Income configs contain only selected income_type_ids
        $filteredIncomeConfigs = [];
        if (!empty($validated['income_type_configs'])) {
            foreach ($validated['income_type_configs'] as $typeIdKey => $config) {
                $intKey = (int) $typeIdKey;
                if (!in_array($intKey, $incomeTypeIds, true)) {
                    return back()
                        ->withInput()
                        ->with('statusDanger', 'Invalid configuration: options provided for unselected income types.')
                        ->with('generation_errors', ["Configuration provided for unselected income type ID: {$typeIdKey}"]);
                }
                $filteredIncomeConfigs[$intKey] = $config;
            }
        }

        // Validate Deduction configs contain only selected deduction_type_ids
        $filteredDeductionConfigs = [];
        if (!empty($validated['deduction_type_configs'])) {
            foreach ($validated['deduction_type_configs'] as $typeIdKey => $config) {
                $intKey = (int) $typeIdKey;
                if (!in_array($intKey, $deductionTypeIds, true)) {
                    return back()
                        ->withInput()
                        ->with('statusDanger', 'Invalid configuration: options provided for unselected deduction types.')
                        ->with('generation_errors', ["Configuration provided for unselected deduction type ID: {$typeIdKey}"]);
                }

                $filteredDeductionConfigs[$intKey] = $config;
            }
        }

        $period = PayrollPeriod::withoutGlobalScopes()->findOrFail($validated['payroll_period_id']);

        if ($currentUser->role !== 'admin' && $currentUser->role_id !== Role::SUPER_ADMIN) {
            $companyId = getActiveCompanyId() ?? $currentUser->company_id;
            if ($companyId && (int) $period->company_id !== (int) $companyId) {
                abort(403, 'Unauthorized access to this payroll period.');
            }
        }

        try {
            $result = app(PayrollBatchChangeService::class)->applyBatchChange(
                $period,
                $incomeTypeIds,
                $filteredIncomeConfigs,
                $deductionTypeIds,
                $filteredDeductionConfigs,
                array_map('intval', $validated['draft_ids']),
                $currentUser
            );

            $msg = "Batch changes applied to {$result['updated_count']} payroll draft(s).";
            if (!empty($result['skipped_loan_employees'])) {
                $skippedParts = [];
                foreach ($result['skipped_loan_employees'] as $loanType => $employees) {
                    $skippedParts[] = "{$loanType} skipped for " . count($employees) . " employee(s) without active loans";
                }
                $msg .= " (" . implode(', ', $skippedParts) . ").";
            }

            return redirect()->to(panelRoute('payroll-drafts.index', ['payroll_period_id' => $period->id]))
                ->with('status', $msg);

        } catch (AuthorizationException $e) {
            abort(403, $e->getMessage());

        } catch (PayrollBatchChangeValidationException $e) {
            return back()
                ->withInput()
                ->with('statusDanger', $e->getMessage())
                ->with('generation_errors', $e->getErrors());

        } catch (\InvalidArgumentException $e) {
            return back()
                ->withInput()
                ->with('statusDanger', $e->getMessage());

        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Payroll draft batch change unexpected error: ' . $e->getMessage(), [
                'exception' => $e,
                'period_id' => $period->id,
            ]);

            return back()
                ->withInput()
                ->with('statusDanger', 'Batch changes could not be applied. Please review the inputs and try again.');
        }
    }
}
