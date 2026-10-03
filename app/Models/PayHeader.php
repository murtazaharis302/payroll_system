<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Traits\HasCompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class PayHeader extends Model
{
    use HasCompanyScope;

    public const FINAL_REPORT_STATUSES = ['approved', 'posted', 'paid'];

    public $timestamps = true;

    protected $fillable = [
        'payroll_period_id',
        'employee_id',
        'days_worked',
        'days_absent',
        'overtime_hours',
        'late_hours',
        'status',
        'requires_review',
        'review_reason',
        'attendance_override_reason',
        'attendance_override_by',
        'attendance_override_at',
        'submitted_at',
        'submitted_by',
        'approved_at',
        'approved_by',
        'rejected_at',
        'rejected_by',
        'rejection_reason',
        'notes',
        'policy_version',
        'lock_version',
        'total_gross_pay',
        'total_deductions',
        'net_pay',
        'posted_at',
    ];

    protected $casts = [
        'days_worked' => 'decimal:2',
        'days_absent' => 'decimal:2',
        'overtime_hours' => 'decimal:2',
        'late_hours' => 'decimal:2',
        'requires_review' => 'boolean',
        'attendance_override_at' => 'datetime',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'posted_at' => 'datetime',
        'lock_version' => 'integer',
        'total_gross_pay' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'net_pay' => 'decimal:2',
    ];

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id')->withoutGlobalScopes();
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(EmployeeMaster::class, 'employee_id')->withoutGlobalScopes();
    }

    public function payIns(): HasMany
    {
        return $this->hasMany(PayIn::class, 'pay_id');
    }

    public function payOuts(): HasMany
    {
        return $this->hasMany(PayOut::class, 'pay_id');
    }

    public function loanPayments(): HasMany
    {
        return $this->hasMany(LoanPayment::class, 'pay_id');
    }

    /**
     * Recalculates gross pay, total deductions, and net pay from active PayIn and PayOut records.
     */
    public function recalculateTotals(): array
    {
        $calculator = app(\App\Support\Payroll\PayrollTotalsCalculator::class);
        $totals = $calculator->calculate($this);

        $this->total_gross_pay = $totals['total_gross_pay'];
        $this->total_deductions = $totals['total_deductions'];
        $this->net_pay = $totals['net_pay'];
        $this->save();

        return $totals;
    }

    // Accessors for Incomes (derived from payIns)
    public function getBasicPayAttribute()
    {
        return (float) $this->payIns()
            ->whereHas('incomeType', fn ($q) => $q->where('system_key', 'basic'))
            ->sum(DB::raw('rate * number_input'));
    }

    public function getOvertimePayAttribute()
    {
        return (float) $this->payIns()
            ->whereHas('incomeType', fn ($q) => $q->where('system_key', 'overtime'))
            ->sum(DB::raw('rate * number_input'));
    }

    public function getTotalAllowancesAttribute()
    {
        $type = IncomeType::where('code', 'ALLOWANCE')->first();
        if (!$type) return 0.00;
        return (float) $this->payIns()->where('income_type_id', $type->id)->sum(DB::raw('rate * number_input'));
    }

    public function getTotalBonusesAttribute()
    {
        $type = IncomeType::where('code', 'BONUS')->first();
        if (!$type) return 0.00;
        return (float) $this->payIns()->where('income_type_id', $type->id)->sum(DB::raw('rate * number_input'));
    }

    public function getOtherIncomeAttribute()
    {
        $type = IncomeType::where('code', 'OTHER')->first();
        if (!$type) return 0.00;
        return (float) $this->payIns()->where('income_type_id', $type->id)->sum(DB::raw('rate * number_input'));
    }

    // Accessors for Deductions (derived from payOuts)
    public function getSssContributionAttribute()
    {
        return (float) $this->payOuts()
            ->whereHas('deductionType', fn ($q) => $q->where('system_key', 'sss'))
            ->sum(DB::raw('rate * number_input'));
    }

    public function getPhilhealthContributionAttribute()
    {
        return (float) $this->payOuts()
            ->whereHas('deductionType', fn ($q) => $q->where('system_key', 'philhealth'))
            ->sum(DB::raw('rate * number_input'));
    }

    public function getPagibigContributionAttribute()
    {
        return (float) $this->payOuts()
            ->whereHas('deductionType', fn ($q) => $q->where('system_key', 'pagibig'))
            ->sum(DB::raw('rate * number_input'));
    }

    public function getWithholdingTaxAttribute()
    {
        return (float) $this->payOuts()
            ->whereHas('deductionType', fn ($q) => $q->where('system_key', 'tax'))
            ->sum(DB::raw('rate * number_input'));
    }

    public function getSssLoanDeductionAttribute()
    {
        $type = DeductionType::where('code', 'SSS_LOAN')->first();
        if (!$type) return 0.00;
        return (float) $this->payOuts()->where('deduction_type_id', $type->id)->sum(DB::raw('rate * number_input'));
    }

    public function getPagibigLoanDeductionAttribute()
    {
        $type = DeductionType::where('code', 'PAGIBIG_LOAN')->first();
        if (!$type) return 0.00;
        return (float) $this->payOuts()->where('deduction_type_id', $type->id)->sum(DB::raw('rate * number_input'));
    }

    public function getValeLoanDeductionAttribute()
    {
        $type = DeductionType::where('code', 'VALE')->first();
        if (!$type) return 0.00;
        return (float) $this->payOuts()->where('deduction_type_id', $type->id)->sum(DB::raw('rate * number_input'));
    }

    public function getGoodsPurchaseDeductionAttribute()
    {
        $type = DeductionType::where('code', 'GOODS')->first();
        if (!$type) return 0.00;
        return (float) $this->payOuts()->where('deduction_type_id', $type->id)->sum(DB::raw('rate * number_input'));
    }

    public function getLateDeductionAttribute()
    {
        return (float) $this->payOuts()
            ->whereHas('deductionType', fn ($q) => $q->where('system_key', 'tardiness'))
            ->sum(DB::raw('rate * number_input'));
    }

    public function getUndertimeDeductionAttribute()
    {
        return (float) $this->payOuts()
            ->whereHas('deductionType', fn ($q) => $q->where('system_key', 'undertime'))
            ->sum(DB::raw('rate * number_input'));
    }

    public function getAbsencesDeductionAttribute()
    {
        $type = DeductionType::where('code', 'ABSENCES')->first();
        if (!$type) return 0.00;
        return (float) $this->payOuts()->where('deduction_type_id', $type->id)->sum(DB::raw('rate * number_input'));
    }

    public function getAbsencesAttribute()
    {
        return $this->absences_deduction;
    }

    public function getOtherDeductionsAttribute()
    {
        $type = DeductionType::where('code', 'OTHER')->first();
        if (!$type) return 0.00;
        return (float) $this->payOuts()->where('deduction_type_id', $type->id)->sum(DB::raw('rate * number_input'));
    }

    public function getCustomDeductionsAttribute(): array
    {
        $customs = [];
        $systemKeys = ['sss', 'philhealth', 'pagibig', 'tax', 'tardiness', 'undertime'];
        $outs = $this->payOuts()->with('deductionType')->get();
        foreach ($outs as $out) {
            $sysKey = $out->deductionType->system_key ?? null;
            if (!in_array($sysKey, $systemKeys, true) && $out->deduction_type_id) {
                $customs[$out->deduction_type_id] = (float) ($out->rate * ($out->number_input ?? 1.00));
            }
        }
        return $customs;
    }

    public function getCustomIncomesAttribute(): array
    {
        $customs = [];
        $ins = $this->payIns()->with('incomeType')->get();
        foreach ($ins as $in) {
            $sysKey = $in->incomeType->system_key ?? null;
            if (!in_array($sysKey, ['basic', 'overtime'], true) && $in->income_type_id) {
                $customs[$in->income_type_id] = (float) ($in->rate * ($in->number_input ?? 1.00));
            }
        }
        return $customs;
    }

    // Accessors for Inputs/Hours
    public function getDaysWorkedAttribute()
    {
        if (array_key_exists('days_worked', $this->attributes) && $this->attributes['days_worked'] !== null && (float) $this->attributes['days_worked'] > 0) {
            return (float) $this->attributes['days_worked'];
        }

        // If days_worked is 0 or null (recurring income mode — no attendance form),
        // fall back to the number_input from the Basic Pay payIn record
        $basicPayNumber = $this->payIns()
            ->whereHas('incomeType', fn ($q) => $q->where('system_key', 'basic'))
            ->value('number_input');

        if ($basicPayNumber !== null && (float) $basicPayNumber > 0) {
            return (float) $basicPayNumber;
        }

        return (float) ($this->attributes['days_worked'] ?? 0.00);
    }

    public function getOvertimeHoursAttribute()
    {
        if (array_key_exists('overtime_hours', $this->attributes) && $this->attributes['overtime_hours'] !== null) {
            return (float) $this->attributes['overtime_hours'];
        }
        return (float) ($this->payIns()
            ->whereHas('incomeType', fn ($q) => $q->where('system_key', 'overtime'))
            ->value('number_input') ?? 0.00);
    }

    public function getDaysAbsentAttribute()
    {
        if (array_key_exists('days_absent', $this->attributes) && $this->attributes['days_absent'] !== null) {
            return (float) $this->attributes['days_absent'];
        }
        return 0.00;
    }

    public function getLateHoursAttribute()
    {
        if (array_key_exists('late_hours', $this->attributes) && $this->attributes['late_hours'] !== null) {
            return (float) $this->attributes['late_hours'];
        }
        return (float) ($this->payOuts()
            ->whereHas('deductionType', fn ($q) => $q->where('system_key', 'tardiness'))
            ->value('number_input') ?? 0.00);
    }

    public function getUndertimeHoursAttribute()
    {
        return (float) ($this->payOuts()
            ->whereHas('deductionType', fn ($q) => $q->where('system_key', 'undertime'))
            ->value('number_input') ?? 0.00);
    }

    public function getGrossPayAttribute()
    {
        if (array_key_exists('total_gross_pay', $this->attributes) && $this->attributes['total_gross_pay'] !== null) {
            return (float) $this->attributes['total_gross_pay'];
        }
        $customSum = array_sum($this->custom_incomes);
        return (float)$this->basic_pay + (float)$this->overtime_pay + (float)$this->total_allowances + (float)$this->total_bonuses + (float)$this->other_income + $customSum;
    }

    public function getTotalDeductionsAttribute()
    {
        if (array_key_exists('total_deductions', $this->attributes) && $this->attributes['total_deductions'] !== null) {
            return (float) $this->attributes['total_deductions'];
        }
        $customSum = array_sum($this->custom_deductions);
        return (float)$this->sss_contribution + (float)$this->philhealth_contribution + (float)$this->pagibig_contribution + (float)$this->withholding_tax + (float)$this->sss_loan_deduction + (float)$this->pagibig_loan_deduction + (float)$this->vale_loan_deduction + (float)$this->goods_purchase_deduction + (float)$this->late_deduction + (float)$this->undertime_deduction + (float)$this->absences_deduction + (float)$this->other_deductions + $customSum;
    }

    public function getNetPayAttribute()
    {
        if (array_key_exists('net_pay', $this->attributes) && $this->attributes['net_pay'] !== null) {
            return (float) $this->attributes['net_pay'];
        }
        return $this->gross_pay - $this->total_deductions;
    }

    // Helper Methods
    public function canEdit(): bool
    {
        return in_array($this->status, ['draft', 'rejected'], true);
    }

    public function canBeEditedBy(?User $user = null): bool
    {
        if ($this->canEdit()) {
            return true;
        }

        $user = $user ?? auth()->user();
        if (!$user) {
            return false;
        }

        if ($this->status === 'pending_approval' && MenuRoleAccess::hasModuleAccess($user, 'payroll_locked_edit_override')) {
            return true;
        }

        return false;
    }

    public function canSubmit(): bool
    {
        return in_array($this->status, ['draft', 'rejected'], true);
    }

    public function canApprove(): bool
    {
        return $this->status === 'pending_approval';
    }

    public function getPayrollDateAttribute()
    {
        return $this->payrollPeriod?->pay_date;
    }

    public function getPayDateAttribute()
    {
        return $this->payrollPeriod?->pay_date;
    }

    // Legacy compatibility aliases
    public function getAllowancesAttribute() { return $this->total_allowances; }
    public function getBonusesAttribute() { return $this->total_bonuses; }
    public function getGrossIncomeAttribute() { return $this->total_gross_pay; }
    public function getSssLoanAttribute() { return $this->sss_loan_deduction; }
    public function getPagibigLoanAttribute() { return $this->pagibig_loan_deduction; }
    public function getValeDeductionAttribute() { return $this->vale_loan_deduction; }
    public function getGoodsDeductionAttribute() { return $this->goods_purchase_deduction; }

    // Local Query Scopes
    public function scopeDraft($query) { return $query->where('status', 'draft'); }
    public function scopePendingApproval($query) { return $query->where('status', 'pending_approval'); }
    public function scopeApproved($query) { return $query->where('status', 'approved'); }
    public function scopeRejected($query) { return $query->where('status', 'rejected'); }
    public function scopeFinalReports($query) { return $query->whereIn('status', self::FINAL_REPORT_STATUSES); }
}
