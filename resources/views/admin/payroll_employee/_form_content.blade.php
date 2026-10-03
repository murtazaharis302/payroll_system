{{-- 3-Step Tabbed Form for Payroll Employee Creation/Edit --}}
<div data-failed-step="{{ session('employee_form_step', 1) }}" id="employeeFormFailedStep" hidden></div>
<div class="card border-0 shadow-sm">
    <div class="card-header bg-light pb-0 border-bottom-0">
        <ul class="nav nav-tabs card-header-tabs" id="employeeFormTabs" role="tablist" style="border-bottom: 2px solid #dee2e6;">
            <li class="nav-item" role="presentation">
                <button class="nav-link active font-weight-bold py-3 px-4" id="basic-info-tab" data-bs-toggle="tab" data-toggle="tab" data-bs-target="#tab-basic-info" data-target="#tab-basic-info" type="button" role="tab" aria-controls="tab-basic-info" aria-selected="true" onclick="switchTab('tab-basic-info')">
                    <i class="mdi mdi-account-details me-1"></i> 1. Basic & Personal Info
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link font-weight-bold py-3 px-4" id="recurring-income-tab" data-bs-toggle="tab" data-toggle="tab" data-bs-target="#tab-recurring-income" data-target="#tab-recurring-income" type="button" role="tab" aria-controls="tab-recurring-income" aria-selected="false" onclick="switchTab('tab-recurring-income')">
                    <i class="mdi mdi-cash-multiple me-1"></i> 2. Recurring Earnings & Deductions
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link font-weight-bold py-3 px-4" id="statutory-tab" data-bs-toggle="tab" data-toggle="tab" data-bs-target="#tab-statutory" data-target="#tab-statutory" type="button" role="tab" aria-controls="tab-statutory" aria-selected="false" onclick="switchTab('tab-statutory')">
                    <i class="mdi mdi-shield-account me-1"></i> 3. Statutory Numbers & Settings
                </button>
            </li>
        </ul>
    </div>

    <div class="card-body p-4">
        @if ($errors->any())
            <div class="alert alert-danger border-0 shadow-sm mb-4" role="alert" style="background-color: #f8d7da; border-left: 4px solid #dc3545 !important; color: #842029;">
                <div class="d-flex align-items-center mb-2">
                    <i class="mdi mdi-alert-circle font-20 me-2 text-danger"></i>
                    <h6 class="mb-0 font-weight-bold">Validation Error: Required Fields Missing</h6>
                </div>
                <p class="mb-2 small">Please complete the following required field(s) before saving the employee:</p>
                <ul class="mb-0 ps-3 small">
                    @foreach ($errors->all() as $error)
                        <li><strong>{{ $error }}</strong></li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="tab-content" id="employeeFormTabContent">

            {{-- ========================================================================= --}}
            {{-- TAB 1: BASIC & PERSONAL INFO --}}
            {{-- ========================================================================= --}}
            <div class="tab-pane fade show active" id="tab-basic-info" role="tabpanel" aria-labelledby="basic-info-tab">
                <h5 class="text-primary font-weight-bold mb-3 border-bottom pb-2">
                    <i class="mdi mdi-account-card-details"></i> Basic Information & Organization
                </h5>

                @if(isset($unlinkedEmployees) && $unlinkedEmployees->count() > 0)
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card bg-soft-info border border-info p-3">
                            <label for="user_id_select" class="form-label font-weight-bold text-info">
                                <i class="mdi mdi-account-search me-1"></i> Select Company Employee to Auto-Fill Profile
                            </label>
                            <select id="user_id_select" name="user_id" class="form-select" onchange="autoFillFromUser(this)">
                                <option value="">-- Select Existing User Account (Optional) --</option>
                                @foreach($unlinkedEmployees as $unlinkedUser)
                                    @php
                                        $uEmp = $unlinkedUser->employees;
                                        $fName = $uEmp->first_name ?? $unlinkedUser->name ?? '';
                                        $lName = $uEmp->last_name ?? '';
                                        $cEmail = $unlinkedUser->email ?? $uEmp->email ?? '';
                                        $cPhone = $uEmp->phone ?? $unlinkedUser->phone ?? '';
                                        $cCode = $uEmp->employee_code ?? '';
                                        $cPos = $uEmp->position ?? '';
                                    @endphp
                                    <option value="{{ $unlinkedUser->id }}"
                                        data-first-name="{{ $fName }}"
                                        data-last-name="{{ $lName }}"
                                        data-email="{{ $cEmail }}"
                                        data-phone="{{ $cPhone }}"
                                        data-code="{{ $cCode }}"
                                        data-position="{{ $cPos }}"
                                        {{ old('user_id') == $unlinkedUser->id ? 'selected' : '' }}>
                                        {{ $unlinkedUser->name }} ({{ $unlinkedUser->email }}) - Role: {{ ucfirst($unlinkedUser->role) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                @endif

                <div class="row">
                    @if(auth()->user()->role === 'admin')
                        <div class="col-md-4 mb-3">
                            <label for="company_id" class="form-label font-weight-semibold">Company <span class="text-danger">*</span></label>
                            <select class="form-select" id="company_id" name="company_id" required>
                                <option value="">Select Company</option>
                                @foreach($companies as $company)
                                    <option value="{{ $company->id }}" {{ (old('company_id', $selectedCompanyId ?? $employee->company_id ?? '') == $company->id) ? 'selected' : '' }}>
                                        {{ $company->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('company_id') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                    @else
                        <input type="hidden" name="company_id" value="{{ $selectedCompanyId ?? auth()->user()->company_id }}">
                    @endif

                    <div class="col-md-4 mb-3">
                        <label for="employee_code" class="form-label font-weight-semibold">Employee Code</label>
                        <input type="text" class="form-control" id="employee_code" name="employee_code" value="{{ old('employee_code', $employee->employee_code ?? '') }}" placeholder="Auto-generated if blank">
                        @error('employee_code') <span class="text-danger small">{{ $message }}</span> @enderror
                    </div>

                    <div class="col-md-4 mb-3">
                        <label for="status" class="form-label font-weight-semibold">Employment Status <span class="text-danger">*</span></label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="active" {{ old('status', $employee->status ?? 'active') == 'active' ? 'selected' : '' }}>Active</option>
                            <option value="inactive" {{ old('status', $employee->status ?? '') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                            <option value="resigned" {{ old('status', $employee->status ?? '') == 'resigned' ? 'selected' : '' }}>Resigned</option>
                        </select>
                        @error('status') <span class="text-danger small">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="first_name" class="form-label font-weight-semibold">First Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="first_name" name="first_name" value="{{ old('first_name', $employee->first_name ?? '') }}" required>
                        @error('first_name') <span class="text-danger small">{{ $message }}</span> @enderror
                    </div>

                    <div class="col-md-4 mb-3">
                        <label for="middle_name" class="form-label font-weight-semibold">Middle Name</label>
                        <input type="text" class="form-control" id="middle_name" name="middle_name" value="{{ old('middle_name', $employee->middle_name ?? '') }}">
                        @error('middle_name') <span class="text-danger small">{{ $message }}</span> @enderror
                    </div>

                    <div class="col-md-4 mb-3">
                        <label for="last_name" class="form-label font-weight-semibold">Last Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="last_name" name="last_name" value="{{ old('last_name', $employee->last_name ?? '') }}" required>
                        @error('last_name') <span class="text-danger small">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="email" class="form-label font-weight-semibold">Email Address</label>
                        <input type="email" class="form-control" id="email" name="email" value="{{ old('email', $employee->email ?? '') }}">
                        @error('email') <span class="text-danger small">{{ $message }}</span> @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="phone" class="form-label font-weight-semibold">Contact Phone</label>
                        <input type="text" class="form-control" id="phone" name="phone" value="{{ old('phone', $employee->phone ?? '') }}">
                        @error('phone') <span class="text-danger small">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="date_of_birth" class="form-label font-weight-semibold">Date of Birth</label>
                        <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" value="{{ old('date_of_birth', $employee->date_of_birth ?? '') }}">
                        @error('date_of_birth') <span class="text-danger small">{{ $message }}</span> @enderror
                    </div>

                    <div class="col-md-4 mb-3">
                        <label for="date_hired" class="form-label font-weight-semibold">Date Hired</label>
                        <input type="date" class="form-control" id="date_hired" name="date_hired" value="{{ old('date_hired', $employee->date_hired ?? '') }}">
                        @error('date_hired') <span class="text-danger small">{{ $message }}</span> @enderror
                    </div>

                    <div class="col-md-4 mb-3">
                        <label for="position" class="form-label font-weight-semibold">Designation / Position</label>
                        <input type="text" class="form-control" id="position" name="position" value="{{ old('position', $employee->position ?? '') }}" placeholder="e.g. Senior Accountant">
                        @error('position') <span class="text-danger small">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="dept_id" class="form-label font-weight-semibold">Department <span class="text-danger">*</span></label>
                        <select class="form-select" id="dept_id" name="dept_id" required>
                            <option value="">Select Department</option>
                            @foreach($departments as $dept)
                                <option value="{{ $dept->id }}" {{ old('dept_id', $employee->dept_id ?? '') == $dept->id ? 'selected' : '' }}>
                                    {{ $dept->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('dept_id') <span class="text-danger small">{{ $message }}</span> @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="sub_dept_id" class="form-label font-weight-semibold">Sub-Department</label>
                        <select class="form-select" id="sub_dept_id" name="sub_dept_id">
                            <option value="">Select Sub-Department</option>
                            @if(isset($subDepartments))
                                @foreach($subDepartments as $sub)
                                    <option value="{{ $sub->id }}" {{ old('sub_dept_id', $employee->sub_dept_id ?? '') == $sub->id ? 'selected' : '' }}>
                                        {{ $sub->name }}
                                    </option>
                                @endforeach
                            @endif
                        </select>
                        @error('sub_dept_id') <span class="text-danger small">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="acct_num" class="form-label font-weight-semibold">Bank Account Number</label>
                        <input type="text" class="form-control" id="acct_num" name="acct_num" value="{{ old('acct_num', $employee->acct_num ?? '') }}" placeholder="e.g. 1234-5678-90">
                        @error('acct_num') <span class="text-danger small">{{ $message }}</span> @enderror
                    </div>

                    <div class="col-md-4 mb-3">
                        <label for="atm_num" class="form-label font-weight-semibold">ATM Card Number</label>
                        <input type="text" class="form-control" id="atm_num" name="atm_num" value="{{ old('atm_num', $employee->atm_num ?? '') }}" placeholder="Card / Payroll No.">
                        @error('atm_num') <span class="text-danger small">{{ $message }}</span> @enderror
                    </div>

                    <div class="col-md-4 mb-3">
                        <label for="biometric_id" class="form-label font-weight-semibold">Biometric Device ID</label>
                        <input type="text" class="form-control" id="biometric_id" name="biometric_id" value="{{ old('biometric_id', $employee->biometric_id ?? '') }}" placeholder="Device User ID">
                        @error('biometric_id') <span class="text-danger small">{{ $message }}</span> @enderror
                    </div>
                </div>

                {{-- Tab 1 Linear Nav --}}
                <div class="d-flex justify-content-end mt-4">
                    <button type="button" class="btn btn-primary" onclick="switchTab('tab-recurring-income')">
                        Next: Recurring Earnings & Deductions <i class="mdi mdi-arrow-right ms-1"></i>
                    </button>
                </div>
            </div>

            {{-- ========================================================================= --}}
            {{-- TAB 2: RECURRING EARNINGS & DEDUCTIONS --}}
            {{-- ========================================================================= --}}
            <div class="tab-pane fade" id="tab-recurring-income" role="tabpanel" aria-labelledby="recurring-income-tab">
                @if(auth()->user()->role !== 'hr' && auth()->user()->role_id !== \App\Models\Role::HR)
                    <h5 class="text-success font-weight-bold mb-3 border-bottom pb-2">
                        <i class="mdi mdi-cash-multiple"></i> Recurring Earnings (Authoritative Source)
                    </h5>

                    <div class="card border mb-4">
                        <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
                            <span class="font-weight-bold text-dark"><i class="mdi mdi-plus-circle me-1 text-success"></i> Income & Earnings Assignments</span>
                            <button type="button" class="btn btn-sm btn-success font-weight-bold" onclick="addFormIncomeRow()">
                                <i class="mdi mdi-plus me-1"></i> Add Income Item
                            </button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 55%;">Income Type (e.g. Basic Pay, Overtime, Allowance)</th>
                                        <th style="width: 35%;">Recurring Amount / Rate (₱)</th>
                                        <th style="width: 10%; text-align: center;">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="form_income_table_body">
                                    {{-- Dynamically populated --}}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Live Derived Rates Preview Card --}}
                    <div class="card bg-light border p-3 mb-4">
                        <h6 class="font-weight-bold text-dark mb-2"><i class="mdi mdi-calculator me-1" style="color: #f06115;"></i> Derived Base Rates & Multipliers (Read-Only Preview)</h6>
                        <small class="text-muted d-block mb-3">Computed automatically from Basic Pay and company payroll policy.</small>
                        <div class="row mb-3">
                            <div class="col-md-4 mb-2">
                                <label class="form-label text-muted small mb-1">Derived Monthly Rate (₱)</label>
                                <input type="text" class="form-control form-control-sm bg-white font-weight-bold text-dark" id="form_preview_monthly" readonly value="0.00">
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="form-label text-muted small mb-1">Derived Daily Rate (₱)</label>
                                <input type="text" class="form-control form-control-sm bg-white font-weight-bold text-dark" id="form_preview_daily" readonly value="0.00">
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="form-label text-muted small mb-1">Derived Hourly Rate (₱)</label>
                                <input type="text" class="form-control form-control-sm bg-white font-weight-bold text-dark" id="form_preview_hourly" readonly value="0.00">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-3 mb-2">
                                <label class="form-label text-muted small mb-1">Regular OT Rate (125%)</label>
                                <input type="text" class="form-control form-control-sm bg-white" id="form_ot_regular_rate" readonly value="0.00">
                            </div>
                            <div class="col-md-3 mb-2">
                                <label class="form-label text-muted small mb-1">Rest Day OT Rate (130%)</label>
                                <input type="text" class="form-control form-control-sm bg-white" id="form_ot_restday_rate" readonly value="0.00">
                            </div>
                            <div class="col-md-3 mb-2">
                                <label class="form-label text-muted small mb-1">Special Holiday OT (130%)</label>
                                <input type="text" class="form-control form-control-sm bg-white" id="form_ot_special_holiday_rate" readonly value="0.00">
                            </div>
                            <div class="col-md-3 mb-2">
                                <label class="form-label text-muted small mb-1">Regular Holiday OT (200%)</label>
                                <input type="text" class="form-control form-control-sm bg-white" id="form_ot_regular_holiday_rate" readonly value="0.00">
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Tab 2 Linear Nav --}}
                <div class="d-flex justify-content-between mt-4">
                    <button type="button" class="btn btn-secondary" onclick="switchTab('tab-basic-info')">
                        <i class="mdi mdi-arrow-left me-1"></i> Previous: Basic Info
                    </button>
                    <button type="button" class="btn btn-primary" onclick="switchTab('tab-statutory')">
                        Next: Statutory Numbers & Settings <i class="mdi mdi-arrow-right ms-1"></i>
                    </button>
                </div>
            </div>

            {{-- ========================================================================= --}}
            {{-- TAB 3: STATUTORY NUMBERS & SETTINGS --}}
            {{-- ========================================================================= --}}
            <div class="tab-pane fade" id="tab-statutory" role="tabpanel" aria-labelledby="statutory-tab">
                <h5 class="text-info font-weight-bold mb-3 border-bottom pb-2">
                    <i class="mdi mdi-shield-check"></i> Government Numbers & Statutory Identification
                </h5>

                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label for="sss_number" class="form-label font-weight-semibold">SSS Number</label>
                        <input type="text" class="form-control" id="sss_number" name="sss_number" value="{{ old('sss_number', $employee->sss_number ?? '') }}" placeholder="XX-XXXXXXX-X">
                        @error('sss_number') <span class="text-danger small">{{ $message }}</span> @enderror
                    </div>

                    <div class="col-md-3 mb-3">
                        <label for="philhealth_number" class="form-label font-weight-semibold">PhilHealth Number</label>
                        <input type="text" class="form-control" id="philhealth_number" name="philhealth_number" value="{{ old('philhealth_number', $employee->philhealth_number ?? '') }}" placeholder="XX-XXXXXXXXX-X">
                        @error('philhealth_number') <span class="text-danger small">{{ $message }}</span> @enderror
                    </div>

                    <div class="col-md-3 mb-3">
                        <label for="pagibig_number" class="form-label font-weight-semibold">Pag-IBIG MID Number</label>
                        <input type="text" class="form-control" id="pagibig_number" name="pagibig_number" value="{{ old('pagibig_number', $employee->pagibig_number ?? '') }}" placeholder="XXXX-XXXX-XXXX">
                        @error('pagibig_number') <span class="text-danger small">{{ $message }}</span> @enderror
                    </div>

                    <div class="col-md-3 mb-3">
                        <label for="tin_number" class="form-label font-weight-semibold">TIN Number</label>
                        <input type="text" class="form-control" id="tin_number" name="tin_number" value="{{ old('tin_number', $employee->tin_number ?? '') }}" placeholder="XXX-XXX-XXX-000">
                        @error('tin_number') <span class="text-danger small">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="tax_code" class="form-label font-weight-semibold">Tax Exemption Code</label>
                        <select class="form-select" id="tax_code" name="tax_code">
                            <option value="">Select Tax Code</option>
                            @foreach($taxCodes as $code => $label)
                                <option value="{{ $code }}" {{ old('tax_code', $employee->tax_code ?? '') == $code ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                        @error('tax_code') <span class="text-danger small">{{ $message }}</span> @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="remarks" class="form-label font-weight-semibold">Employee Remarks / Payroll Notes</label>
                        <textarea class="form-control" id="remarks" name="remarks" rows="2" placeholder="Special payroll configuration or note">{{ old('remarks', $employee->remarks ?? '') }}</textarea>
                        @error('remarks') <span class="text-danger small">{{ $message }}</span> @enderror
                    </div>
                </div>

                {{-- Tab 3 Linear Nav --}}
                <div class="d-flex justify-content-between mt-4">
                    <button type="button" class="btn btn-secondary" onclick="switchTab('tab-recurring-income')">
                        <i class="mdi mdi-arrow-left me-1"></i> Previous: Recurring Earnings & Deductions
                    </button>
                </div>
            </div>

        </div>
    </div>

    {{-- Sticky Submit Footer --}}
    <div class="card-footer bg-white border-top p-3 d-flex justify-content-between align-items-center">
        <div>
            <small class="text-muted"><i class="mdi mdi-information-outline"></i> Data retains across tabs. Submit when ready.</small>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ panelRoute('payroll-employees.index') }}" class="btn btn-secondary" style="background-color: #6c757d; border: none; padding: 8px 24px; font-weight: bold; color: #fff; text-decoration: none;">
                Cancel
            </a>
            <button type="submit" class="btn btn-primary" style="background: linear-gradient(135deg, #1f618d, #2980b9); border: none; padding: 8px 24px; font-weight: bold; color: #fff;">
                <i class="mdi mdi-check"></i> {{ isset($employee) ? 'Update Payroll Employee' : 'Save Payroll Employee' }}
            </button>
        </div>
    </div>
</div>
