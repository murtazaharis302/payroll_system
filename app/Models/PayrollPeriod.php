<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\HasCompanyScope;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollPeriod extends Model
{
    use HasFactory, HasCompanyScope;

    protected $fillable = [
        'company_id',
        'period_name',
        'period_start',
        'period_end',
        'pay_date',
        'status',
        'processed_by',
        'processed_at',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'pay_date' => 'date',
        'processed_at' => 'datetime',
    ];

    // Relationships
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function processor()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function payHeaders(): HasMany
    {
        return $this->hasMany(PayHeader::class, 'payroll_period_id');
    }

    public function transactions(): HasMany
    {
        return $this->payHeaders();
    }

    // Helper Methods
    public function isOpen()
    {
        return $this->status === 'open';
    }

    public function isCompleted()
    {
        return $this->status === 'completed';
    }

    public function getTotalPayrollAttribute()
    {
        return 0;
    }
}
