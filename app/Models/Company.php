<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Company extends Model
{
    protected $table = 'companies';
    protected $fillable = [
        'user_id',
        'company_code',
        'name',
        'email',
        'phone',
        'website',
        'address',
        'status',
        'detail',
    ];
    
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id','id');
    }
    
    public function country()
    {
        return $this->belongsTo(Country::class);
    }
    
    public function userAccess()
    {
        return $this->hasMany(UserCompanyAccess::class);
    }
    
    public function employees()
    {
        return $this->hasMany(EmployeeMaster::class);
    }

    public function bankSettings()
    {
        return $this->hasMany(CompanyBankSetting::class);
    }
    
    public function governmentContributions()
    {
        return $this->hasMany(GovernmentContribution::class);
    }
    
    public function empLoans()
    {
        return $this->hasManyThrough(EmpLoan::class, EmployeeMaster::class, 'company_id', 'employee_id');
    }

    /**
     * Get company-wide deduction defaults from detail column.
     */
    public function getDeductionDefaultsAttribute(): array
    {
        $detailData = json_decode($this->detail ?? '{}', true) ?? [];
        return $detailData['deduction_defaults'] ?? [
            'use_fixed_amount' => false,
            'deductions' => []
        ];
    }

    /**
     * Store company-wide deduction defaults into detail column.
     */
    public function setDeductionDefaults(array $defaults): void
    {
        $detailData = json_decode($this->detail ?? '{}', true) ?? [];
        $detailData['deduction_defaults'] = $defaults;
        $this->detail = json_encode($detailData);
        $this->save();
    }

    /**
     * Get company-wide income defaults from detail column.
     */
    public function getIncomeDefaultsAttribute(): array
    {
        $detailData = json_decode($this->detail ?? '{}', true) ?? [];
        return $detailData['income_defaults'] ?? [
            'use_fixed_income_amount' => false,
            'incomes' => []
        ];
    }

    /**
     * Store company-wide income defaults into detail column.
     */
    public function setIncomeDefaults(array $defaults): void
    {
        $detailData = json_decode($this->detail ?? '{}', true) ?? [];
        $detailData['income_defaults'] = $defaults;
        $this->detail = json_encode($detailData);
        $this->save();
    }
    
    /**
     * Accessor to map company_name to name attribute dynamically.
     */
    public function getCompanyNameAttribute()
    {
        return $this->name;
    }

    /**
     * Accessor for company address.
     */
    public function getCompanyAddressAttribute()
    {
        return $this->attributes['address'] ?? (json_decode($this->attributes['detail'] ?? '{}', true)['address'] ?? '');
    }

    /**
     * Accessor for address attribute.
     */
    public function getAddressAttribute($value)
    {
        return $value ?? (json_decode($this->attributes['detail'] ?? '{}', true)['address'] ?? '');
    }

    /**
     * Generate unique company code (COMP-0001, COMP-0002, etc.)
     */
    public static function generateCompanyCode()
    {
        $lastCompany = self::orderBy('id', 'desc')->first();
        $nextNumber = $lastCompany ? ($lastCompany->id + 1) : 1;
        $code = 'COMP-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

        while (self::where('company_code', $code)->exists()) {
            $nextNumber++;
            $code = 'COMP-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
        }

        return $code;
    }
    
    /**
     * Boot method to auto-generate company code on creation
     */
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($company) {
            if (empty($company->company_code)) {
                $company->company_code = self::generateCompanyCode();
            }
        });
    }
}
