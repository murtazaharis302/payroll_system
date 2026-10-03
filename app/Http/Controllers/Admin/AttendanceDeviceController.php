<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceDevice;
use App\Models\AttendanceDevicePendingLog;
use App\Models\AttendanceDeviceUserMapping;
use App\Models\AttendanceLog;
use App\Models\Company;
use App\Models\EmployeeMaster;
use App\Models\Role;
use App\Services\DeliAttendanceIngestionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AttendanceDeviceController extends Controller
{
    public function __construct(
        protected DeliAttendanceIngestionService $ingestionService
    ) {}

    /**
     * Determine user permissions and company context.
     */
    protected function getAuthContext(?int $requestedCompanyId = null): array
    {
        $user = Auth::user();
        if (!$user) {
            abort(401);
        }

        $isSuperAdmin = $user->role === 'admin' || (int) $user->role_id === Role::SUPER_ADMIN;

        if ($isSuperAdmin) {
            $companyId = $requestedCompanyId ?: (getActiveCompanyId() ?: (Company::where('status', 1)->first()?->id ?? 1));
        } else {
            $companyId = (int) (getActiveCompanyId() ?: $user->company_id);
        }

        return [$user, $isSuperAdmin, $companyId];
    }

    /**
     * Authorize access to a specific device.
     */
    protected function authorizeDevice(AttendanceDevice $device): void
    {
        $user = Auth::user();
        $isSuperAdmin = $user->role === 'admin' || (int) $user->role_id === Role::SUPER_ADMIN;

        if (!$isSuperAdmin) {
            $activeCompanyId = (int) (getActiveCompanyId() ?: $user->company_id);
            if ((int) $device->company_id !== $activeCompanyId) {
                abort(403, 'Unauthorized access to this device.');
            }
        }
    }

    /**
     * Display listing of devices and readiness metrics.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            abort(401);
        }

        $isSuperAdmin = $user->role === 'admin' || (int) $user->role_id === Role::SUPER_ADMIN;
        $companies = $isSuperAdmin ? Company::where('status', 1)->orderBy('name')->get() : collect();

        if ($isSuperAdmin) {
            $requestedCompany = $request->input('company_id');
            if ($requestedCompany === 'all') {
                $selectedCompanyId = 'all';
                $devices = AttendanceDevice::with('company')->latest()->get();
                $statsCompanyId = null;
            } elseif ($request->filled('company_id')) {
                $selectedCompanyId = (int) $requestedCompany;
                $devices = AttendanceDevice::with('company')->where('company_id', $selectedCompanyId)->latest()->get();
                $statsCompanyId = $selectedCompanyId;
            } else {
                $selectedCompanyId = Company::where('status', 1)->first()?->id ?? (getActiveCompanyId() ?? 1);
                $devices = AttendanceDevice::with('company')->where('company_id', $selectedCompanyId)->latest()->get();
                $statsCompanyId = $selectedCompanyId;
            }
        } else {
            $selectedCompanyId = (int) (getActiveCompanyId() ?: $user->company_id);
            $devices = AttendanceDevice::with('company')->where('company_id', $selectedCompanyId)->latest()->get();
            $statsCompanyId = $selectedCompanyId;
        }

        // Attach diagnostic metrics to each device
        foreach ($devices as $dev) {
            $dev->mapped_users_count = AttendanceDeviceUserMapping::where('attendance_device_id', $dev->id)->count();
            $dev->pending_punches_count = AttendanceDevicePendingLog::where('attendance_device_id', $dev->id)->where('status', 'pending')->count();
            $dev->unmapped_users_count = AttendanceDevicePendingLog::where('attendance_device_id', $dev->id)->where('status', 'pending')->distinct('device_user_id')->count('device_user_id');
        }

        // Biometric readiness metrics for selected company (if a single company is selected)
        if ($statsCompanyId !== null) {
            $allEmployeesInCompany = EmployeeMaster::withoutGlobalScopes()
                ->where('company_id', $statsCompanyId)
                ->get(['id', 'first_name', 'last_name', 'employee_code', 'biometric_id', 'status']);

            $withBiometricAll = $allEmployeesInCompany->filter(fn($e) => !empty($e->biometric_id));
            $duplicateBiometricIds = $withBiometricAll
                ->groupBy('biometric_id')
                ->filter(fn($group) => $group->count() > 1)
                ->keys()
                ->all();

            $activeEmployees = $allEmployeesInCompany->where('status', 'active');
            $totalActive = $activeEmployees->count();
            $withBiometric = $activeEmployees->filter(fn($e) => !empty($e->biometric_id));
            $missingBiometric = $activeEmployees->filter(fn($e) => empty($e->biometric_id));
        } else {
            $totalActive = 0;
            $withBiometric = collect();
            $missingBiometric = collect();
            $duplicateBiometricIds = [];
        }

        $defaultServerAddress = AttendanceDevice::getDefaultServerAddress();

        return view('admin.attendance.devices.index', compact(
            'devices',
            'companies',
            'selectedCompanyId',
            'statsCompanyId',
            'isSuperAdmin',
            'totalActive',
            'withBiometric',
            'missingBiometric',
            'duplicateBiometricIds',
            'defaultServerAddress'
        ));
    }

    /**
     * Register a new DELI attendance device.
     */
    public function store(Request $request)
    {
        [$user, $isSuperAdmin, $activeCompanyId] = $this->getAuthContext();

        $defaultServerAddress = AttendanceDevice::getDefaultServerAddress();

        $rules = [
            'name' => 'required|string|max:255',
            'server_address' => $defaultServerAddress ? 'nullable|string|max:255' : 'required|string|max:255',
        ];

        $messages = [
            'server_address.required' => 'A reachable Server Base Address (domain or IP:port) is required because no usable public URL could be determined automatically.',
        ];

        if ($isSuperAdmin) {
            $rules['company_id'] = 'required|exists:companies,id';
        }

        $validated = $request->validate($rules, $messages);
        $companyId = $isSuperAdmin ? (int) $validated['company_id'] : $activeCompanyId;

        $serverAddress = null;
        if (!empty($validated['server_address'])) {
            try {
                $serverAddress = AttendanceDevice::normalizeServerAddress($validated['server_address']);
            } catch (\InvalidArgumentException $e) {
                return redirect()->back()->withInput()->with('statusError', $e->getMessage());
            }
        }

        $device = AttendanceDevice::create([
            'company_id' => $companyId,
            'name' => trim($validated['name']),
            'server_address' => $serverAddress,
            'is_active' => true,
            'next_upload_pos' => null,
        ]);

        return redirect()->back()->with('statusSuccess', "DELI Device '{$device->name}' registered successfully! Upload URL generated.");
    }

    /**
     * Update device server address override.
     */
    public function updateServerAddress(Request $request, AttendanceDevice $device)
    {
        $this->authorizeDevice($device);

        $request->validate([
            'server_address' => 'nullable|string|max:255',
        ]);

        $rawAddress = $request->input('server_address');
        try {
            $normalized = AttendanceDevice::normalizeServerAddress($rawAddress);
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('statusError', $e->getMessage());
        }

        $device->server_address = $normalized;
        $device->save();

        return redirect()->back()->with('statusSuccess', "Server address updated for '{$device->name}'. Upload URL regenerated.");
    }

    /**
     * Toggle device active status.
     */
    public function toggle(AttendanceDevice $device)
    {
        $this->authorizeDevice($device);

        $device->is_active = !$device->is_active;
        $device->save();

        $statusText = $device->is_active ? 'activated' : 'deactivated';
        return redirect()->back()->with('statusSuccess', "Device '{$device->name}' has been {$statusText}.");
    }

    /**
     * Rotate device upload token.
     */
    public function rotateToken(AttendanceDevice $device)
    {
        $this->authorizeDevice($device);

        $device->rotateToken();

        return redirect()->back()->with('statusWarning', "Upload token rotated for '{$device->name}'. The previous Upload URL has been invalidated. Please update the target_uri in SDKDemoApp on the physical DELI machine.");
    }

    /**
     * Reset device upload pointer position.
     */
    public function resetPosition(Request $request, AttendanceDevice $device)
    {
        $this->authorizeDevice($device);

        $request->validate([
            'position' => [
                'required',
                function ($attribute, $value, $fail) {
                    if ($value === 'null' || $value === null) {
                        return;
                    }
                    if (!ctype_digit((string) $value) && !is_int($value)) {
                        $fail('The position must be "null" or a non-negative integer.');
                    }
                },
            ],
        ]);

        $rawPos = $request->input('position');
        if ($rawPos === 'null' || $rawPos === null) {
            $device->next_upload_pos = null;
        } else {
            $device->next_upload_pos = (int) $rawPos;
        }
        $device->save();

        $displayPos = $device->next_upload_pos === null ? 'None (Sync Handshake)' : $device->next_upload_pos;
        return redirect()->back()->with('statusSuccess', "Upload position reset to '{$displayPos}' for device '{$device->name}'.");
    }

    /**
     * Safe test handshake simulation / configuration validation.
     */
    public function testHandshake(AttendanceDevice $device)
    {
        $this->authorizeDevice($device);

        $result = $this->ingestionService->handleRequest($device->public_id, $device->upload_token, [], true);

        return response()->json([
            'success' => $result['status'] === 200,
            'status' => $result['status'],
            'body' => $result['body'],
            'endpoint_url' => $device->upload_url,
            'message' => $result['status'] === 200
                ? 'Configuration and credentials validated successfully via simulated handshake. Physical device reachability is confirmed once the biometric machine establishes its first connection.'
                : 'Configuration validation failed. Check credentials and active status.',
        ]);
    }

    /**
     * Display recent attendance logs imported from this device.
     */
    public function deviceLogs(AttendanceDevice $device)
    {
        $this->authorizeDevice($device);

        $logs = AttendanceLog::where('device_serial', 'DELI:' . $device->public_id)
            ->with('employee')
            ->orderBy('log_date', 'desc')
            ->orderBy('log_time', 'desc')
            ->paginate(50);

        return view('admin.attendance.devices.logs', compact('device', 'logs'));
    }

    /**
     * Display device user mappings and unmapped pending users.
     */
    public function mappings(AttendanceDevice $device)
    {
        $this->authorizeDevice($device);

        $mappings = AttendanceDeviceUserMapping::where('attendance_device_id', $device->id)
            ->with('employee.dept')
            ->orderBy('device_user_id')
            ->get();

        $mappedUserIds = $mappings->pluck('device_user_id')->all();

        // Find unresolved user IDs observed in pending punches
        $pendingUsers = AttendanceDevicePendingLog::where('attendance_device_id', $device->id)
            ->where('status', 'pending')
            ->selectRaw('device_user_id, count(*) as pending_count, max(logged_at) as latest_punch')
            ->groupBy('device_user_id')
            ->orderBy('device_user_id')
            ->get();

        // Company employees for mapping selector
        $employees = EmployeeMaster::withoutGlobalScopes()
            ->where('company_id', $device->company_id)
            ->with('dept')
            ->orderBy('first_name')
            ->get();

        return view('admin.attendance.devices.mappings', compact('device', 'mappings', 'pendingUsers', 'mappedUserIds', 'employees'));
    }

    /**
     * Store or update device user mapping and recover pending punches.
     */
    public function storeMapping(Request $request, AttendanceDevice $device)
    {
        $this->authorizeDevice($device);

        $validated = $request->validate([
            'device_user_id' => 'required|string|max:100',
            'employee_id' => 'required|exists:employees_master,id',
        ]);

        $deviceUserId = trim($validated['device_user_id']);
        $employeeId = (int) $validated['employee_id'];

        $employee = EmployeeMaster::withoutGlobalScopes()
            ->where('company_id', $device->company_id)
            ->find($employeeId);

        if (!$employee) {
            abort(403, 'Selected employee does not belong to this company.');
        }

        AttendanceDeviceUserMapping::updateOrCreate(
            [
                'attendance_device_id' => $device->id,
                'device_user_id' => $deviceUserId,
            ],
            [
                'employee_id' => $employeeId,
            ]
        );

        $recoveredCount = $this->ingestionService->processPendingLogsForMapping($device, $deviceUserId, $employeeId);

        $message = "DELI User ID '{$deviceUserId}' mapped to {$employee->first_name} {$employee->last_name} ({$employee->employee_code}).";
        if ($recoveredCount > 0) {
            $message .= " {$recoveredCount} historical pending punches were automatically recovered and logged!";
        }

        return redirect()->back()->with('statusSuccess', $message);
    }

    /**
     * Remove an existing device user mapping.
     */
    public function deleteMapping(AttendanceDevice $device, AttendanceDeviceUserMapping $mapping)
    {
        $this->authorizeDevice($device);

        if ($mapping->attendance_device_id !== $device->id) {
            abort(403, 'Mapping does not belong to this device.');
        }

        $userId = $mapping->device_user_id;
        $mapping->delete();

        return redirect()->back()->with('statusSuccess', "Mapping for DELI User '{$userId}' removed. Historical attendance records have been preserved.");
    }
}
