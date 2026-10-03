<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmpDeduction extends Model
{
    use HasFactory;

    protected $table = 'emp_deductions';

    protected $fillable = [
        'employee_id',
        'deduction_type_id',
        'rate',
        'amount',
        'frequency',
        'start_date',
        'end_date',
        'effective_date',
        'is_active',
    ];

    protected $appends = ['amount'];

    protected $casts = [
        'rate' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'effective_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function getAmountAttribute()
    {
        return $this->attributes['rate'] ?? $this->attributes['amount'] ?? 0.00;
    }

    public function setAmountAttribute($value)
    {
        $this->attributes['rate'] = $value;
        if (array_key_exists('amount', $this->attributes)) {
            $this->attributes['amount'] = $value;
        }
    }

    // Relationships
    public function employee()
    {
        return $this->belongsTo(EmployeeMaster::class, 'employee_id');
    }

    public function deductionType()
    {
        return $this->belongsTo(DeductionType::class, 'deduction_type_id');
    }
}
