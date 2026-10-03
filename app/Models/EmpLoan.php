<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Traits\HasCompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmpLoan extends Model
{
    use HasCompanyScope;

    public $timestamps = false;

    protected $fillable = [
        'employee_id',
        'loan_type_id',
        'principal_amount',
        'current_balance',
        'total_amount',
        'per_cutoff_amount',
        'amount_paid',
        'status',
        'start_date',
        'notes',
        'purpose',
        'terms_months',
        'monthly_interest_rate',
        'interest_amount',
    ];

    protected $casts = [
        'principal_amount' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'per_cutoff_amount' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'start_date' => 'date',
        'terms_months' => 'integer',
        'monthly_interest_rate' => 'decimal:2',
        'interest_amount' => 'decimal:2',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(EmployeeMaster::class, 'employee_id')->withoutGlobalScopes();
    }

    public function loanType(): BelongsTo
    {
        return $this->belongsTo(LoanType::class, 'loan_type_id')->withoutGlobalScopes();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(LoanPayment::class, 'emp_loan_id');
    }

    public function recalculateBalance(): void
    {
        $totalPaid = (float) $this->payments()->sum('amount_paid');
        $total = (float) ($this->attributes['total_amount'] ?? $this->principal_amount ?? 0);
        $this->amount_paid = $totalPaid;
        $newBalance = max(0.00, round($total - $totalPaid, 2));
        $this->current_balance = $newBalance;
        $this->status = ($newBalance <= 0) ? 'PAID' : 'ACTIVE';
        $this->save();
    }

    public function getLoanDateAttribute()
    {
        return $this->attributes['start_date'] ?? '2026-07-01';
    }

    public function getPurchaseDateAttribute()
    {
        return $this->attributes['start_date'] ?? '2026-07-01';
    }

    public function getStartDateAttribute()
    {
        $val = $this->attributes['start_date'] ?? null;
        return $val ? \Carbon\Carbon::parse($val) : \Carbon\Carbon::parse('2026-07-01');
    }

    public function getNotesAttribute()
    {
        return $this->attributes['notes'] ?? $this->loanType?->name ?? 'Company Loan';
    }

    public function getTotalAmountAttribute()
    {
        return (float) ($this->attributes['total_amount'] ?? $this->principal_amount ?? 0.00);
    }

    public function getBalanceAttribute()
    {
        return (float) ($this->current_balance ?? 0.00);
    }

    public function getPerCutoffAmountAttribute()
    {
        $amt = (float) ($this->attributes['per_cutoff_amount'] ?? 0.00);
        if ($amt <= 0 && $this->loanType) {
            $amt = (float) ($this->loanType->default_amortization_per_cutoff ?? 0.00);
        }
        return $amt;
    }

    public function getInstallmentAmountAttribute()
    {
        return $this->per_cutoff_amount;
    }

    public function getStatusAttribute()
    {
        $stat = $this->attributes['status'] ?? null;
        if (!empty($stat)) {
            return strtoupper($stat);
        }
        return (float) $this->current_balance > 0 ? 'ACTIVE' : 'PAID';
    }

    public function getTypeAttribute()
    {
        return $this->loanType?->name ?? 'Loan';
    }

    public function getDeductionPerCutoffAttribute()
    {
        return $this->per_cutoff_amount;
    }

    public function getItemDescriptionAttribute()
    {
        return $this->loanType?->name ?? 'Goods Purchase';
    }

    public function getMonthlyDeductionAttribute()
    {
        return $this->per_cutoff_amount;
    }
}
