@extends('admin.layouts.Master')
@section('title') Import Attendance @endsection
@section('content')
    <div class="col-12">
        <div class="card mt-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Import Attendance</h4>
                <a href="{{ route('attendance.index') }}" class="btn btn-secondary" style="background-color: #6c757d; border: none; padding: 8px 18px; font-weight: bold; color: #fff;">
                    <i class="mdi mdi-arrow-left"></i> Back to List
                </a>
            </div>
            <div class="card-body">
                <form action="{{ route('attendance.process-import') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    
                    <div class="alert alert-info">
                        <h6>Import Instructions:</h6>
                        <ul>
                            <li>Export attendance data from your ZKTeco device</li>
                            <li>Supported file formats: .txt, .csv, .dat</li>
                            <li>Expected format: <code>BiometricID,DateTime</code> or <code>BiometricID	DateTime	Type</code> (tab-separated)</li>
                            <li>Example: <code>101,2026-07-04 08:30:00</code></li>
                            <li>Make sure employee Biometric IDs are entered in Payroll Employee Master</li>
                        </ul>
                    </div>

                    <div class="mb-3">
                        <label for="company_id" class="form-label">Company <span style="color: red;">*</span></label>
                        <select class="form-control" id="company_id" name="company_id" required>
                            <option value="">Select Company</option>
                            @foreach($companies as $company)
                                <option value="{{ $company->id }}" {{ old('company_id') == $company->id ? 'selected' : '' }}>
                                    {{ $company->name }} ({{ $company->company_code }})
                                </option>
                            @endforeach
                        </select>
                        <small class="text-muted">Select the company for this attendance import</small>
                        @error('company_id')
                            <div class="text-danger">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="device_serial" class="form-label">Device Serial Number (Optional)</label>
                        <input type="text" class="form-control" id="device_serial" name="device_serial" value="{{ old('device_serial') }}" placeholder="e.g., ZK-001">
                        <small class="text-muted">For tracking which device the data came from</small>
                        @error('device_serial')
                            <div class="text-danger">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="import_file" class="form-label">Attendance File <span style="color: red;">*</span></label>
                        <input type="file" class="form-control" id="import_file" name="import_file" accept=".txt,.csv,.dat" required>
                        <small class="text-muted">Maximum file size: 10MB</small>
                        @error('import_file')
                            <div class="text-danger">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="alert alert-warning">
                        <strong>Note:</strong> Duplicate entries (same employee, date, and time) will be automatically skipped.
                    </div>

                    @if(session('importErrors'))
                        <div class="alert alert-danger">
                            <strong>Import Errors:</strong>
                            <div>{!! session('importErrors') !!}</div>
                        </div>
                    @endif

                    <div class="d-flex justify-content-end gap-2">
                        <button type="submit" class="btn btn-primary" style="background: linear-gradient(135deg, #1f618d, #2980b9); border: none; padding: 8px 24px; font-weight: bold; color: #fff;">
                            <i class="mdi mdi-check"></i> Import Attendance
                        </button>
                        <a href="{{ route('attendance.index') }}" class="btn btn-secondary" style="background-color: #6c757d; border: none; padding: 8px 24px; font-weight: bold; color: #fff; text-decoration: none;">
                            Cancel
                        </a>
                    </div>
                </form>

                <hr class="my-4">

                <div class="card">
                    <div class="card-header">
                        <h5>Sample File Format</h5>
                    </div>
                    <div class="card-body">
                        <p>Your attendance file should look like one of these formats:</p>
                        
                        <h6>Format 1: Comma-separated</h6>
                        <pre class="bg-light p-2 border">101,2026-07-04 08:30:00
102,2026-07-04 08:31:15
101,2026-07-04 17:00:00
102,2026-07-04 17:05:20</pre>

                        <h6>Format 2: Tab-separated with type</h6>
                        <pre class="bg-light p-2 border">101	2026-07-04 08:30:00	check
102	2026-07-04 08:31:15	check
101	2026-07-04 17:00:00	check
102	2026-07-04 17:05:20	check</pre>
                    </div>
                </div>
            </div> 
        </div> 
    </div>
@endsection
