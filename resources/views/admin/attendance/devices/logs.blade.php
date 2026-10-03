@extends('admin.layouts.Master')
@section('title') DELI Device Logs - {{ $device->name }} @endsection
@section('content')
<div class="container-fluid py-3">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1 text-dark fw-bold"><i class="mdi mdi-history text-primary me-1"></i> Device Attendance Logs: {{ $device->name }}</h4>
            <p class="text-muted mb-0 small">
                Serial: <code>DELI:{{ $device->public_id }}</code> | Company: <strong>{{ $device->company->name ?? 'N/A' }}</strong> | Status: 
                <span class="badge {{ $device->is_active ? 'bg-success' : 'bg-danger' }} text-white">{{ $device->is_active ? 'Active' : 'Inactive' }}</span>
            </p>
        </div>
        <div>
            <a href="{{ panelRoute('attendance.devices.index') }}" class="btn btn-secondary btn-sm">
                <i class="mdi mdi-arrow-left me-1"></i> Back to DELI Devices
            </a>
        </div>
    </div>

    <!-- Diagnostic Summary -->
    <div class="row g-2 mb-3">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm p-3 bg-light text-center">
                <div class="text-muted small">Total Logs Ingested</div>
                <div class="fs-4 fw-bold text-dark">{{ $logs->total() }}</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm p-3 bg-light text-center">
                <div class="text-muted small">Last Device Contact</div>
                <div class="fs-6 fw-bold text-primary mt-1">{{ $device->last_seen_at ? $device->last_seen_at->diffForHumans() : 'Never' }}</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm p-3 bg-light text-center">
                <div class="text-muted small">Current Upload Position</div>
                <div class="fs-5 fw-bold text-dark font-monospace">{{ $device->next_upload_pos ?? 'None ({})' }}</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm p-3 bg-light text-center">
                <div class="text-muted small">Latest Batch Result</div>
                <div class="fs-6 fw-bold text-success mt-1">
                    @if($device->last_result)
                        {{ $device->last_result['imported'] ?? 0 }} imp / {{ $device->last_result['received'] ?? 0 }} rec
                    @else
                        No batches yet
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Attendance Logs Table -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold text-dark"><i class="mdi mdi-format-list-bulleted text-info me-1"></i> Imported Logs (Read-Only)</h6>
            <span class="badge bg-secondary">{{ $logs->total() }} Records</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Employee</th>
                            <th>Biometric ID</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Punch Type</th>
                            <th>Import Batch</th>
                            <th>Imported At</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($logs as $log)
                            <tr>
                                <td>
                                    <div class="fw-bold text-dark">{{ $log->employee->full_name ?? 'N/A' }}</div>
                                    <small class="text-muted">{{ $log->employee->employee_code ?? '' }}</small>
                                </td>
                                <td><span class="badge bg-light text-dark border font-monospace">{{ $log->biometric_id }}</span></td>
                                <td>{{ \Carbon\Carbon::parse($log->log_date)->format('M d, Y') }}</td>
                                <td>{{ \Carbon\Carbon::parse($log->log_time)->format('h:i:s A') }}</td>
                                <td>
                                    <span class="badge {{ $log->log_type === 'in' ? 'bg-success' : 'bg-primary' }} text-white">
                                        {{ strtoupper($log->log_type) }}
                                    </span>
                                </td>
                                <td><small class="text-muted font-monospace">{{ $log->import_batch ?? 'N/A' }}</small></td>
                                <td><small class="text-muted">{{ $log->imported_at ? $log->imported_at->format('M d, Y H:i:s') : 'N/A' }}</small></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    <i class="mdi mdi-clock-alert-outline text-secondary fs-3 d-block mb-1"></i>
                                    No attendance records imported from this DELI device yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($logs->hasPages())
            <div class="card-footer bg-white py-2 border-top">
                {{ $logs->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
