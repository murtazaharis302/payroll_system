<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'company_id',
        'action',
        'module',
        'record_type',
        'record_id',
        'old_value',
        'new_value',
        'ip_address',
        'user_agent',
        'description',
    ];

    protected $casts = [
        'old_value' => 'array',
        'new_value' => 'array',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    // Helper Methods
    public static function log($action, $module, $description = null, $recordType = null, $recordId = null, $oldValue = null, $newValue = null, $companyId = null, $userId = null)
    {
        $resolvedCompanyId = $companyId 
            ?? (auth()->check() ? auth()->user()->company_id : null) 
            ?? (function_exists('getActiveCompanyId') ? getActiveCompanyId() : null) 
            ?? session('active_company_id');

        return self::create([
            'user_id'     => $userId ?? auth()->id(),
            'company_id'  => $resolvedCompanyId,
            'action'      => $action,
            'module'      => $module,
            'record_type' => $recordType,
            'record_id'   => $recordId,
            'old_value'   => $oldValue,
            'new_value'   => $newValue,
            'ip_address'  => request()?->ip(),
            'user_agent'  => request()?->userAgent(),
            'description' => $description,
        ]);
    }
}
