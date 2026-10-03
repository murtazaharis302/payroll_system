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
use App\Services\PayrollWorkflowService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

trait PayrollDraftCalculationActions
{
    use PayrollStatutorySyncActions;

    public function generateDraft(PayrollPeriod $period, array $employeeIds = [], array $options = []): Collection
    {
        return require app_path('Services/PayrollGeneration/run.php');
    }

    /**
     * Check a list of PayHeader records or calculation arrays for negative net pay employees.
     *
     * @param iterable $payHeaders
     * @return array List of employees with negative net pay
     */
    public function getNegativeNetPayEmployees(iterable $payHeaders): array
    {
        $negativeEmployees = [];

        foreach ($payHeaders as $item) {
            if ($item instanceof PayHeader) {
                if ((float) $item->net_pay < 0) {
                    $emp = $item->employee;
                    $negativeEmployees[] = [
                        'id' => $item->id,
                        'employee_id' => $item->employee_id,
                        'code' => $emp->employee_code ?? 'EMP-' . $item->employee_id,
                        'name' => $emp->full_name ?? 'Employee',
                        'net_pay' => (float) $item->net_pay,
                    ];
                }
            } elseif (is_array($item)) {
                $netPay = (float) ($item['net_pay'] ?? 0);
                if ($netPay < 0) {
                    $negativeEmployees[] = [
                        'id' => $item['id'] ?? null,
                        'employee_id' => $item['employee_id'] ?? null,
                        'code' => $item['code'] ?? ($item['employee_code'] ?? 'EMP'),
                        'name' => $item['name'] ?? ($item['full_name'] ?? 'Employee'),
                        'net_pay' => $netPay,
                    ];
                }
            }
        }

        return $negativeEmployees;
    }

    /**
     * Resolve the initial values for one new draft deduction line. Existing
     * deduction lines are deliberately not changed by this operation.
     *
     * @return array{number_input: float, rate: float}
     */
    public function resolveAddedDraftDeductionLine(
        PayHeader $payHeader,
        DeductionType $deductionType,
        ?EmpLoan $loan,
        ?float $numberInput = null,
        mixed $rateOverride = null
    ): array {
        if ($loan) {
            $rate = $rateOverride !== null && $rateOverride !== ''
                ? (float) $rateOverride
                : (float) $loan->per_cutoff_amount;

            return [
                'number_input' => 1.00,
                'rate' => min(max(0.00, $rate), (float) $loan->current_balance),
            ];
        }

        $isStatutory = in_array($deductionType->system_key, ['sss', 'philhealth', 'pagibig', 'tax'], true);
        if (!$isStatutory) {
            $period = $payHeader->payrollPeriod;
            $assignment = EmpDeduction::withoutGlobalScopes()
                ->where('employee_id', $payHeader->employee_id)
                ->where('deduction_type_id', $deductionType->id)
                ->where('is_active', true)
                ->when($period, function ($query, $p) {
                    $query->where(function ($q) use ($p) {
                        $q->whereNull('start_date')
                          ->orWhere('start_date', '<=', $p->period_end);
                    })->where(function ($q) use ($p) {
                        $q->whereNull('end_date')
                          ->orWhere('end_date', '>=', $p->period_start);
                    });
                })
                ->orderBy('start_date', 'desc')
                ->first();

            $qty = ($numberInput !== null && $numberInput !== '')
                ? max(0.00, (float) $numberInput)
                : 1.00;

            if (strcasecmp((string)$deductionType->code, 'ABSENCES') === 0) {
                $qty = 1.00;
            }

            if ($rateOverride !== null && $rateOverride !== '') {
                return ['number_input' => $qty, 'rate' => max(0.00, (float) $rateOverride)];
            }

            $rate = (float) ($assignment?->rate ?? 0.00);
            if ($assignment?->frequency === 'monthly') {
                $rate /= $this->statutoryPeriodFactor($payHeader->payrollPeriod?->frequency);
            }

            return ['number_input' => $qty, 'rate' => max(0.00, $rate)];
        }

        $period = $payHeader->payrollPeriod;
        $employee = $payHeader->employee;
        if (!$period || !$employee) {
            return ['number_input' => 1.00, 'rate' => 0.00];
        }

        $factor = $this->statutoryPeriodFactor($period->frequency);
        $annualPeriods = $this->annualPeriods($period->frequency);
        $payDate = $period->pay_date
            ? Carbon::parse($period->pay_date)->toDateString()
            : Carbon::parse($period->period_end)->toDateString();
        $grossPay = (float) PayIn::withoutGlobalScopes()
            ->where('pay_id', $payHeader->id)
            ->sum(DB::raw('rate * number_input'));
        $monthlySalaryBasis = $this->monthlySalaryBasis($grossPay, $factor, $employee);

        $sss = round($this->statutoryCalculationService
            ->calculateSSS($monthlySalaryBasis, $this->statutoryCalculationService->resolveRule('sss', $payDate))['monthly_ee'] / $factor, 2);
        $philhealth = round($this->statutoryCalculationService
            ->calculatePhilHealth($monthlySalaryBasis, $this->statutoryCalculationService->resolveRule('philhealth', $payDate))['monthly_ee'] / $factor, 2);
        $pagibig = round($this->statutoryCalculationService
            ->calculatePagIbig($monthlySalaryBasis, $this->statutoryCalculationService->resolveRule('pagibig', $payDate))['monthly_ee'] / $factor, 2);

        $rate = match ($deductionType->system_key) {
            'sss' => $sss,
            'philhealth' => $philhealth,
            'pagibig' => $pagibig,
            'tax' => $this->statutoryCalculationService->calculateWithholdingTax(
                max(0.00, $grossPay - $this->existingStatutoryRate($payHeader, 'sss', $sss) - $this->existingStatutoryRate($payHeader, 'philhealth', $philhealth) - $this->existingStatutoryRate($payHeader, 'pagibig', $pagibig)),
                $annualPeriods,
                $this->statutoryCalculationService->resolveRule('tax', $payDate)
            ),
            default => 0.00,
        };

        $qty = ($numberInput !== null && $numberInput !== '')
            ? max(0.00, (float) $numberInput)
            : 1.00;

        return ['number_input' => $qty, 'rate' => $rate];
    }

