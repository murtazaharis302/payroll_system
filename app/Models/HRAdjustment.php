<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HRAdjustment extends Model
{
    use HasFactory;

    protected $table = 'hr_adjustments';

    protected $fillable = [
        'employee_id',
        'company_id',
        'adjustment_type',
        'attendance_date',
        'time_in',
        'time_out',
        'hours_worked',
        'overtime_hours',
        'income_type_id',
        'deduction_type_id',
        'amount',
        'reason',
        'notes',
        'effective_date',
        'status',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'effective_date' => 'date',
        'approved_at' => 'datetime',
        'amount' => 'decimal:2',
        'hours_worked' => 'decimal:2',
        'overtime_hours' => 'decimal:2',
    ];

    // Relationships
    public function employee()
    {
        return $this->belongsTo(EmployeeMaster::class, 'employee_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function incomeType()
    {
        return $this->belongsTo(IncomeType::class);
    }

    public function deductionType()
    {
        return $this->belongsTo(DeductionType::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
