@extends('admin.layouts.Master')

@section('title') Employee Mapping - {{ $device->name }} @endsection

@section('content')
<div class="container-fluid">
    <!-- Header -->
    <div class="row mb-3">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <div>
                <a href="{{ panelRoute('attendance.devices.index') }}" class="btn btn-sm btn-outline-secondary mb-2">
                    <i class="mdi mdi-arrow-left me-1"></i> Back to DELI Devices
                </a>
                <h4 class="mb-0 fw-bold text-dark">
                    <i class="mdi mdi-account-switch text-primary me-2"></i> Employee Mapping: {{ $device->name }}
                </h4>
                <p class="text-muted small mb-0">Map DELI machine user IDs to Payroll Employees for {{ $device->company->name ?? 'Company' }}.</p>
            </div>
            <div>
                <span class="badge bg-light text-dark border p-2">
                    <i class="mdi mdi-cloud-upload text-info me-1"></i> Public ID: <code>{{ $device->public_id }}</code>
                </span>
            </div>
        </div>
    </div>

    @if(session('statusSuccess'))
        <div class="alert alert-success alert-dismissible fade show py-2 px-3 small" role="alert">
            <i class="mdi mdi-check-circle me-1"></i> {{ session('statusSuccess') }}
            <button type="button" class="btn-close py-2" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('statusError'))
        <div class="alert alert-danger alert-dismissible fade show py-2 px-3 small" role="alert">
            <i class="mdi mdi-alert-circle me-1"></i> {{ session('statusError') }}
            <button type="button" class="btn-close py-2" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row g-3 mb-4">
        <!-- Unresolved / Pending Punches Card -->
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold text-dark">
                        <i class="mdi mdi-alert-circle-outline text-warning me-1"></i> Unmapped DELI Users with Pending Punches
                    </h6>
                    <span class="badge {{ $pendingUsers->count() > 0 ? 'bg-warning text-dark' : 'bg-success' }}">
                        {{ $pendingUsers->count() }} Unmapped {{ Str::plural('User', $pendingUsers->count()) }}
                    </span>
                </div>
                <div class="card-body p-0">
                    @if($pendingUsers->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover table-bordered mb-0 align-middle">
                                <thead class="table-light small">
                                    <tr>
                                        <th style="width: 140px;">DELI User ID</th>
                                        <th style="width: 130px;">Pending Punches</th>
                                        <th style="width: 180px;">Latest Punch</th>
                                        <th>Select Payroll Employee to Map</th>
                                        <th style="width: 180px;" class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($pendingUsers as $pending)
                                        <tr>
                                            <td>
                                                <span class="badge bg-warning text-dark font-monospace fs-6">
                                                    {{ $pending->device_user_id }}
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-danger">
                                                    {{ $pending->pending_count }} {{ Str::plural('punch', $pending->pending_count) }}
                                                </span>
                                            </td>
                                            <td class="small text-muted">
                                                {{ \Carbon\Carbon::parse($pending->latest_punch)->format('M d, Y h:i A') }}
                                            </td>
                                            <td>
                                                <form method="POST" action="{{ panelRoute('attendance.devices.store-mapping', $device->id) }}" id="mapForm-{{ $pending->device_user_id }}" class="d-flex gap-2">
                                                    @csrf
                                                    <input type="hidden" name="device_user_id" value="{{ $pending->device_user_id }}">
                                                    <select name="employee_id" class="form-select form-select-sm" required>
                                                        <option value="">-- Select Employee for ID {{ $pending->device_user_id }} --</option>
                                                        @foreach($employees as $emp)
                                                            <option value="{{ $emp->id }}">
                                                                {{ $emp->first_name }} {{ $emp->last_name }} ({{ $emp->employee_code ?? 'EMP-'.$emp->id }})
                                                                @if($emp->dept) — {{ $emp->dept->name }} @endif
                                                                @if($emp->biometric_id) [Bio ID: {{ $emp->biometric_id }}] @endif
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </form>
                                            </td>
                                            <td class="text-end">
                                                <button type="submit" form="mapForm-{{ $pending->device_user_id }}" class="btn btn-sm btn-success fw-bold">
                                                    <i class="mdi mdi-link-variant me-1"></i> Map & Recover
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-4 text-muted">
                            <i class="mdi mdi-check-all text-success fs-3 d-block mb-1"></i>
                            No pending unmapped punches! All uploads for this device have been successfully mapped.
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Add Explicit Mapping Form -->
        <div class="col-lg-4">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="mb-0 fw-bold text-dark"><i class="mdi mdi-plus-circle text-primary me-1"></i> Add Custom Device Mapping</h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ panelRoute('attendance.devices.store-mapping', $device->id) }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-secondary">DELI Machine User ID <span class="text-danger">*</span></label>
                            <input type="text" name="device_user_id" class="form-control form-control-sm font-monospace" placeholder="e.g. 101" required>
                            <small class="text-muted">Numeric or string user ID enrolled in the DELI machine.</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-secondary">Payroll Employee <span class="text-danger">*</span></label>
                            <select name="employee_id" class="form-select form-select-sm" required>
                                <option value="">-- Choose Employee --</option>
                                @foreach($employees as $emp)
                                    <option value="{{ $emp->id }}">
                                        {{ $emp->first_name }} {{ $emp->last_name }} ({{ $emp->employee_code ?? 'EMP-'.$emp->id }})
                                        @if($emp->dept) — {{ $emp->dept->name }} @endif
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-sm fw-bold">
                                <i class="mdi mdi-check me-1"></i> Save Device Mapping
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Existing Mappings Table -->
        <div class="col-lg-8">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold text-dark"><i class="mdi mdi-account-multiple-check text-success me-1"></i> Active Device User Mappings</h6>
                    <span class="badge bg-primary">{{ $mappings->count() }} Mapped</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered mb-0 align-middle">
                            <thead class="table-light small">
                                <tr>
                                    <th style="width: 130px;">DELI User ID</th>
                                    <th>Payroll Employee</th>
                                    <th>Department</th>
                                    <th>Biometric ID</th>
                                    <th style="width: 80px;" class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($mappings as $mapping)
                                    <tr>
                                        <td>
                                            <span class="badge bg-light text-dark border font-monospace fs-6">
                                                {{ $mapping->device_user_id }}
                                            </span>
                                        </td>
                                        <td class="fw-bold">
                                            {{ $mapping->employee->first_name ?? 'N/A' }} {{ $mapping->employee->last_name ?? '' }}
                                            <small class="text-muted d-block">{{ $mapping->employee->employee_code ?? '' }}</small>
                                        </td>
                                        <td class="small text-muted">
                                            {{ $mapping->employee->dept->name ?? '—' }}
                                        </td>
                                        <td class="small font-monospace">
                                            {{ $mapping->employee->biometric_id ?? '—' }}
                                        </td>
                                        <td class="text-end">
                                            <form method="POST" action="{{ panelRoute('attendance.devices.delete-mapping', [$device->id, $mapping->id]) }}" onsubmit="return confirm('Remove mapping for DELI User {{ $mapping->device_user_id }}? Historical attendance will remain saved.');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Remove Mapping">
                                                    <i class="mdi mdi-trash-can-outline"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-muted">
                                            No explicit device mappings saved yet. Punches will automatically map via Biometric ID or appear in Unmapped Punches above.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
