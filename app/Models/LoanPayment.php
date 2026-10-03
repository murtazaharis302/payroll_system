<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanPayment extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'emp_loan_id',
        'pay_id',
        'pay_header_id',
        'amount_paid',
        'payment_date',
        'notes',
        'source',
    ];

    protected $casts = [
        'amount_paid' => 'decimal:2',
        'payment_date' => 'date',
    ];

    public function empLoan(): BelongsTo
    {
        return $this->belongsTo(EmpLoan::class, 'emp_loan_id');
    }

    public function payHeader(): BelongsTo
    {
        return $this->belongsTo(PayHeader::class, 'pay_header_id')->withoutGlobalScopes();
    }

    public function getAmountAttribute()
    {
        return (float) ($this->amount_paid ?? 0.00);
    }

    public function setAmountAttribute($value)
    {
        $this->attributes['amount_paid'] = $value;
    }

    public function getNotesAttribute()
    {
        if (!empty($this->attributes['notes'])) {
            return $this->attributes['notes'];
        }

        $headerId = $this->attributes['pay_header_id'] ?? $this->attributes['pay_id'] ?? null;
        if ($headerId) {
            $header = PayHeader::find($headerId);
            if ($header) {
                $periodName = $header->payrollPeriod?->period_name ?? null;
                return 'Payroll Deduction' . ($periodName ? ' (' . $periodName . ')' : '');
            }
        }

        return 'Direct Repayment';
    }

    public function getPayrollPeriodNameAttribute(): ?string
    {
        $source = strtoupper((string) ($this->attributes['source'] ?? ''));
        if ($source === 'MANUAL') {
            return null;
        }

        $header = $this->payHeader;
        if (!$header && !empty($this->attributes['pay_id']) && $source === 'PAYROLL') {
            $header = PayHeader::withoutGlobalScopes()->find($this->attributes['pay_id']);
        }

        if (!$header && !empty($this->attributes['pay_header_id']) && $source !== 'MANUAL') {
            $header = PayHeader::withoutGlobalScopes()->find($this->attributes['pay_header_id']);
        }

        if (!$header) {
            return null;
        }

        return $header->payrollPeriod?->period_name;
    }
}
