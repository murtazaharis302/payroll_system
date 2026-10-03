<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\PayHeader;
use App\Models\EmployeeMaster;
use App\Models\PayrollPeriod;
use App\Models\ActivityLog;
use App\Models\IncomeType;
use App\Models\DeductionType;
use App\Models\MenuRoleAccess;
use App\Models\Role;
use App\Models\Department;
use App\Services\PayrollProcessingService;
use Illuminate\Http\Request;
use App\Http\Requests\AddPayrollDraftEmployeeRequest;
use App\Http\Requests\StorePayrollDraftRequest;

trait PayrollDraftGenerationActions
{
    /** Show the single-employee additive payroll form for an open period. */
    public function addEmployeeCreate(Request $request)
    {
        $validated = $request->validate([
            'payroll_period_id' => ['required', 'integer', 'exists:payroll_periods,id'],
        ]);

        $period = PayrollPeriod::withoutGlobalScopes()->findOrFail($validated['payroll_period_id']);
        $this->authorizePayrollPeriodCompany($period);

        if ($period->status !== 'open') {
            return redirect()->to(panelRoute('payroll-drafts.index', ['payroll_period_id' => $period->id]))
                ->with('statusDanger', 'This payroll period is not open and cannot accept additional employees.');
        }

        $employees = app(PayrollProcessingService::class)->eligibleEmployeesForAddition($period);

        return view('admin.payroll-drafts.add-employee', compact('period', 'employees'));
    }

    /** Generate a first draft for one employee without touching existing drafts. */
    public function addEmployeeStore(AddPayrollDraftEmployeeRequest $request)
    {
        $validated = $request->validated();
        $period = PayrollPeriod::withoutGlobalScopes()->findOrFail($validated['payroll_period_id']);
        $this->authorizePayrollPeriodCompany($period);

        try {
            $draft = app(PayrollProcessingService::class)->addEmployeeDraft($period, (int) $validated['employee_id']);
            $employeeName = trim(($draft->employee?->employee_code ? $draft->employee->employee_code . ' - ' : '') . ($draft->employee?->full_name ?? 'Employee'));

            ActivityLog::log(
                'add_employee',
                'payroll_draft',
                "Added {$employeeName} to payroll period {$period->period_name}.",
                PayHeader::class,
                $draft->id,
                null,
                null,
                $period->company_id
            );

            return redirect()->to(panelRoute('payroll-drafts.edit', $draft->id))
                ->with('status', "Added {$employeeName} to this payroll period successfully.");
        } catch (\InvalidArgumentException $exception) {
            return redirect()->to(panelRoute('payroll-drafts.add-employee.create', ['payroll_period_id' => $period->id]))
                ->withInput()
                ->with('statusDanger', $exception->getMessage());
        } catch (\Throwable $exception) {
            \Illuminate\Support\Facades\Log::error('Add payroll employee error: ' . $exception->getMessage(), [
                'exception' => $exception,
                'payroll_period_id' => $period->id,
                'employee_id' => $validated['employee_id'],
            ]);

            return redirect()->to(panelRoute('payroll-drafts.add-employee.create', ['payroll_period_id' => $period->id]))
                ->withInput()
                ->with('statusDanger', $exception->getMessage());
        }
    }

    protected function authorizePayrollPeriodCompany(PayrollPeriod $period): void
    {
        $currentUser = auth()->user();
        if ($currentUser->role === 'admin') {
            return;
        }

        $companyId = getActiveCompanyId() ?? $currentUser->company_id;
        if ($companyId && (int) $period->company_id !== (int) $companyId) {
            abort(403, 'Unauthorized access to this payroll period.');
        }
    }

