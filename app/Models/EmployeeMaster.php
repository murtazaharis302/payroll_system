<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\HasCompanyScope;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeMaster extends Model
{
    use HasFactory, HasCompanyScope;

    protected $table = 'employees_master';

    protected $fillable = [
        'company_id',
        'employee_code',
        'first_name',
        'middle_name',
        'last_name',
        'email',
        'phone',
        'date_of_birth',
        'date_hired',
        'position',
        'department',
        'dept_id',
        'sub_dept_id',
        'acct_num',
        'atm_num',
        'monthly_rate',
        'daily_rate',
        'hourly_rate',
        'sss_number',
        'philhealth_number',
        'pagibig_number',
        'tin_number',
        'tax_code',
        'remarks',
        'biometric_id',
        'status',
    ];

    protected $appends = ['basic_salary'];

    protected $casts = [
        'date_of_birth' => 'date',
        'date_hired' => 'date',
        'monthly_rate' => 'decimal:2',
        'daily_rate' => 'decimal:2',
        'hourly_rate' => 'decimal:2',
    ];

    public function getBasicSalaryAttribute()
    {
        return $this->attributes['monthly_rate'] ?? 0.00;
    }

    public function setBasicSalaryAttribute($value)
    {
        $this->attributes['monthly_rate'] = $value;
    }

    // Relationships
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function dept()
    {
        return $this->belongsTo(Department::class, 'dept_id');
    }

    public function subDepartment()
    {
        return $this->belongsTo(SubDepartment::class, 'sub_dept_id');
    }

    public function empIncomes()
    {
        return $this->hasMany(EmpIncome::class, 'employee_id');
    }

    public function empDeductions()
    {
        return $this->hasMany(EmpDeduction::class, 'employee_id');
    }

    public function empLoans(): HasMany
    {
        return $this->hasMany(EmpLoan::class, 'employee_id');
    }

    public function loans(): HasMany
    {
        return $this->hasMany(EmpLoan::class, 'employee_id');
    }

    public function loanRequests(): HasMany
    {
        return $this->hasMany(LoanRequest::class, 'employee_id');
    }

    public function payHeaders(): HasMany
    {
        return $this->hasMany(PayHeader::class, 'employee_id');
    }

    public function attendanceLogs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class, 'employee_id');
    }

    public function governmentContributions(): HasMany
    {
        return $this->hasMany(GovernmentContribution::class, 'employee_id');
    }

    public function payrollDrafts(): HasMany
    {
        return $this->hasMany(PayrollDraft::class, 'employee_id');
    }

    // Helper Methods
    public function getFullNameAttribute()
    {
        $middleInitial = '';
        if (!empty($this->middle_name)) {
            $initial = strtoupper(substr(trim($this->middle_name), 0, 1));
            $middleInitial = ' ' . $initial . '.';
        }

        if (!empty($this->last_name)) {
            return $this->last_name . ', ' . $this->first_name . $middleInitial;
        }

        return implode(' ', array_filter([$this->first_name, $this->middle_name, $this->last_name]));
    }

    public function isActive()
    {
        return $this->status === 'active';
    }

    public static function generateNextEmployeeCode(?int $companyId = null): string
    {
        $maxId = (int) static::withoutGlobalScopes()->max('id');
        $next = $maxId + 1;
        $code = 'EMP-' . str_pad((string)$next, 4, '0', STR_PAD_LEFT);

        while (static::withoutGlobalScopes()->where('employee_code', $code)->exists()) {
            $next++;
            $code = 'EMP-' . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
        }

        return $code;
    }
}
