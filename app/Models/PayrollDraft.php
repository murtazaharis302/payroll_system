<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollDraft extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'payroll_period_id',
        'employee_id',
        'days_worked',
        'days_absent',
        'overtime_hours',
        'late_hours',
        'undertime_hours',
        'basic_pay',
        'overtime_pay',
        'total_allowances',
        'total_bonuses',
        'other_income',
        'gross_pay',
        'sss_contribution',
        'philhealth_contribution',
        'pagibig_contribution',
        'withholding_tax',
        'sss_loan_deduction',
        'pagibig_loan_deduction',
        'vale_loan_deduction',
        'goods_purchase_deduction',
        'late_deduction',
        'undertime_deduction',
        'other_deductions',
        'custom_deductions',
        'total_deductions',
        'net_pay',
        'status',
        'prepared_by',
        'approved_by',
        'submitted_at',
        'approved_at',
        'rejection_reason',
        'notes',
    ];

    protected $casts = [
        'days_worked' => 'decimal:2',
        'days_absent' => 'decimal:2',
        'overtime_hours' => 'decimal:2',
        'late_hours' => 'decimal:2',
        'undertime_hours' => 'decimal:2',
        'basic_pay' => 'decimal:2',
        'overtime_pay' => 'decimal:2',
        'total_allowances' => 'decimal:2',
        'total_bonuses' => 'decimal:2',
        'other_income' => 'decimal:2',
        'gross_pay' => 'decimal:2',
        'sss_contribution' => 'decimal:2',
        'philhealth_contribution' => 'decimal:2',
        'pagibig_contribution' => 'decimal:2',
        'withholding_tax' => 'decimal:2',
        'sss_loan_deduction' => 'decimal:2',
        'pagibig_loan_deduction' => 'decimal:2',
        'vale_loan_deduction' => 'decimal:2',
        'goods_purchase_deduction' => 'decimal:2',
        'late_deduction' => 'decimal:2',
        'undertime_deduction' => 'decimal:2',
        'other_deductions' => 'decimal:2',
        'custom_deductions' => 'array',
        'total_deductions' => 'decimal:2',
        'net_pay' => 'decimal:2',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    // Relationships
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function payrollPeriod()
    {
        return $this->belongsTo(PayrollPeriod::class);
    }

    public function employee()
    {
        return $this->belongsTo(EmployeeMaster::class, 'employee_id');
    }

    public function preparer()
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // Scopes
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopePendingApproval($query)
    {
        return $query->where('status', 'pending_approval');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    // Helper Methods
    public function canEdit()
    {
        return in_array($this->status, ['draft', 'rejected']);
    }

    public function canSubmit()
    {
        return $this->status === 'draft';
    }

    public function canApprove()
    {
        return $this->status === 'pending_approval';
    }
}
