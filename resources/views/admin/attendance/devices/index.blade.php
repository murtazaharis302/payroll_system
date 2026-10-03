@extends('admin.layouts.Master')
@section('title') DELI Biometric Attendance Integration @endsection
@section('content')
<div class="container-fluid py-3">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1 text-dark fw-bold"><i class="mdi mdi-fingerprint text-primary me-1"></i> DELI Biometric Device Integration</h4>
            <p class="text-muted mb-0 small">Manage automatic HTTP attendance log uploaders for DELI biometric devices</p>
        </div>
        <div>
            <a href="{{ panelRoute('attendance.index') }}" class="btn btn-secondary btn-sm">
                <i class="mdi mdi-arrow-left me-1"></i> Back to Attendance Logs
            </a>
        </div>
    </div>

    <!-- Company Filter for Super Admin -->
    @if($isSuperAdmin && $companies->count() > 1)
        <div class="card mb-3 shadow-sm border-0">
            <div class="card-body py-2 px-3">
                <form method="GET" action="{{ panelRoute('attendance.devices.index') }}" class="row g-2 align-items-center">
                    <div class="col-auto">
                        <label class="form-label mb-0 fw-bold small text-secondary">Filter by Company:</label>
                    </div>
                    <div class="col-auto">
                        <select name="company_id" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="all" {{ $selectedCompanyId === 'all' ? 'selected' : '' }}>-- All Companies --</option>
                            @foreach($companies as $company)
                                <option value="{{ $company->id }}" {{ (string)$selectedCompanyId === (string)$company->id ? 'selected' : '' }}>
                                    {{ $company->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <div class="row g-3 mb-4">
        <!-- Register Device Card -->
        <div class="col-lg-5">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="mb-0 fw-bold text-dark"><i class="mdi mdi-plus-circle-outline text-success me-1"></i> Register New DELI Device</h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ panelRoute('attendance.devices.store') }}">
                        @csrf
                        @if($isSuperAdmin)
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-secondary">Target Company <span class="text-danger">*</span></label>
                                <select name="company_id" class="form-select form-select-sm" required>
                                    @foreach($companies as $company)
                                        <option value="{{ $company->id }}" {{ (string)$statsCompanyId === (string)$company->id ? 'selected' : '' }}>
                                            {{ $company->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-secondary">Device Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control form-control-sm" placeholder="e.g. Main Entrance DELI Face/FP" required maxlength="255">
                            <small class="text-muted">Human-readable label to identify punches from this unit.</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-secondary">
                                Server Base Address
                                @if($defaultServerAddress)
                                    <span class="text-muted fw-normal">(Optional Override)</span>
                                @else
                                    <span class="text-danger">*</span>
                                @endif
                            </label>
                            <input type="text" name="server_address" class="form-control form-control-sm font-monospace" placeholder="{{ $defaultServerAddress ? 'e.g. ' . $defaultServerAddress : 'e.g. https://payroll.company.com or http://192.168.1.50:8000' }}" {{ $defaultServerAddress ? '' : 'required' }}>
                            <small class="text-muted">
                                @if($defaultServerAddress)
                                    Default: <code>{{ $defaultServerAddress }}</code>. Specify if machine uses custom LAN IP or proxy.
                                @else
                                    <span class="text-danger fw-semibold">Required:</span> Enter reachable domain or LAN IP:port for physical DELI machines.
                                @endif
                            </small>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-sm fw-bold">
                                <i class="mdi mdi-check-circle me-1"></i> Register Device & Generate URL
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Biometric Mapping Readiness Card -->
        <div class="col-lg-7">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold text-dark"><i class="mdi mdi-account-check text-info me-1"></i> Biometric Mapping Readiness</h6>
                    <span class="badge {{ $statsCompanyId ? 'bg-primary' : 'bg-secondary' }} text-white">
                        {{ $statsCompanyId ? 'Selected Company' : 'All Companies' }}
                    </span>
                </div>
                <div class="card-body">
                    @if($statsCompanyId === null)
                        <div class="d-flex flex-column justify-content-center align-items-center text-center py-4">
                            <div class="p-3 bg-light rounded-circle mb-2">
                                <i class="mdi mdi-domain text-primary fs-2"></i>
                            </div>
                            <h6 class="fw-bold text-dark mb-1">Select a company to view biometric mapping readiness</h6>
                            <p class="text-muted small mb-0" style="max-width: 420px;">
                                Biometric ID mapping and duplicate diagnostics are evaluated per company. Use the company filter above to select a specific company.
                            </p>
                        </div>
                    @else
                        <div class="alert alert-info py-2 px-3 mb-3 small">
                            <strong><i class="mdi mdi-information-outline"></i> Critical Mapping Rule:</strong> DELI User ID must match employee's <strong>Biometric ID</strong> or explicit <strong>Employee Mapping</strong>.
                        </div>

                        <div class="row g-2 text-center mb-3">
                            <div class="col-3">
                                <div class="p-2 border rounded bg-light">
                                    <div class="fs-5 fw-bold text-dark">{{ $totalActive }}</div>
                                    <div class="text-muted" style="font-size: 11px;">Active Employees</div>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="p-2 border rounded bg-light">
                                    <div class="fs-5 fw-bold text-success">{{ $withBiometric->count() }}</div>
                                    <div class="text-muted" style="font-size: 11px;">With Biometric ID</div>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="p-2 border rounded bg-light">
                                    <div class="fs-5 fw-bold {{ $missingBiometric->count() > 0 ? 'text-warning' : 'text-muted' }}">{{ $missingBiometric->count() }}</div>
                                    <div class="text-muted" style="font-size: 11px;">Missing ID</div>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="p-2 border rounded bg-light">
                                    <div class="fs-5 fw-bold {{ count($duplicateBiometricIds) > 0 ? 'text-danger' : 'text-muted' }}">{{ count($duplicateBiometricIds) }}</div>
                                    <div class="text-muted" style="font-size: 11px;">Duplicates</div>
                                </div>
                            </div>
                        </div>

                        @if(count($duplicateBiometricIds) > 0)
                            <div class="alert alert-danger py-2 px-3 small mb-2">
                                <strong><i class="mdi mdi-alert"></i> Ambiguity Warning:</strong> Duplicate Biometric ID(s) found: <code>{{ implode(', ', $duplicateBiometricIds) }}</code>. Logs with these IDs will be treated as ambiguous until explicitly mapped.
                            </div>
                        @endif

                        @if($missingBiometric->count() > 0)
                            <div class="small">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="text-danger fw-bold">Employees missing Biometric ID:</span>
                                    <a href="{{ panelRoute('payroll-employees.index') }}" class="small fw-semibold text-primary" target="_blank">
                                        Open Employee Record Management <i class="mdi mdi-open-in-new" style="font-size: 10px;"></i>
                                    </a>
                                </div>
                                <div class="mt-1 d-flex flex-wrap gap-1" style="max-height: 80px; overflow-y: auto;">
                                    @foreach($missingBiometric as $emp)
                                        <a href="{{ panelRoute('payroll-employees.edit', $emp->id) }}" class="badge bg-light text-dark border text-decoration-none" target="_blank" title="Click to edit employee biometric ID">
                                            {{ $emp->first_name }} {{ $emp->last_name }} ({{ $emp->employee_code ?? 'EMP-'.$emp->id }}) <i class="mdi mdi-open-in-new" style="font-size: 9px;"></i>
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @else
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="text-success small fw-bold"><i class="mdi mdi-check"></i> All active employees have Biometric IDs configured.</div>
                                <a href="{{ panelRoute('payroll-employees.index') }}" class="small fw-semibold text-primary" target="_blank">
                                    Employee Record Management <i class="mdi mdi-open-in-new" style="font-size: 10px;"></i>
                                </a>
                            </div>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Registered Devices Table -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold text-dark"><i class="mdi mdi-server-network text-primary me-1"></i> Registered DELI Devices</h6>
            <span class="badge bg-secondary">{{ $devices->count() }} Registered</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col" style="min-width: 140px;">Device Name</th>
                            <th scope="col">Company</th>
                            <th scope="col">Status</th>
                            <th scope="col">Last Contact</th>
                            <th scope="col">Mappings</th>
                            <th scope="col" style="min-width: 260px;">Upload URL</th>
                            <th scope="col">Latest Result</th>
                            <th scope="col" class="text-end" style="min-width: 220px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($devices as $device)
                            <tr>
                                <td>
                                    <div class="fw-bold text-dark">{{ $device->name }}</div>
                                    <small class="text-muted"><code>DELI:{{ $device->public_id }}</code></small>
                                </td>
                                <td><span class="badge bg-light text-dark border">{{ $device->company->name ?? 'N/A' }}</span></td>
                                <td>
                                    @if($device->is_active)
                                        <span class="badge bg-success text-white">Active</span>
                                    @else
                                        <span class="badge bg-danger text-white">Inactive</span>
                                    @endif
                                </td>
                                <td>
                                    @if($device->last_seen_at)
                                        <span class="text-dark fw-semibold" title="{{ $device->last_seen_at }}">{{ $device->last_seen_at->diffForHumans() }}</span>
                                    @else
                                        <span class="text-muted fst-italic">Never contacted</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="d-flex flex-column gap-1 align-items-start">
                                        <span class="badge bg-light text-dark border">{{ $device->mapped_users_count ?? 0 }} Mapped</span>
                                        @if(($device->pending_punches_count ?? 0) > 0)
                                            <a href="{{ panelRoute('attendance.devices.mappings', $device->id) }}" class="badge bg-warning text-dark text-decoration-none">
                                                <i class="mdi mdi-alert-circle"></i> {{ $device->pending_punches_count }} Pending
                                            </a>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    @if($device->upload_url)
                                        <div class="input-group input-group-sm">
                                            <input type="text" class="form-control font-monospace" id="url-{{ $device->id }}" value="{{ $device->upload_url }}" readonly style="font-size: 11px;">
                                            <button class="btn btn-outline-secondary" type="button" onclick="copyUrl('url-{{ $device->id }}')" title="Copy URL">
                                                <i class="mdi mdi-content-copy"></i>
                                            </button>
                                        </div>
                                    @else
                                        <span class="badge bg-warning text-dark" title="Server base address must be configured before an upload URL can be generated.">
                                            <i class="mdi mdi-alert"></i> Set Server Address
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    @if($device->last_result)
                                        @php $res = $device->last_result; @endphp
                                        <span class="badge bg-success" title="Imported">{{ $res['imported'] ?? 0 }} imported</span>
                                        @if(($res['duplicates'] ?? 0) > 0)
                                            <span class="badge bg-secondary" title="Duplicates">{{ $res['duplicates'] }} dup</span>
                                        @endif
                                        @if(($res['unmatched'] ?? 0) > 0 || ($res['ambiguous'] ?? 0) > 0)
                                            <span class="badge bg-warning text-dark" title="Unmatched/Ambiguous">{{ ($res['unmatched'] ?? 0) + ($res['ambiguous'] ?? 0) }} unres</span>
                                        @endif
                                        @if(($res['invalid'] ?? 0) > 0)
                                            <span class="badge bg-danger" title="Invalid">{{ $res['invalid'] }} inv</span>
                                        @endif
                                    @else
                                        <span class="text-muted small fst-italic">No uploads yet</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <!-- Employee Mapping Button -->
                                        <a href="{{ panelRoute('attendance.devices.mappings', $device->id) }}" class="btn {{ ($device->pending_punches_count ?? 0) > 0 ? 'btn-warning text-dark' : 'btn-outline-primary' }}" title="Employee User Mapping">
                                            <i class="mdi mdi-account-switch"></i> Mapping
                                        </a>

                                        <!-- Test Handshake -->
                                        <button type="button" class="btn btn-outline-info" onclick="testHandshake('{{ panelRoute('attendance.devices.test-handshake', $device->id) }}', '{{ $device->name }}')" title="Simulate Handshake / Validate Config">
                                            <i class="mdi mdi-sync"></i> Test
                                        </button>

                                        <!-- Toggle Status -->
                                        <form method="POST" action="{{ panelRoute('attendance.devices.toggle', $device->id) }}" style="display:inline;" onsubmit="return confirm('Toggle status for this device?');">
                                            @csrf
                                            <button type="submit" class="btn {{ $device->is_active ? 'btn-outline-warning' : 'btn-outline-success' }}" title="{{ $device->is_active ? 'Deactivate Device' : 'Activate Device' }}">
                                                <i class="mdi {{ $device->is_active ? 'mdi-pause' : 'mdi-play' }}"></i>
                                            </button>
                                        </form>

                                        <!-- Rotate Token -->
                                        <form method="POST" action="{{ panelRoute('attendance.devices.rotate-token', $device->id) }}" style="display:inline;" onsubmit="return confirm('WARNING: Rotating the upload token will immediately invalidate the existing URL. You MUST update the URL in SDKDemoApp on the physical DELI machine. Continue?');">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-danger" title="Rotate Upload Token">
                                                <i class="mdi mdi-key-change"></i>
                                            </button>
                                        </form>

                                        <!-- Reset Position Modal Trigger -->
                                        <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#resetModal-{{ $device->id }}" title="Reset Upload Position">
                                            <i class="mdi mdi-restart"></i>
                                        </button>

                                        <!-- View Logs -->
                                        <a href="{{ panelRoute('attendance.devices.logs', $device->id) }}" class="btn btn-outline-secondary" title="View Imported Logs">
                                            <i class="mdi mdi-history"></i>
                                        </a>
                                    </div>

                                    <!-- Reset Position Modal -->
                                    <div class="modal fade text-start" id="resetModal-{{ $device->id }}" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form method="POST" action="{{ panelRoute('attendance.devices.reset-position', $device->id) }}">
                                                    @csrf
                                                    <div class="modal-header">
                                                        <h6 class="modal-title fw-bold">Reset Upload Position: {{ $device->name }}</h6>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body small">
                                                        <div class="alert alert-warning py-2 mb-3">
                                                            <strong>Note:</strong> Resetting position backwards will cause the DELI device to resend historical logs. Duplicate detection automatically prevents duplicate records.
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label fw-bold">Select Position Option:</label>
                                                            <div class="form-check mb-2">
                                                                <input class="form-check-input" type="radio" name="position" id="posNull-{{ $device->id }}" value="null" {{ $device->next_upload_pos === null ? 'checked' : '' }}>
                                                                <label class="form-check-label" for="posNull-{{ $device->id }}">
                                                                    <strong>None (null)</strong> — Force DELI sync handshake (<code>{}</code>)
                                                                </label>
                                                            </div>
                                                            <div class="form-check mb-2">
                                                                <input class="form-check-input" type="radio" name="position" id="posZero-{{ $device->id }}" value="0" {{ $device->next_upload_pos === 0 ? 'checked' : '' }}>
                                                                <label class="form-check-label" for="posZero-{{ $device->id }}">
                                                                    <strong>0 (Start)</strong> — Request device to upload from log position 0
                                                                </label>
                                                            </div>
                                                            <div class="form-check mb-2">
                                                                <input class="form-check-input" type="radio" name="position" id="posCustom-{{ $device->id }}" value="custom">
                                                                <label class="form-check-label" for="posCustom-{{ $device->id }}">
                                                                    <strong>Specific Position:</strong>
                                                                </label>
                                                                <input type="number" id="customInput-{{ $device->id }}" class="form-control form-control-sm mt-1" placeholder="e.g. 50" min="0" oninput="document.getElementById('posCustom-{{ $device->id }}').value = this.value">
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer py-2">
                                                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-primary btn-sm fw-bold">Apply Reset</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">
                                    <i class="mdi mdi-server-off text-secondary fs-3 d-block mb-1"></i>
                                    No DELI biometric devices registered yet for this company.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Step-by-Step Integration Instructions Card -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3 border-bottom">
            <h6 class="mb-0 fw-bold text-dark"><i class="mdi mdi-book-open-page-variant text-primary me-1"></i> Physical DELI Device Setup Instructions</h6>
        </div>
        <div class="card-body small">
            <div class="alert alert-primary py-2 px-3 mb-3">
                <strong><i class="mdi mdi-lan-connect"></i> Prerequisite:</strong> The physical DELI machine's local network must have internet access / routing to reach the public Payroll System HTTPS domain.
            </div>

            <ol class="mb-0 ps-3">
                <li class="mb-2"><strong>Register Device:</strong> Select company, enter device name, and click <em>Register Device</em> to generate your secure Upload URL.</li>
                <li class="mb-2"><strong>Confirm Biometric IDs:</strong> Ensure each employee's DELI machine User ID matches their <strong>Biometric ID</strong> in Payroll System (see readiness card above).</li>
                <li class="mb-2"><strong>Copy Upload URL:</strong> Copy the full generated Upload URL from the table above.</li>
                <li class="mb-2"><strong>Open SDKDemoApp:</strong> On a Windows PC connected to the same LAN as the DELI biometric machine, launch the supplied DELI <code>SDKDemoApp</code>.</li>
                <li class="mb-2"><strong>Connect to Device:</strong> Enter the DELI device's local LAN IP and device control API key.</li>
                <li class="mb-2"><strong>Configure AttendLog Uploader:</strong> Navigate to the <em>ConfigAttendLogUploader</em> screen in SDKDemoApp.</li>
                <li class="mb-2"><strong>Paste URL & Interval:</strong> Paste the copied Payroll System URL into <code>target_uri</code>. Set upload interval (default: <code>60</code> seconds).</li>
                <li class="mb-2"><strong>Write & Apply:</strong> Click Write/Apply to save settings to the DELI machine.</li>
                <li class="mb-2"><strong>Verify Handshake:</strong> Watch the <em>Last Contact</em> column above. The machine will automatically send its initial <code>{}</code> sync handshake.</li>
                <li class="mb-0"><strong>Test Attendance Punch:</strong> Clock in on the DELI device. The punch will automatically appear in <a href="{{ panelRoute('attendance.index') }}" class="fw-bold">Attendance Logs</a> within the configured interval.</li>
            </ol>
        </div>
    </div>
</div>

<script>
function copyUrl(inputId) {
    var copyText = document.getElementById(inputId);
    copyText.select();
    copyText.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(copyText.value).then(function() {
        alert("Upload URL copied to clipboard!");
    });
}

function testHandshake(testUrl, deviceName) {
    if (!confirm("Run safe Handshake simulation for '" + deviceName + "'?")) return;

    fetch(testUrl, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        var msg = "Handshake Simulation & Config Validation for " + deviceName + ":\n\n";
        msg += "HTTP Status: " + data.status + "\n";
        msg += "Response Body: " + JSON.stringify(data.body, null, 2) + "\n\n";
        msg += data.message || (data.success ? "Configuration validated successfully." : "Validation failed.");
        alert(msg);
    })
    .catch(err => {
        alert("Handshake simulation error: " + err);
    });
}
</script>
@endsection
