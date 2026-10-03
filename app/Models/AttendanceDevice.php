<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AttendanceDevice extends Model
{
    use HasFactory;

    protected $table = 'attendance_devices';

    protected $fillable = [
        'public_id',
        'company_id',
        'name',
        'server_address',
        'upload_token',
        'next_upload_pos',
        'is_active',
        'last_seen_at',
        'last_upload_at',
        'last_start_pos',
        'last_next_pos',
        'last_result',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_seen_at' => 'datetime',
        'last_upload_at' => 'datetime',
        'last_result' => 'array',
        'upload_token' => 'encrypted',
        'next_upload_pos' => 'integer',
        'last_start_pos' => 'integer',
        'last_next_pos' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (AttendanceDevice $device) {
            if (empty($device->public_id)) {
                $device->public_id = (string) Str::uuid();
            }
            if (empty($device->upload_token)) {
                $device->upload_token = Str::random(40);
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function userMappings(): HasMany
    {
        return $this->hasMany(AttendanceDeviceUserMapping::class, 'attendance_device_id');
    }

    public function pendingLogs(): HasMany
    {
        return $this->hasMany(AttendanceDevicePendingLog::class, 'attendance_device_id');
    }

    public function attendanceLogs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class, 'device_serial', 'deli_device_serial');
    }

    public function getDeliDeviceSerialAttribute(): string
    {
        return 'DELI:' . $this->public_id;
    }

    /**
     * Normalize a server address: validates base scheme + host/port only.
     */
    public static function normalizeServerAddress(?string $address): ?string
    {
        if ($address === null || trim($address) === '') {
            return null;
        }

        $address = rtrim(trim($address), '/');

        if (!str_starts_with($address, 'http://') && !str_starts_with($address, 'https://')) {
            throw new \InvalidArgumentException('Server address must start with http:// or https://');
        }

        $parsed = parse_url($address);
        if ($parsed === false || empty($parsed['host'])) {
            throw new \InvalidArgumentException('Server address must contain a valid domain or IP address.');
        }

        $host = $parsed['host'];
        if ($host === 'localhost' || $host === '127.0.0.1') {
            throw new \InvalidArgumentException('Localhost (127.0.0.1) cannot be reached by a physical biometric device. Please use your server IP or domain name.');
        }

        // Reject paths beyond an empty string or single '/'
        if (!empty($parsed['path']) && $parsed['path'] !== '/') {
            throw new \InvalidArgumentException('Server address must be base domain or IP only (e.g. http://192.168.1.50:8000 or https://payroll.company.com). Do not include URL paths.');
        }

        // Reject query, fragment, credentials
        if (!empty($parsed['query']) || !empty($parsed['fragment']) || !empty($parsed['user']) || !empty($parsed['pass'])) {
            throw new \InvalidArgumentException('Server address must not contain query parameters, fragments, or user credentials.');
        }

        $scheme = $parsed['scheme'];
        $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';

        return "{$scheme}://{$host}{$port}";
    }

    /**
     * Suggest default server address from config or request (returns null if only local).
     */
    public static function getDefaultServerAddress(): ?string
    {
        $appUrl = config('app.url');
        if (!empty($appUrl) && !str_contains($appUrl, 'localhost') && !str_contains($appUrl, '127.0.0.1')) {
            return rtrim($appUrl, '/');
        }

        if (request() && request()->getSchemeAndHttpHost()) {
            $reqHost = request()->getSchemeAndHttpHost();
            if (!str_contains($reqHost, 'localhost') && !str_contains($reqHost, '127.0.0.1')) {
                return rtrim($reqHost, '/');
            }
        }

        return null;
    }

    /**
     * Get deployment-agnostic target upload URL for DELI machine.
     * Returns null if no reachable base server address exists.
     */
    public function getUploadUrlAttribute(): ?string
    {
        $base = !empty($this->server_address)
            ? rtrim($this->server_address, '/')
            : self::getDefaultServerAddress();

        if (empty($base)) {
            return null;
        }

        return "{$base}/api/deli/attendance/{$this->public_id}/{$this->upload_token}";
    }

    public function rotateToken(): string
    {
        $newToken = Str::random(40);
        $this->upload_token = $newToken;
        $this->save();

        return $newToken;
    }
}
