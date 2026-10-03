@extends('admin.layouts.Master')
@section('title') Attendance Logs @endsection
@section('content')
    <div class="col-12">
        <div class="card mt-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Attendance Logs</h4>
                <div class="d-flex gap-2">
                    @if(auth()->check() && (auth()->user()->role === 'admin' || (int)auth()->user()->role_id === \App\Models\Role::SUPER_ADMIN || in_array(auth()->user()->role, ['company', 'company_admin'], true) || (int)auth()->user()->role_id === \App\Models\Role::COMPANY_ADMIN))
                        <a href="{{ panelRoute('attendance.devices.index') }}" class="btn btn-info text-white" style="background: linear-gradient(135deg, #6a11cb, #2575fc); border: none; padding: 8px 18px; font-weight: bold;">
                            <i class="mdi mdi-fingerprint me-1"></i> DELI Device Integration
                        </a>
                    @endif
                    <a href="{{ panelRoute('attendance.manual-generate') }}" class="btn btn-success" style="background: linear-gradient(135deg, #11998e, #38ef7d); border: none; padding: 8px 18px; font-weight: bold; color: #fff;">
                        <i class="mdi mdi-calendar-plus me-1"></i> Manual Generator
                    </a>
                    <a href="{{ route('attendance.import') }}" class="btn btn-primary" style="background: linear-gradient(135deg, #1f618d, #2980b9); border: none; padding: 8px 18px; font-weight: bold; color: #fff;">
                        <i class="mdi mdi-upload"></i> Import Attendance
                    </a>
                </div>
            </div>
            <div class="card-body">
                <!-- Filters -->
                <form method="GET" action="{{ route('attendance.index') }}" class="mb-4">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label class="form-label" style="font-size: 11px; font-weight: bold; color: #555;">Employee</label>
                            <select name="employee_id" class="form-control">
                                <option value="">All Employees</option>
                                @foreach($employees as $employee)
                                    <option value="{{ $employee->id }}" {{ request('employee_id') == $employee->id ? 'selected' : '' }}>
                                        {{ $employee->full_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label" style="font-size: 11px; font-weight: bold; color: #555;">Date From</label>
                            <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label" style="font-size: 11px; font-weight: bold; color: #555;">Date To</label>
                            <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
                        </div>
                        <div class="col-md-3 mb-3 d-flex align-items-end">
                            <div class="d-flex gap-2 w-100">
                                <button type="submit" class="btn btn-primary w-50" style="background: linear-gradient(135deg, #1f618d, #2980b9); border: none; padding: 8px 18px; font-weight: bold; color: #fff;">
                                    Filter
                                </button>
                                <a href="{{ route('attendance.index') }}" class="btn btn-secondary w-50" style="background-color: #6c757d; border: none; padding: 8px 18px; font-weight: bold; color: #fff; text-decoration: none; text-align: center;">
                                    Clear
                                </a>
                            </div>
                        </div>
                    </div>
                </form>

                <style>
                    #myTable th, #myTable td {
                        vertical-align: middle !important;
                        white-space: nowrap !important;
                    }
                </style>
                <!-- Attendance Logs Table -->
                <div class="table-responsive">
                    <table class="table table-striped align-middle" id="myTable">
                        <thead>
                        <tr>
                            <th scope="col">Employee</th>
                            <th scope="col">Biometric ID</th>
                            <th scope="col">Date</th>
                            <th scope="col">Time</th>
                            <th scope="col">Type</th>
                            <th scope="col">Device Serial</th>
                            <th scope="col">Import Batch</th>
                            <th scope="col">Action</th>
                        </tr>
                        </thead>
                        <tbody>
                            @if(isset($attendanceLogs) && $attendanceLogs->count() > 0)
                                @foreach ($attendanceLogs as $log)
                                    <tr>
                                        <td>{{ $log->employee->full_name }}</td>
                                        <td>{{ $log->biometric_id }}</td>
                                        <td>{{ \Carbon\Carbon::parse($log->log_date)->format('M d, Y') }}</td>
                                        <td>{{ \Carbon\Carbon::parse($log->log_time)->format('h:i A') }}</td>
                                        <td><span class="badge bg-primary text-white">{{ ucfirst($log->log_type) }}</span></td>
                                        <td>{{ $log->device_serial ?? 'N/A' }}</td>
                                        <td><small class="text-muted">{{ $log->import_batch ?? 'Manual' }}</small></td>
                                        <td>
                                            <div class="d-flex align-items-center" style="gap: 12px; min-width: 80px;">
                                                <form action="{{ route('attendance.destroy', $log->id) }}" method="POST" style="display:inline; margin:0;" class="btn-delete-form" onsubmit="return confirm('Are you sure you want to delete this log?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-link p-0 text-danger btn-delete" title="Delete" style="border: none; background: none; line-height: 1;">
                                                        <i class="fa-sharp fa-solid fa-trash" style="font-size: 14px;"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>    
                                    </tr>
                                @endforeach
                            @else
                                <tr>
                                    <td colspan="8" class="text-center">No attendance logs found</td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3">
                    <div>
                        {{ $attendanceLogs->links() }}
                    </div>
                    <x-per-page-selector :paginator="$attendanceLogs" />
                </div>
            </div> 
        </div> 
    </div>
    <script>
        var translations = {
            search: "{{ __('messages.search') }}"
        };
    </script>
@endsection