    /**
     * Resolve the values for one new draft income line.
     *
     * @return array{number_input: float, rate: float}
     */
    public function resolveAddedIncomeLine(PayHeader $draft, IncomeType $incomeType, float $numberInput, mixed $rateOverride = null): array
    {
        if ($rateOverride !== null && $rateOverride !== '') {
            return ['number_input' => $numberInput, 'rate' => (float) $rateOverride];
        }

        $employee = $draft->employee;
        if (!$employee) {
            return ['number_input' => $numberInput, 'rate' => 0.00];
        }

        $policy = PayrollPolicy::forCompany($employee->company_id, (string) $draft->payrollPeriod?->period_start);
        $calculator = new PayrollLineCalculator($policy);

        $assignment = EmpIncome::withoutGlobalScopes()
            ->where('employee_id', $employee->id)
            ->where('income_type_id', $incomeType->id)
            ->where('is_active', true)
            ->first();

        $assignmentRate = ($assignment !== null && (float) ($assignment->rate ?? $assignment->amount ?? 0) > 0)
            ? (float) ($assignment->rate ?? $assignment->amount ?? 0)
            : null;

        if ($incomeType->system_key === 'overtime') {
            // Overtime is a required payroll-domain exception (hours x rate x multiplier)
            return $calculator->calculateOvertimePay($employee, $numberInput, null, 'regular', $assignmentRate);
        }

        if ($assignmentRate !== null) {
            return ['number_input' => $numberInput, 'rate' => $assignmentRate];
        }

        return [
            'number_input' => $numberInput,
            'rate' => 0.00,
        ];
    }



    /**
     * Approve and finalize the payroll header draft.
     *
     * @param PayHeader $draft
     * @param User $admin
     * @return PayHeader
     */
    public function approvePayroll(PayHeader $draft, User $admin): PayHeader
    {
        return DB::transaction(function () use ($draft, $admin) {
            $lockedDraft = PayHeader::where('id', $draft->id)->lockForUpdate()->first();
            if (!$lockedDraft) {
                return $draft;
            }

            if (in_array($lockedDraft->status, ['approved', 'posted', 'paid'])) {
                return $lockedDraft;
            }

            // Recalculate totals immediately before approval from latest PayIn and PayOut lines
            $lockedDraft->recalculateTotals();

            if ((float) $lockedDraft->net_pay < 0) {
                $empCode = $lockedDraft->employee->employee_code ?? 'EMP-' . $lockedDraft->employee_id;
                $empName = $lockedDraft->employee->full_name ?? 'Employee';
                $netFormatted = number_format((float) $lockedDraft->net_pay, 2);
                throw new \RuntimeException("Cannot approve payroll draft. Employee {$empCode} - {$empName} has a negative net income of ₱{$netFormatted}. Please adjust income or deductions before approving.");
            }

            $lockedDraft->save();

            $workflow = app(PayrollWorkflowService::class);
            $workflow->transition($lockedDraft, PayrollWorkflowService::STATE_APPROVED, [
                'user' => $admin,
            ]);

            return $lockedDraft->fresh();
        });

        return $draft->fresh();
    }
}
