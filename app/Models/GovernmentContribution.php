<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\HasCompanyScope;

class GovernmentContribution extends Model
{
    use HasFactory, HasCompanyScope;

    public const STATUS_PENDING = 'pending';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_PAID = 'paid';

    public const VALID_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_SUBMITTED,
        self::STATUS_PAID,
    ];

    protected $fillable = [
        'employee_id',
        'company_id',
        'payroll_period_id',
        'payroll_draft_id',
        'type',
        'employee_share',
        'employer_share',
        'total_contribution',
        'period_start',
        'period_end',
        'payment_date',
        'status',
        'notes',
    ];

    protected $casts = [
        'employee_share' => 'decimal:2',
        'employer_share' => 'decimal:2',
        'total_contribution' => 'decimal:2',
        'period_start' => 'date',
        'period_end' => 'date',
        'payment_date' => 'date',
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

    public function payrollPeriod()
    {
        return $this->belongsTo(PayrollPeriod::class);
    }

    public function payrollDraft()
    {
        return $this->belongsTo(PayrollDraft::class);
    }

    public static function normalizeStatus(?string $status, string $default = self::STATUS_PENDING): string
    {
        if ($status !== null && in_array(strtolower($status), self::VALID_STATUSES, true)) {
            return strtolower($status);
        }

        return $default;
    }

    // Scopes
    public function scopeSSS($query)
    {
        return $query->where('type', 'sss');
    }

    public function scopePhilHealth($query)
    {
        return $query->where('type', 'philhealth');
    }

    public function scopePagIbig($query)
    {
        return $query->where('type', 'pagibig');
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeSubmitted($query)
    {
        return $query->where('status', self::STATUS_SUBMITTED);
    }

    public function scopePaid($query)
    {
        return $query->where('status', self::STATUS_PAID);
    }
}
