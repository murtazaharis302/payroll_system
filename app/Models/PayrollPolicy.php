<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollPolicy extends Model
{
    use HasFactory;

    protected $table = 'payroll_policies';

    protected $fillable = [
        'company_id',
        'policy_name',
        'pay_frequency',
        'workdays_per_month',
        'hours_per_day',
        'regular_ot_multiplier',
        'rest_day_ot_multiplier',
        'holiday_ot_multiplier',
        'rounding_mode',
        'no_attendance_policy',
        'effective_date',
        'is_active',
    ];

    protected $casts = [
        'workdays_per_month' => 'decimal:2',
        'hours_per_day' => 'decimal:2',
        'regular_ot_multiplier' => 'decimal:4',
        'rest_day_ot_multiplier' => 'decimal:4',
        'holiday_ot_multiplier' => 'decimal:4',
        'effective_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Resolve active effective policy for a company as of a date.
     */
    public static function forCompany(?int $companyId, ?string $date = null): self
    {
        $targetDate = $date ? date('Y-m-d', strtotime($date)) : date('Y-m-d');

        if ($companyId) {
            $policy = static::where('company_id', $companyId)
                ->where('is_active', true)
                ->where('effective_date', '<=', $targetDate)
                ->orderBy('effective_date', 'desc')
                ->orderBy('id', 'desc')
                ->first();

            if ($policy) {
                return $policy;
            }
        }

        return static::makeDefaultPolicy($companyId);
    }

    /**
     * Create an unpersisted default policy object with standard defaults.
     */
    public static function makeDefaultPolicy(?int $companyId = null): self
    {
        return new static([
            'company_id' => $companyId,
            'policy_name' => 'Default Standard Payroll Policy',
            'pay_frequency' => 'semi_monthly',
            'workdays_per_month' => 26.00,
            'hours_per_day' => 8.00,
            'regular_ot_multiplier' => 1.2500,
            'rest_day_ot_multiplier' => 1.3000,
            'holiday_ot_multiplier' => 2.0000,
            'rounding_mode' => 'round_half_up',
            'no_attendance_policy' => 'zero_pay_requires_review',
            'effective_date' => '2020-01-01',
            'is_active' => true,
        ]);
    }

    /**
     * Calculate daily rate for an employee based on policy.
     */
    public function dailyRate(EmployeeMaster $employee): float
    {
        if ((float) $employee->daily_rate > 0) {
            return (float) $employee->daily_rate;
        }

        if ((float) $employee->monthly_rate > 0) {
            $workdays = (float) $this->workdays_per_month > 0 ? (float) $this->workdays_per_month : 26.0;
            return round((float) $employee->monthly_rate / $workdays, 4);
        }

        if ((float) $employee->hourly_rate > 0) {
            $hours = (float) $this->hours_per_day > 0 ? (float) $this->hours_per_day : 8.0;
            return round((float) $employee->hourly_rate * $hours, 4);
        }

        return 0.00;
    }

    /**
     * Calculate hourly rate for an employee based on policy.
     */
    public function hourlyRate(EmployeeMaster $employee): float
    {
        if ((float) $employee->hourly_rate > 0) {
            return (float) $employee->hourly_rate;
        }

        if ((float) $employee->daily_rate > 0) {
            $hours = (float) $this->hours_per_day > 0 ? (float) $this->hours_per_day : 8.0;
            return round((float) $employee->daily_rate / $hours, 4);
        }

        if ((float) $employee->monthly_rate > 0) {
            $workdays = (float) $this->workdays_per_month > 0 ? (float) $this->workdays_per_month : 26.0;
            $hours = (float) $this->hours_per_day > 0 ? (float) $this->hours_per_day : 8.0;
            return round((float) $employee->monthly_rate / $workdays / $hours, 4);
        }

        return 0.00;
    }

    /**
     * Calculate overtime rate for an employee based on policy and day type.
     */
    public function overtimeRate(EmployeeMaster $employee, string $dayType = 'regular'): float
    {
        $hourly = $this->hourlyRate($employee);

        $multiplier = match (strtolower($dayType)) {
            'rest_day', 'rest' => (float) $this->rest_day_ot_multiplier,
            'holiday' => (float) $this->holiday_ot_multiplier,
            default => (float) $this->regular_ot_multiplier,
        };

        return round($hourly * $multiplier, 4);
    }
}
