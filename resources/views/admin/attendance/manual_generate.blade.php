@extends('admin.layouts.Master')
@section('title') Manual Attendance Generator @endsection
@section('content')
    <div class="col-12">
        <div class="card mt-4 shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
                <h4 class="mb-0 text-dark font-weight-bold">
                    <i class="mdi mdi-calendar-plus text-success me-2"></i> Manual Attendance Generator
                </h4>
                <a href="{{ route('attendance.index') }}" class="btn btn-secondary btn-sm font-weight-bold px-3">
                    <i class="mdi mdi-arrow-left me-1"></i> Back to Attendance Logs
                </a>
            </div>
            <div class="card-body p-4">
                <div class="alert alert-info border-0 shadow-sm mb-4">
                    <h5 class="alert-heading font-weight-bold"><i class="mdi mdi-information-outline me-1"></i> Batch / Manual Attendance Log Generation</h5>
                    <p class="mb-1">Generate check-in and check-out attendance log records for biweekly, monthly, or custom date ranges.</p>
                    <ul class="mb-0 ps-3 small">
                        <li>Super Admins can select any company; Company Admins generate for their company's employees.</li>
                        <li>Check-in (IN) and Check-out (OUT) entries will be created automatically for selected workdays.</li>
                        <li>Existing attendance logs on the same date and log type will be preserved without creating duplicates.</li>
                    </ul>
                </div>

                @if ($errors->any())
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <ul class="mb-0 ps-3">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                @if (session('statusDanger'))
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="mdi mdi-alert-circle me-1"></i> {{ session('statusDanger') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                <form action="{{ panelRoute('attendance.process-manual-generate') }}" method="POST" id="manualAttendanceForm">
                    @csrf

                    {{-- 1. Company Selection --}}
                    @if($isSuperAdmin && isset($companies) && $companies->count() > 0)
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label for="company_id" class="form-label font-weight-bold text-dark">
                                    Target Company <span class="text-danger">*</span>
                                </label>
                                <select name="company_id" id="company_id" class="form-control form-select @error('company_id') is-invalid @enderror" required onchange="onCompanyChange(this.value)">
                                    @foreach($companies as $company)
                                        <option value="{{ $company->id }}" {{ old('company_id', $selectedCompanyId) == $company->id ? 'selected' : '' }}>
                                            {{ $company->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="text-muted">Select company to generate attendance logs for.</small>
                            </div>
                        </div>
                    @else
                        <input type="hidden" name="company_id" id="company_id" value="{{ $selectedCompanyId }}">
                    @endif

                    <div class="row g-4">
                        {{-- 2. Target Employee Scope --}}
                        <div class="col-md-6">
                            <div class="card border h-100 shadow-none rounded">
                                <div class="card-header bg-light py-2">
                                    <h6 class="mb-0 font-weight-bold text-dark"><i class="mdi mdi-account-group me-1"></i> 1. Select Employees</h6>
                                </div>
                                <div class="card-body p-3">
                                    <div class="mb-3">
                                        <label class="form-label font-weight-bold text-dark mb-1">Employee Scope</label>
                                        <div class="d-flex gap-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="employee_scope" id="scope_all" value="all" {{ old('employee_scope', 'all') === 'all' ? 'checked' : '' }} onchange="toggleEmployeeList()">
                                                <label class="form-check-label font-weight-semibold text-dark" for="scope_all">
                                                    All Active Employees in Company
                                                </label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="employee_scope" id="scope_selected" value="selected" {{ old('employee_scope') === 'selected' ? 'checked' : '' }} onchange="toggleEmployeeList()">
                                                <label class="form-check-label font-weight-semibold text-dark" for="scope_selected">
                                                    Specific Employee(s)
                                                </label>
                                            </div>
                                        </div>
                                    </div>

                                    <div id="employee_select_container" class="mt-3" style="display: {{ old('employee_scope') === 'selected' ? 'block' : 'none' }};">
                                        <label class="form-label font-weight-semibold text-dark mb-1">
                                            Select Employee(s) <span class="text-danger">*</span>
                                        </label>
                                        <div id="employee_checkbox_list" class="border rounded p-2" style="max-height: 200px; overflow-y: auto; background-color: #f8fafc;">
                                            @if(isset($employees) && $employees->count() > 0)
                                                @foreach($employees as $emp)
                                                    <div class="form-check py-1">
                                                        <input class="form-check-input emp-checkbox" type="checkbox" name="employee_ids[]" value="{{ $emp->id }}" id="emp_{{ $emp->id }}" {{ is_array(old('employee_ids')) && in_array($emp->id, old('employee_ids')) ? 'checked' : '' }}>
                                                        <label class="form-check-label text-dark" for="emp_{{ $emp->id }}">
                                                            {{ $emp->full_name }} <small class="text-muted">({{ $emp->employee_code ?? 'EMP-' . $emp->id }})</small>
                                                        </label>
                                                    </div>
                                                @endforeach
                                            @else
                                                <p class="text-muted small mb-0 p-2">No active employees found for selected company.</p>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- 3. Period & Date Range Presets --}}
                        <div class="col-md-6">
                            <div class="card border h-100 shadow-none rounded">
                                <div class="card-header bg-light py-2">
                                    <h6 class="mb-0 font-weight-bold text-dark"><i class="mdi mdi-calendar-range me-1"></i> 2. Period & Date Range</h6>
                                </div>
                                <div class="card-body p-3">
                                    <div class="mb-3">
                                        <label for="period_preset" class="form-label font-weight-bold text-dark mb-1">Period Preset</label>
                                        <select name="period_preset" id="period_preset" class="form-control form-select" onchange="toggleDateFields()">
                                            <option value="biweekly_1_15" {{ old('period_preset', 'biweekly_1_15') === 'biweekly_1_15' ? 'selected' : '' }}>Biweekly (1st to 15th of Month)</option>
                                            <option value="biweekly_16_end" {{ old('period_preset') === 'biweekly_16_end' ? 'selected' : '' }}>Biweekly (16th to End of Month)</option>
                                            <option value="full_month" {{ old('period_preset') === 'full_month' ? 'selected' : '' }}>Full Month (1st to End of Month)</option>
                                            <option value="custom" {{ old('period_preset') === 'custom' ? 'selected' : '' }}>Custom Date Range</option>
                                        </select>
                                    </div>

                                    <div id="month_year_container" class="mb-3">
                                        <label for="month_year" class="form-label font-weight-semibold text-dark mb-1">Target Month & Year</label>
                                        <input type="month" name="month_year" id="month_year" class="form-control" value="{{ old('month_year', date('Y-m')) }}">
                                    </div>

                                    <div id="custom_date_container" class="row" style="display: {{ old('period_preset') === 'custom' ? 'flex' : 'none' }};">
                                        <div class="col-6 mb-3">
                                            <label for="date_from" class="form-label font-weight-semibold text-dark mb-1">Date From <span class="text-danger">*</span></label>
                                            <input type="date" name="date_from" id="date_from" class="form-control" value="{{ old('date_from') }}">
                                        </div>
                                        <div class="col-6 mb-3">
                                            <label for="date_to" class="form-label font-weight-semibold text-dark mb-1">Date To <span class="text-danger">*</span></label>
                                            <input type="date" name="date_to" id="date_to" class="form-control" value="{{ old('date_to') }}">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- 4. Work Schedule & Shift Times --}}
                        <div class="col-12">
                            <div class="card border shadow-none rounded">
                                <div class="card-header bg-light py-2">
                                    <h6 class="mb-0 font-weight-bold text-dark"><i class="mdi mdi-clock-outline me-1"></i> 3. Work Days & Shift Timings</h6>
                                </div>
                                <div class="card-body p-3">
                                    <div class="row">
                                        <div class="col-md-7 mb-3">
                                            <label class="form-label font-weight-bold text-dark mb-2">Work Days Included</label>
                                            <div class="d-flex flex-wrap gap-3">
                                                @php
                                                    $days = [
                                                        1 => 'Monday',
                                                        2 => 'Tuesday',
                                                        3 => 'Wednesday',
                                                        4 => 'Thursday',
                                                        5 => 'Friday',
                                                        6 => 'Saturday',
                                                        7 => 'Sunday',
                                                    ];
                                                    $oldDays = old('work_days', [1, 2, 3, 4, 5]);
                                                @endphp
                                                @foreach($days as $val => $dayName)
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="work_days[]" value="{{ $val }}" id="day_{{ $val }}" {{ in_array($val, $oldDays) ? 'checked' : '' }}>
                                                        <label class="form-check-label text-dark font-weight-semibold" for="day_{{ $val }}">
                                                            {{ $dayName }}
                                                        </label>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                        <div class="col-md-5 mb-3">
                                            <div class="row">
                                                <div class="col-6">
                                                    <label for="time_in" class="form-label font-weight-bold text-dark mb-1">Time In (Check-in)</label>
                                                    <input type="time" name="time_in" id="time_in" class="form-control" value="{{ old('time_in', '08:00') }}" required>
                                                </div>
                                                <div class="col-6">
                                                    <label for="time_out" class="form-label font-weight-bold text-dark mb-1">Time Out (Check-out)</label>
                                                    <input type="time" name="time_out" id="time_out" class="form-control" value="{{ old('time_out', '17:00') }}" required>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end mt-4 gap-2">
                        <a href="{{ route('attendance.index') }}" class="btn btn-secondary px-4">Cancel</a>
                        <button type="submit" class="btn btn-success px-4 font-weight-bold" style="background: linear-gradient(135deg, #11998e, #38ef7d); border: none;">
                            <i class="mdi mdi-check-circle me-1"></i> Generate Attendance Logs
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function toggleEmployeeList() {
            const scopeSelected = document.getElementById('scope_selected').checked;
            const container = document.getElementById('employee_select_container');
            container.style.display = scopeSelected ? 'block' : 'none';
        }

        function toggleDateFields() {
            const preset = document.getElementById('period_preset').value;
            const monthContainer = document.getElementById('month_year_container');
            const customContainer = document.getElementById('custom_date_container');

            if (preset === 'custom') {
                monthContainer.style.display = 'none';
                customContainer.style.display = 'flex';
            } else {
                monthContainer.style.display = 'block';
                customContainer.style.display = 'none';
            }
        }

        function onCompanyChange(companyId) {
            if (!companyId) return;
            const listContainer = document.getElementById('employee_checkbox_list');
            listContainer.innerHTML = '<p class="text-muted small mb-0 p-2"><i class="mdi mdi-loading mdi-spin me-1"></i> Loading employees...</p>';

            fetch("{{ url('admin/attendance/get-company-employees') }}/" + companyId)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.employees.length > 0) {
                        let html = '';
                        data.employees.forEach(emp => {
                            html += `
                                <div class="form-check py-1">
                                    <input class="form-check-input emp-checkbox" type="checkbox" name="employee_ids[]" value="${emp.id}" id="emp_${emp.id}">
                                    <label class="form-check-label text-dark" for="emp_${emp.id}">
                                        ${emp.name}
                                    </label>
                                </div>
                            `;
                        });
                        listContainer.innerHTML = html;
                    } else {
                        listContainer.innerHTML = '<p class="text-muted small mb-0 p-2">No active employees found for this company.</p>';
                    }
                })
                .catch(err => {
                    listContainer.innerHTML = '<p class="text-danger small mb-0 p-2">Error loading employees.</p>';
                });
        }
    </script>
    @endpush
@endsection
