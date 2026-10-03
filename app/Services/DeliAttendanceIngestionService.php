<?php

namespace App\Services;

use App\Models\AttendanceDevice;
use App\Models\AttendanceDevicePendingLog;
use App\Models\AttendanceDeviceUserMapping;
use App\Models\AttendanceLog;
use App\Models\EmployeeMaster;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DeliAttendanceIngestionService
{
    /**
     * Process an incoming DELI device request.
     *
     * @return array{status: int, body: mixed}
     */
    public function handleRequest(string $publicId, string $token, array $payload = [], bool $isSimulation = false): array
    {
        $device = AttendanceDevice::where('public_id', $publicId)->first();

        if (!$device || !hash_equals((string) $device->upload_token, (string) $token)) {
            return [
                'status' => 404,
                'body' => ['error' => 'Device or token invalid.'],
            ];
        }

        if (!$device->is_active) {
            return [
                'status' => 403,
                'body' => ['error' => 'Device is inactive.'],
            ];
        }

        if (empty($payload)) {
            return $this->handleHandshake($device, $isSimulation);
        }

        return $this->handleBatchUpload($device, $payload, $isSimulation);
    }

    /**
     * Handle DELI {} handshake request.
     */
    protected function handleHandshake(AttendanceDevice $device, bool $isSimulation): array
    {
        if (!$isSimulation) {
            $device->last_seen_at = now();
            $device->save();
        }

        if ($device->next_upload_pos === null) {
            return [
                'status' => 200,
                'body' => (object) [],
            ];
        }

        return [
            'status' => 200,
            'body' => [
                'next_upload_pos' => (int) $device->next_upload_pos,
            ],
        ];
    }

    /**
     * Single reusable method to create an AttendanceLog record.
     * Used by both live uploads and recovered pending punches.
     */
    public function insertResolvedPunch(
        AttendanceDevice $device,
        EmployeeMaster $employee,
        string $userIdStr,
        Carbon $dateTime,
        ?string $mode = null,
        ?int $startPos = null,
        ?int $nextPos = null,
        bool $isSimulation = false
    ): bool {
        $logDate = $dateTime->toDateString();
        $logTime = $dateTime->toTimeString();

        // Duplicate check: employee_id + log_date + log_time
        $isDuplicate = AttendanceLog::where('employee_id', $employee->id)
            ->whereDate('log_date', $logDate)
            ->where(function ($q) use ($logTime) {
                $q->where('log_time', $logTime)
                    ->orWhereTime('log_time', $logTime);
            })
            ->exists();

        if ($isDuplicate) {
            return false;
        }

        // IN / OUT determination: first log of the day is 'in', subsequent is 'out'
        $hasLogToday = AttendanceLog::where('employee_id', $employee->id)
            ->whereDate('log_date', $logDate)
            ->exists();

        $logType = $hasLogToday ? 'out' : 'in';

        if (!$isSimulation) {
            AttendanceLog::create([
                'employee_id' => $employee->id,
                'company_id' => $device->company_id,
                'biometric_id' => $userIdStr,
                'log_date' => $logDate,
                'log_time' => $logTime,
                'log_datetime' => $dateTime,
                'log_type' => $logType,
                'device_name' => $device->name,
                'device_serial' => 'DELI:' . $device->public_id,
                'import_status' => 'processed',
                'import_batch' => ($startPos !== null && $nextPos !== null)
                    ? "deli_{$device->public_id}_{$startPos}_{$nextPos}"
                    : "deli_recovered_{$device->public_id}_" . now()->format('YmdHis'),
                'imported_by' => null,
                'imported_at' => now(),
            ]);
        }

        return true;
    }

    /**
     * Handle DELI batch attendance upload.
     */
    protected function handleBatchUpload(AttendanceDevice $device, array $payload, bool $isSimulation): array
    {
        if (
            !isset($payload['start_pos']) ||
            !isset($payload['next_pos']) ||
            !isset($payload['logs']) ||
            !is_numeric($payload['start_pos']) ||
            !is_numeric($payload['next_pos']) ||
            !is_array($payload['logs'])
        ) {
            return [
                'status' => 422,
                'body' => ['error' => 'Malformed payload structure.'],
            ];
        }

        $startPos = (int) $payload['start_pos'];
        $nextPos = (int) $payload['next_pos'];
        $rawLogs = $payload['logs'];

        if ($startPos < 0 || $nextPos < $startPos) {
            return [
                'status' => 422,
                'body' => ['error' => 'Invalid start_pos or next_pos value.'],
            ];
        }

        try {
            return DB::transaction(function () use ($device, $startPos, $nextPos, $rawLogs, $isSimulation) {
                $lockedDevice = AttendanceDevice::where('id', $device->id)->lockForUpdate()->first() ?? $device;

                $received = count($rawLogs);
                $imported = 0;
                $duplicates = 0;
                $unmatched = 0;
                $ambiguous = 0;
                $invalid = 0;
                $unmatchedUserIds = [];

                foreach ($rawLogs as $log) {
                    if (!is_array($log) || !isset($log['user_id']) || !isset($log['time']) || $log['user_id'] === '' || $log['time'] === '') {
                        $invalid++;
                        continue;
                    }

                    try {
                        $dateTime = Carbon::parse($log['time']);
                    } catch (\Throwable $e) {
                        $invalid++;
                        continue;
                    }

                    $userIdStr = (string) $log['user_id'];
                    $resolvedEmployee = null;

                    // Tier 1: Explicit device user mapping
                    $mapping = AttendanceDeviceUserMapping::where('attendance_device_id', $lockedDevice->id)
                        ->where('device_user_id', $userIdStr)
                        ->first();

                    if ($mapping) {
                        $resolvedEmployee = EmployeeMaster::withoutGlobalScopes()
                            ->where('company_id', $lockedDevice->company_id)
                            ->find($mapping->employee_id);
                    }

                    // Tier 2: Biometric ID fallback (only when exactly one employee in company matches)
                    if (!$resolvedEmployee) {
                        $matchingEmployees = EmployeeMaster::withoutGlobalScopes()
                            ->where('company_id', $lockedDevice->company_id)
                            ->where('biometric_id', $userIdStr)
                            ->get();

                        if ($matchingEmployees->count() === 1) {
                            $resolvedEmployee = $matchingEmployees->first();
                            if (!$isSimulation) {
                                AttendanceDeviceUserMapping::firstOrCreate(
                                    ['attendance_device_id' => $lockedDevice->id, 'device_user_id' => $userIdStr],
                                    ['employee_id' => $resolvedEmployee->id]
                                );
                            }
                        } elseif ($matchingEmployees->count() > 1) {
                            $ambiguous++;
                            $unmatchedUserIds[] = "{$userIdStr} (ambiguous: {$matchingEmployees->count()} employees found)";
                        }
                    }

                    // Process punch or store in pending logs
                    if ($resolvedEmployee) {
                        $inserted = $this->insertResolvedPunch(
                            $lockedDevice,
                            $resolvedEmployee,
                            $userIdStr,
                            $dateTime,
                            $log['mode'] ?? null,
                            $startPos,
                            $nextPos,
                            $isSimulation
                        );

                        if ($inserted) {
                            $imported++;
                        } else {
                            $duplicates++;
                        }
                    } else {
                        // Unresolved / Ambiguous: Store pending log so punch is never lost
                        if (!$isSimulation) {
                            AttendanceDevicePendingLog::firstOrCreate(
                                [
                                    'attendance_device_id' => $lockedDevice->id,
                                    'device_user_id' => $userIdStr,
                                    'logged_at' => $dateTime,
                                ],
                                [
                                    'mode' => $log['mode'] ?? null,
                                    'start_pos' => $startPos,
                                    'next_pos' => $nextPos,
                                    'status' => 'pending',
                                    'raw_payload' => $log,
                                ]
                            );
                        }

                        if ($matchingEmployees->count() === 0) {
                            $unmatched++;
                            $unmatchedUserIds[] = $userIdStr;
                        }
                    }
                }

                // Replay calculation from locked row: pointer can never move backwards
                $currentStored = $lockedDevice->next_upload_pos !== null ? (int) $lockedDevice->next_upload_pos : null;
                $acknowledgedPointer = $currentStored !== null ? max($currentStored, $nextPos) : $nextPos;

                if (!$isSimulation) {
                    $lockedDevice->next_upload_pos = $acknowledgedPointer;
                    $lockedDevice->last_start_pos = $startPos;
                    $lockedDevice->last_next_pos = $nextPos;
                    $lockedDevice->last_upload_at = now();
                    $lockedDevice->last_seen_at = now();
                    $lockedDevice->last_result = [
                        'received' => $received,
                        'imported' => $imported,
                        'duplicates' => $duplicates,
                        'unmatched' => $unmatched,
                        'ambiguous' => $ambiguous,
                        'invalid' => $invalid,
                        'unmatched_user_ids' => array_values(array_unique($unmatchedUserIds)),
                    ];
                    $lockedDevice->save();
                }

                return [
                    'status' => 200,
                    'body' => [
                        'next_upload_pos' => $acknowledgedPointer,
                    ],
                ];
            });
        } catch (\Throwable $e) {
            Log::error('DELI attendance ingestion failed', [
                'device_id' => $device->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 500,
                'body' => ['error' => 'Internal server error during upload processing.'],
            ];
        }
    }

    /**
     * Process historical pending logs for a device user mapped to an employee.
     * Locks rows and processes chronologically by logged_at asc, id asc.
     */
    public function processPendingLogsForMapping(AttendanceDevice $device, string $deviceUserId, int $employeeId): int
    {
        $employee = EmployeeMaster::withoutGlobalScopes()
            ->where('company_id', $device->company_id)
            ->find($employeeId);

        if (!$employee) {
            throw new \InvalidArgumentException('Employee does not belong to this device company.');
        }

        return DB::transaction(function () use ($device, $deviceUserId, $employee) {
            $pendingLogs = AttendanceDevicePendingLog::where('attendance_device_id', $device->id)
                ->where('device_user_id', $deviceUserId)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->orderBy('logged_at', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            $recoveredCount = 0;

            foreach ($pendingLogs as $pendingLog) {
                $inserted = $this->insertResolvedPunch(
                    $device,
                    $employee,
                    $deviceUserId,
                    $pendingLog->logged_at,
                    $pendingLog->mode,
                    $pendingLog->start_pos,
                    $pendingLog->next_pos,
                    false
                );

                if ($inserted) {
                    $recoveredCount++;
                }

                $pendingLog->status = 'processed';
                $pendingLog->processed_at = now();
                $pendingLog->save();
            }

            return $recoveredCount;
        });
    }
}
