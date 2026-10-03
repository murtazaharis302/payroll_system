<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceDevicePendingLog extends Model
{
    use HasFactory;

    protected $table = 'attendance_device_pending_logs';

    protected $fillable = [
        'attendance_device_id',
        'device_user_id',
        'logged_at',
        'mode',
        'start_pos',
        'next_pos',
        'status',
        'processed_at',
        'raw_payload',
    ];

    protected $casts = [
        'logged_at' => 'datetime',
        'processed_at' => 'datetime',
        'raw_payload' => 'array',
        'start_pos' => 'integer',
        'next_pos' => 'integer',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(AttendanceDevice::class, 'attendance_device_id');
    }
}
