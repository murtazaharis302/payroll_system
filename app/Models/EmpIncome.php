<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmpIncome extends Model
{
    use HasFactory;

    protected $table = 'emp_income';

    protected $fillable = [
        'employee_id',
        'income_type_id',
        'rate',
        'amount',
        'frequency',
        'effective_date',
        'is_active',
    ];

    protected $appends = ['amount'];

    protected $casts = [
        'rate' => 'decimal:2',
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

    public function incomeType()
    {
        return $this->belongsTo(IncomeType::class, 'income_type_id');
    }
}
