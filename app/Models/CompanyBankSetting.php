<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyBankSetting extends Model
{
    protected $fillable = [
        'company_id',
        'bank_name',
        'branch_name',
        'manager_name',
        'account_number',
        'corporate_signee_1',
        'corporate_signee_1_title',
        'corporate_signee_2',
        'corporate_signee_2_title',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
