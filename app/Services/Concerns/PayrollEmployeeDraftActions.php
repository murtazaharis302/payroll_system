<?php

namespace App\Services\Concerns;

use App\Models\AttendanceLog;
use App\Models\Company;
use App\Models\DeductionType;
use App\Models\EmpDeduction;
use App\Models\EmpIncome;
use App\Models\EmpLoan;
use App\Models\EmployeeMaster;
use App\Models\GovernmentContribution;
use App\Models\HRAdjustment;
use App\Models\IncomeType;
use App\Models\LoanPayment;
use App\Models\PayHeader;
use App\Models\PayIn;
use App\Models\PayOut;
use App\Models\PayrollPeriod;
use App\Models\PayrollPolicy;
use App\Models\User;
use App\Services\PayrollLineCalculator;
use App\Services\StatutoryCalculationService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

trait PayrollEmployeeDraftActions
{
    public function __construct(?StatutoryCalculationService $statutoryCalculationService = null)
    {
        $this->statutoryCalculationService = $statutoryCalculationService ?? app(StatutoryCalculationService::class);
    }

    /**
     * Return employees who can be added to an existing payroll period.
     *
     * This is deliberately separate from the legacy batch query so the UI and
     * the additive endpoint share exactly the same eligibility definition.
     *
     * @return Collection<int, EmployeeMaster>
     */
    public function eligibleEmployeesForAddition(PayrollPeriod $period): Collection
    {
        return EmployeeMaster::withoutGlobalScopes()
            ->with('dept')
            ->where('company_id', $period->company_id)
            ->where('status', 'active')
            ->whereHas('dept', function ($query) use ($period) {
                $query->where('company_id', $period->company_id);
            })
            ->whereNotIn('id', PayHeader::withoutGlobalScopes()
                ->where('payroll_period_id', $period->id)
                ->select('employee_id'))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    /**
     * Create the first payroll draft for one missing employee in an open period.
     * Existing PayHeaders are a hard stop, irrespective of their workflow state.
     */
    public function addEmployeeDraft(PayrollPeriod $period, int $employeeId): PayHeader
    {
        try {
            return DB::transaction(function () use ($period, $employeeId): PayHeader {
                // The lock order is intentional: period, employee, then header.
                $lockedPeriod = PayrollPeriod::withoutGlobalScopes()
                    ->lockForUpdate()
                    ->findOrFail($period->id);

                if ($lockedPeriod->status !== 'open') {
                    throw new \InvalidArgumentException('This payroll period is not open and cannot accept additional employees.');
                }

                $employee = EmployeeMaster::withoutGlobalScopes()
                    ->with('dept')
                    ->lockForUpdate()
                    ->findOrFail($employeeId);

                $hasValidDepartment = $employee->dept_id !== null
                    && $employee->dept !== null
                    && (int) $employee->dept->company_id === (int) $lockedPeriod->company_id;

                if ((int) $employee->company_id !== (int) $lockedPeriod->company_id
                    || $employee->status !== 'active'
                    || !$hasValidDepartment) {
                    throw new \InvalidArgumentException('The selected employee is not eligible for this payroll period.');
                }

                $existingHeader = PayHeader::withoutGlobalScopes()
                    ->where('payroll_period_id', $lockedPeriod->id)
                    ->where('employee_id', $employee->id)
                    ->lockForUpdate()
                    ->first();

                if ($existingHeader) {
                    throw new \InvalidArgumentException('Employee is already included in this payroll period.');
                }

                // create_only is a one-way guard in generateDraft: it can create
                // a fresh header, but can never enter its legacy delete/recreate path.
                return $this->generateDraft($lockedPeriod, [$employee->id], ['create_only' => true])->sole();
            });
        } catch (QueryException $exception) {
            if ($this->isPayHeaderEmployeePeriodDuplicate($exception)) {
                throw new \InvalidArgumentException('Employee is already included in this payroll period.', 0, $exception);
            }

            throw $exception;
        }
    }

    protected function isPayHeaderEmployeePeriodDuplicate(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'pay_headers_period_employee_unique')
            || (str_contains($message, 'pay_headers')
                && str_contains($message, 'payroll_period_id')
                && str_contains($message, 'employee_id'));
    }

    /**
     * Generate payroll drafts for a given period, optional subset of employees, and deduction options.
     *
     * @param PayrollPeriod $period
     * @param array $employeeIds
     * @param array $options
     * @return Collection<PayHeader>
     */
}