    /**
     * Show the form for creating payroll drafts (batch creation for period).
     */
    public function create(Request $request)
    {
        $currentUser = auth()->user();
        $selectedCompanyId = $request->input('company_id') ?? getActiveCompanyId();

        $companies = collect();
        if ($currentUser->role === 'admin') {
            $companies = \App\Models\Company::where('status', '1')->orderBy('name')->get();
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

        $employees = collect();
        if ($selectedCompanyId) {
            $employees = EmployeeMaster::where('company_id', $selectedCompanyId)
                ->where('status', 'active')
                ->whereHas('dept', function ($q) use ($selectedCompanyId) {
                    $q->where('company_id', $selectedCompanyId);
                })
                ->orderBy('last_name')
                ->get();
        }

        $selectedPeriodId = $request->payroll_period_id;

        $company = $selectedCompanyId ? \App\Models\Company::find($selectedCompanyId) : null;
        $companyDeductionDefaults = $company ? $company->deduction_defaults : ['use_fixed_amount' => false, 'deductions' => []];
        $companyIncomeDefaults = $company ? $company->income_defaults : ['use_fixed_income_amount' => false, 'incomes' => []];

        $allCompanyIncomeTypes = IncomeType::forCompany($selectedCompanyId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
        $allCompanyDeductionTypes = DeductionType::forCompany($selectedCompanyId)->where('is_active', true)->orderBy('name')->get();

        $hasIncomeInputPermission = MenuRoleAccess::hasModuleAccess(
            $currentUser,
            'payroll_income_inputs'
        );
        $hasIncomeRateVisibilityPermission = MenuRoleAccess::hasModuleAccess(
            $currentUser,
            'payroll_income_rate_visibility'
        );
        $hasDeductionInputPermission = MenuRoleAccess::hasModuleAccess(
            $currentUser,
            'payroll_deduction_inputs'
        );
        $canSeeIncomeConfig = $hasIncomeInputPermission;
        $canSeeDeductionConfig = $hasDeductionInputPermission;
        $canManageCompanyDefaults = $currentUser->role === 'admin'
            || $currentUser->role_id === Role::SUPER_ADMIN
            || in_array($currentUser->role, ['company', 'company_admin'], true)
            || $currentUser->role_id === Role::COMPANY_ADMIN;

        $departments = collect();
        if ($selectedCompanyId) {
            $departments = Department::where('company_id', $selectedCompanyId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get();
        }

        $selectedDeptId = $request->input('dept_id');

        return view('admin.payroll-drafts.create', compact(
            'payrollPeriods',
            'employees',
            'selectedPeriodId',
            'departments',
            'selectedDeptId',
            'companies',
            'selectedCompanyId',
            'companyDeductionDefaults',
            'companyIncomeDefaults',
            'allCompanyIncomeTypes',
            'allCompanyDeductionTypes',
            'hasIncomeInputPermission',
            'hasIncomeRateVisibilityPermission',
            'hasDeductionInputPermission',
            'canSeeIncomeConfig',
            'canSeeDeductionConfig',
            'canManageCompanyDefaults'
        ));
    }

    /**
     * Generate payroll drafts for all employees in a period.
     */
    public function generate(StorePayrollDraftRequest $request)
    {
        return $this->store($request);
    }

    /**
     * Store payroll drafts (Phase 4 integration with PayrollProcessingService)
     */
    public function store(StorePayrollDraftRequest $request)
    {
        $currentUser = auth()->user();
        $hasIncomeInputPermission = MenuRoleAccess::hasModuleAccess($currentUser, 'payroll_income_inputs');
        $hasDeductionInputPermission = MenuRoleAccess::hasModuleAccess($currentUser, 'payroll_deduction_inputs');
        $hasRateVisibilityPermission = MenuRoleAccess::hasModuleAccess($currentUser, 'payroll_income_rate_visibility');

        // Authorization must inspect the raw request before validation removes
        // unexpected monetary fields. Hidden/disabled controls are never a guard.
        if (!$hasIncomeInputPermission && ($request->has('incomes') || $request->has('use_fixed_income_amount'))) {
            abort(403, 'User is restricted from submitting payroll income configurations without authorization.');
        }

        if (!$hasRateVisibilityPermission) {
            if ($request->has('use_fixed_income_amount')) {
                abort(403, 'Users without rate visibility are restricted from enabling fixed manual income amount mode.');
            }

            foreach ((array) $request->input('incomes', []) as $income) {
                if (is_array($income) && (array_key_exists('amount', $income) || array_key_exists('rate', $income))) {
                    abort(403, 'Users without rate visibility are restricted from submitting fixed monetary income amounts or rates.');
                }
            }
        }

        if (!$hasDeductionInputPermission && ($request->has('deductions') || $request->has('use_fixed_amount'))) {
            abort(403, 'User is restricted from submitting payroll deduction configurations without authorization.');
        }

        $validated = $request->validated();
        $periodId = $validated['payroll_period_id'];
        $period = PayrollPeriod::findOrFail($periodId);
        $employeeIds = $validated['employee_ids'] ?? [];
        $deptId = $validated['dept_id'] ?? null;

        if ($currentUser->role !== 'admin') {
            $companyId = getActiveCompanyId() ?? $currentUser->company_id;
            if ($companyId && (int) $period->company_id !== (int) $companyId) {
                abort(403, 'Unauthorized access to this payroll period.');
            }
        }

        $submittedIncomes = (array) ($validated['incomes'] ?? $request->input('incomes', []));
        if (!empty($submittedIncomes)) {
            foreach ($submittedIncomes as $incItem) {
                if (!is_array($incItem)) {
                    continue;
                }
                $incTypeId = $incItem['income_type_id'] ?? $incItem['income_type'] ?? null;
                if ($incTypeId === null || $incTypeId === '') {
                    continue;
                }

                $typeQuery = IncomeType::withoutGlobalScopes()
                    ->where('is_active', true)
                    ->where(function ($q) use ($period) {
                        $q->whereNull('company_id')
                          ->orWhere('company_id', $period->company_id);
                    });

                if (is_numeric($incTypeId)) {
                    $typeQuery->where('id', (int) $incTypeId);
                } else {
                    $typeQuery->where('code', (string) $incTypeId);
                }

                if (!$typeQuery->exists()) {
                    return back()->withInput()->with('statusDanger', 'One or more selected income types are invalid, inactive, or not authorized for this company.');
                }
            }
        }

        if (!empty($deptId)) {
            $dept = Department::where('id', $deptId)
                ->where('company_id', $period->company_id)
                ->where('is_active', true)
                ->first();

            if (!$dept) {
                return back()->withInput()->with('statusDanger', 'The selected department is invalid or inactive for this company.');
            }

            $deptEmployeeIds = EmployeeMaster::where('company_id', $period->company_id)
                ->where('status', 'active')
                ->where('dept_id', $deptId)
                ->pluck('id')
                ->toArray();

            if (empty($deptEmployeeIds)) {
                return back()->withInput()->with('statusDanger', 'No active employees found in the selected department.');
            }

            $employeeIds = !empty($employeeIds) ? array_values(array_intersect($employeeIds, $deptEmployeeIds)) : $deptEmployeeIds;

            if (empty($employeeIds)) {
                return back()->withInput()->with('statusDanger', 'No active employees matched the selected department criteria.');
            }
        }

        $isRegenerateRequest = (bool) $request->input('regenerate', false);

        if (!$isRegenerateRequest) {
            if (empty($employeeIds) && empty($deptId)) {
                $employeeIds = EmployeeMaster::where('company_id', $period->company_id)
                    ->where('status', 'active')
                    ->whereHas('dept', function ($q) use ($period) {
                        $q->where('company_id', $period->company_id);
                    })
                    ->pluck('id')
                    ->toArray();
            }

            $existingEmployeeIds = PayHeader::where('payroll_period_id', $periodId)
                ->whereIn('employee_id', $employeeIds)
                ->pluck('employee_id')
                ->map(fn ($id) => (int) $id)
                ->toArray();

            $employeeIds = array_values(array_diff(
                array_map('intval', $employeeIds),
                $existingEmployeeIds
            ));

            if (empty($employeeIds)) {
                return back()->withInput()->with(
                    'status',
                    'Payroll drafts already exist for all eligible employees in the selected scope.'
                );
            }
        }

        try {
            $options = [
                'use_fixed_amount' => (bool) ($validated['use_fixed_amount'] ?? $request->input('use_fixed_amount', false)),
                'deductions' => $validated['deductions'] ?? $request->input('deductions', []),
                'use_fixed_income_amount' => (bool) ($validated['use_fixed_income_amount'] ?? $request->input('use_fixed_income_amount', false)),
                'incomes' => $validated['incomes'] ?? $request->input('incomes', []),
                'payroll_inputs' => $validated['payroll_inputs'] ?? $request->input('payroll_inputs', []),
                'employee_inputs' => $validated['employee_inputs'] ?? $request->input('employee_inputs', []),
                'days_worked' => $validated['days_worked'] ?? $request->input('days_worked', null),
                'regenerate' => $isRegenerateRequest,
                'create_only' => !$isRegenerateRequest,
            ];

            $service = app(PayrollProcessingService::class);
            $generatedHeaders = $service->generateDraft($period, $employeeIds, $options);

            ActivityLog::log(
                'generate',
                'payroll_draft',
                "Generated " . $generatedHeaders->count() . " payroll drafts for period"
            );

            return redirect()->route('payroll-drafts.index', ['payroll_period_id' => $periodId])
                ->with('status', "Generated " . $generatedHeaders->count() . " payroll drafts successfully.");

        } catch (\App\Exceptions\PayrollGenerationSetupException $e) {
            return back()
                ->withInput()
                ->with('statusDanger', $e->getMessage())
                ->with('generation_errors', $e->getErrors());

        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());

        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('statusDanger', $e->getMessage());

        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Payroll draft generation unexpected error: ' . $e->getMessage(), [
                'exception' => $e,
                'period_id' => $periodId ?? null,
            ]);

            return back()->withInput()->with('statusDanger', 'Payroll drafts could not be generated. Please review the employee payroll setup and try again.');
        }
    }
}
