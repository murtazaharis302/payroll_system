<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'company_id',
        'biometric_id',
        'log_date',
        'log_time',
        'log_datetime',
        'log_type',
        'device_name',
        'device_serial',
        'import_status',
        'import_batch',
        'imported_by',
        'imported_at',
    ];

    protected $casts = [
        'log_date' => 'date',
        'log_datetime' => 'datetime',
        'imported_at' => 'datetime',
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

    public function importer()
    {
        return $this->belongsTo(User::class, 'imported_by');
    }
}
