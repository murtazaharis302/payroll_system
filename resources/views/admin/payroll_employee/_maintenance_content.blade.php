<div class="container-fluid py-3">
    <!-- Header Section -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-0 text-dark font-weight-bold">
                <i class="mdi mdi-account-group text-primary me-2" style="color: #f06115 !important;"></i> Employee Record Management
            </h4>
            <small class="text-muted">Interactive quick-entry workspace for full employee profiles, compensation, and recurring items</small>
        </div>
        <div>
            @if(auth()->user()->role !== 'hr' && auth()->user()->role_id !== \App\Models\Role::HR)
                <a href="{{ panelRoute('payroll-employees.create') }}" class="btn font-weight-bold shadow-sm text-white" style="background-color: #f06115; border: none; padding: 8px 18px;">
                    <i class="mdi mdi-account-plus me-1"></i> Create Payroll Employee
                </a>
            @endif
        </div>
    </div>

    <!-- Top Filter Bar Card -->
    <div class="card border-0 shadow-sm mb-3 bg-white" style="border: 1px solid #e9ecef !important;">
        <div class="card-body p-3">
            <div class="row align-items-end">
                {{-- Company Selector --}}
                <div class="col-md-4 mb-2 mb-md-0">
                    <label for="filter_company_id" class="form-label font-weight-semibold text-dark small mb-1">Company</label>
                    @php
                        $activeCompName = $companies->firstWhere('id', $selectedCompanyId)?->name ?? ($companies->first()?->name ?? 'Select Company');
                    @endphp
                    @if(auth()->user()->role !== 'admin' && auth()->user()->role_id !== \App\Models\Role::SUPER_ADMIN && $companies->count() <= 1)
                        <input type="hidden" id="filter_company_id" value="{{ $selectedCompanyId }}">
                        <input type="text" class="form-control form-control-sm font-weight-bold" value="{{ $activeCompName }}" readonly style="background-color: #f8f9fa;">
                    @else
                        <select class="form-select form-select-sm font-weight-bold" id="filter_company_id" onchange="window.location.href='{{ panelRoute('payroll-employees.index') }}?company_id=' + this.value">
                            @foreach($companies as $company)
                                <option value="{{ $company->id }}" {{ $selectedCompanyId == $company->id ? 'selected' : '' }}>
                                    {{ $company->name }}
                                </option>
                            @endforeach
                        </select>
                    @endif
                </div>

                {{-- Department Filter --}}
                <div class="col-md-4 mb-2 mb-md-0">
                    <label for="filter_department_id" class="form-label font-weight-semibold text-dark small mb-1">Department</label>
                    <select class="form-select form-select-sm font-weight-bold" id="filter_department_id" onchange="filterEmployeeDirectory()">
                        <option value="">All Departments</option>
                        @foreach($departments as $dept)
                            <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Quick Search Bar --}}
                <div class="col-md-4">
                    <label for="employee_quick_search" class="form-label font-weight-semibold text-dark small mb-1">Quick Search Employee</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light border-end-0"><i class="mdi mdi-magnify"></i></span>
                        <input type="text" id="employee_quick_search" class="form-control border-start-0" placeholder="Search employee name or code..." onkeyup="filterEmployeeDirectory()">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Split Pane Workspace -->
    <div class="row">
        {{-- LEFT PANEL: Directory Grid --}}
        <div class="col-md-4 col-lg-4 col-xl-4 mb-3">
            <div class="card border-0 shadow-sm h-100" style="border: 1px solid #e9ecef !important;">
                <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center" style="border-bottom: 2px solid #f06115 !important;">
                    <span class="font-weight-bold text-dark"><i class="mdi mdi-account-group me-1" style="color: #f06115;"></i> Employee Directory</span>
                    <span class="badge ms-2" style="background-color: #f06115; color: white; font-weight: bold;" id="emp_count_badge">{{ $employees->count() }}</span>
                </div>
                <div class="card-body p-2" style="max-height: 75vh; overflow-y: auto;">
                    <div class="list-group list-group-flush" id="employee_directory_list">
                        @forelse($employees as $index => $emp)
                            <button type="button" class="list-group-item list-group-item-action text-start employee-item {{ $index === 0 ? 'active' : '' }}"
                                    data-emp-id="{{ $emp->id }}"
                                    data-dept-id="{{ $emp->dept_id ?? '' }}"
                                    data-search-term="{{ strtolower($emp->full_name . ' ' . $emp->employee_code . ' ' . ($emp->department ?? '')) }}"
                                    onclick="loadEmployeeRecordDetails({{ $emp->id }}, this)">
                                <!-- Row 1: Name & Status -->
                                <div class="row align-items-center g-0 mb-1">
                                    <div class="col">
                                        <span class="font-weight-bold text-dark text-truncate d-block emp-name-text" style="font-size: 14px;" id="emp_list_name_{{ $emp->id }}">{{ $emp->full_name }}</span>
                                    </div>
                                    <div class="col-auto text-end ps-2">
                                        @if($emp->status == 'active')
                                            <span class="badge bg-success small">Active</span>
                                        @elseif($emp->status == 'inactive')
                                            <span class="badge bg-secondary small">Inactive</span>
                                        @else
                                            <span class="badge bg-danger small">Resigned</span>
                                        @endif
                                    </div>
                                </div>
                                <!-- Row 2: Code & Position -->
                                <div class="d-flex align-items-center text-truncate">
                                    <span class="badge bg-light text-dark border me-2 emp-code-badge font-weight-semibold" style="font-size: 11px; padding: 3px 8px; border-radius: 4px;" id="emp_list_code_{{ $emp->id }}">{{ $emp->employee_code }}</span>
                                    <span class="text-muted small text-truncate emp-subtitle-text" style="font-size: 12px;" id="emp_list_pos_{{ $emp->id }}">{{ $emp->position ?? 'Staff' }}</span>
                                </div>
                            </button>
                        @empty
                            <div class="p-4 text-center text-muted">
                                <i class="mdi mdi-account-off display-4 d-block opacity-50 mb-2"></i>
                                No employees found for selected company.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        {{-- RIGHT PANEL: Tabbed Employee Record Maintenance Editor --}}
        <div class="col-md-8 col-lg-8 col-xl-8">
            <div class="card border-0 shadow-sm" style="border: 1px solid #e9ecef !important;">
                {{-- Active Employee Header --}}
                <div class="card-header bg-white p-3 d-flex justify-content-between align-items-center" style="border-bottom: 2px solid #f06115 !important;">
                    <div>
                        <h5 class="mb-0 font-weight-bold text-dark" id="active_emp_name">Loading Employee...</h5>
                        <small class="text-muted" id="active_emp_subtitle">Code: - | Position: - | Department: -</small>
                    </div>
                    <div>
                        <button type="button" class="btn font-weight-bold px-4 shadow-sm text-white" style="background-color: #f06115; border: none;" onclick="saveEmployeeRecord()">
                            <i class="mdi mdi-content-save me-1"></i> Save Employee Record
                        </button>
                    </div>
                </div>

                <div class="card-body p-4" id="pane_loading_spinner" style="display: none;">
                    <div class="text-center py-5">
                        <div class="spinner-border text-warning" role="status" style="color: #f06115 !important;">
                            <span class="visually-hidden">Loading profile...</span>
                        </div>
                        <p class="mt-2 text-muted">Fetching complete employee profile...</p>
                    </div>
                </div>

                <div class="card-body p-4" id="pane_content_area">
                    <!-- Nav Tabs -->
                    <ul class="nav nav-tabs nav-bordered mb-4" id="maintenance_tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active font-weight-bold" id="tab-basic-link" data-bs-toggle="tab" href="#tab-basic" role="tab">
                                <i class="mdi mdi-account-card-details me-1"></i> 1. Basic & Personal Info
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link font-weight-bold" id="tab-recurring-link" data-bs-toggle="tab" href="#tab-recurring" role="tab">
                                <i class="mdi mdi-tune me-1"></i> 2. Recurring Earnings & Deductions
                            </a>
                        </li>
                    </ul>

                    <!-- Tab Content -->
                    <div class="tab-content" id="maintenance_tab_content">
                        {{-- TAB 1: BASIC & PERSONAL INFO --}}
                        <div class="tab-pane fade show active" id="tab-basic" role="tabpanel">
                            <form id="form_employee_basic">
                                <div class="row mb-3">
                                     <div class="col-md-4 mb-2">
                                        <label for="rec_employee_code" class="form-label font-weight-semibold small text-dark">Employee Code</label>
                                        <input type="text" id="rec_employee_code" class="form-control form-control-sm" placeholder="Auto-generated if blank">
                                    </div>
                                    <div class="col-md-4 mb-2">
                                        <label for="rec_first_name" class="form-label font-weight-semibold small text-dark">First Name *</label>
                                        <input type="text" id="rec_first_name" class="form-control form-control-sm" required>
                                    </div>
                                    <div class="col-md-4 mb-2">
                                        <label for="rec_middle_name" class="form-label font-weight-semibold small text-dark">Middle Name</label>
                                        <input type="text" id="rec_middle_name" class="form-control form-control-sm">
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-4 mb-2">
                                        <label for="rec_last_name" class="form-label font-weight-semibold small text-dark">Last Name *</label>
                                        <input type="text" id="rec_last_name" class="form-control form-control-sm" required>
                                    </div>
                                    <div class="col-md-4 mb-2">
                                        <label for="rec_email" class="form-label font-weight-semibold small text-dark">Email Address</label>
                                        <input type="email" id="rec_email" class="form-control form-control-sm">
                                    </div>
                                    <div class="col-md-4 mb-2">
                                        <label for="rec_phone" class="form-label font-weight-semibold small text-dark">Contact Phone</label>
                                        <input type="text" id="rec_phone" class="form-control form-control-sm">
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-4 mb-2">
                                        <label for="rec_date_of_birth" class="form-label font-weight-semibold small text-dark">Date of Birth</label>
                                        <input type="date" id="rec_date_of_birth" class="form-control form-control-sm">
                                    </div>
                                    <div class="col-md-4 mb-2">
                                        <label for="rec_date_hired" class="form-label font-weight-semibold small text-dark">Date Hired</label>
                                        <input type="date" id="rec_date_hired" class="form-control form-control-sm">
                                    </div>
                                    <div class="col-md-4 mb-2">
                                        <label for="rec_position" class="form-label font-weight-semibold small text-dark">Job Position</label>
                                        <input type="text" id="rec_position" class="form-control form-control-sm">
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-6 mb-2">
                                        <label for="rec_dept_id" class="form-label font-weight-semibold small text-dark">Department</label>
                                        <select id="rec_dept_id" class="form-select form-select-sm" onchange="loadSubDepartmentsForSelectedDept()">
                                            <option value="">-- Select Department --</option>
                                            @foreach($departments as $dept)
                                                <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <label for="rec_sub_dept_id" class="form-label font-weight-semibold small text-dark">Sub-Department</label>
                                        <select id="rec_sub_dept_id" class="form-select form-select-sm">
                                            <option value="">-- Select Sub-Department --</option>
                                        </select>
                                    </div>
                                </div>

                                <hr class="my-3 text-muted">
                                <h6 class="font-weight-bold text-dark mb-3"><i class="mdi mdi-bank-outline me-1"></i> Banking & Statutory Identification</h6>

                                <div class="row mb-3">
                                    <div class="col-md-4 mb-2">
                                        <label for="rec_acct_num" class="form-label font-weight-semibold small text-dark">Bank Account No.</label>
                                        <input type="text" id="rec_acct_num" class="form-control form-control-sm" placeholder="e.g. 1234-5678-90">
                                    </div>
                                    <div class="col-md-4 mb-2">
                                        <label for="rec_atm_num" class="form-label font-weight-semibold small text-dark">ATM Card No.</label>
                                        <input type="text" id="rec_atm_num" class="form-control form-control-sm" placeholder="Card / Payroll No.">
                                    </div>
                                    <div class="col-md-4 mb-2">
                                        <label for="rec_biometric_id" class="form-label font-weight-semibold small text-dark">Biometric Device ID</label>
                                        <input type="text" id="rec_biometric_id" class="form-control form-control-sm" placeholder="Device Bio ID">
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-3 mb-2">
                                        <label for="rec_sss_number" class="form-label font-weight-semibold small text-dark">SSS No.</label>
                                        <input type="text" id="rec_sss_number" class="form-control form-control-sm">
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <label for="rec_philhealth_number" class="form-label font-weight-semibold small text-dark">PhilHealth No.</label>
                                        <input type="text" id="rec_philhealth_number" class="form-control form-control-sm">
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <label for="rec_pagibig_number" class="form-label font-weight-semibold small text-dark">Pag-IBIG HDMF No.</label>
                                        <input type="text" id="rec_pagibig_number" class="form-control form-control-sm">
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <label for="rec_tin_number" class="form-label font-weight-semibold small text-dark">TIN Tax No.</label>
                                        <input type="text" id="rec_tin_number" class="form-control form-control-sm">
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-4 mb-2">
                                        <label for="rec_tax_code" class="form-label font-weight-semibold small text-dark">Tax Exemption Code</label>
                                        <select id="rec_tax_code" class="form-select form-select-sm">
                                            <option value="SINGLE">SINGLE</option>
                                            <option value="MARRIED">MARRIED</option>
                                            <option value="S1">S1 / M1 (1 Dependent)</option>
                                            <option value="S2">S2 / M2 (2 Dependents)</option>
                                            <option value="S3">S3 / M3 (3 Dependents)</option>
                                            <option value="S4">S4 / M4 (4 Dependents)</option>
                                            <option value="EXEMPT">TAX EXEMPT</option>
                                        </select>
                                    </div>
                                    <div class="col-md-8 mb-2">
                                        <label for="rec_remarks" class="form-label font-weight-semibold small text-dark">Remarks / Notes</label>
                                        <input type="text" id="rec_remarks" class="form-control form-control-sm" placeholder="Additional HR notes...">
                                    </div>
                                </div>
                            </form>
                        </div>

                        {{-- TAB 2: RECURRING EARNINGS & DEDUCTIONS --}}
                        <div class="tab-pane fade" id="tab-recurring" role="tabpanel">
                            @if(auth()->user()->role !== 'hr' && auth()->user()->role_id !== \App\Models\Role::HR)
                                {{-- Income Grid --}}
                                <div class="card border mb-4">
                                    <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center" style="border-bottom: 2px solid #e9ecef;">
                                        <div>
                                            <span class="font-weight-bold text-dark"><i class="mdi mdi-cash-multiple me-1" style="color: #f06115;"></i> Recurring Income Assignments</span>
                                            <small class="text-muted d-block" style="font-size: 11px;">Authoritative earnings repository (Basic Pay, Overtime rate, Allowances, Bonuses)</small>
                                        </div>
                                        <button type="button" class="btn btn-sm text-white font-weight-bold" style="background-color: #f06115; border: none;" onclick="addMaintIncomeRow()">
                                            <i class="mdi mdi-plus me-1"></i> Add Income
                                        </button>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover align-middle mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th style="width: 50%;">Income Type</th>
                                                    <th style="width: 40%;">Recurring Amount / Rate (₱)</th>
                                                    <th style="width: 50px;">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody id="maint_income_table_body">
                                                {{-- Dynamically populated --}}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                {{-- Read-Only Derived Rates Preview --}}
                                <div class="card bg-light border mb-4">
                                    <div class="card-body p-3">
                                        <h6 class="font-weight-bold text-dark mb-2"><i class="mdi mdi-calculator me-1" style="color: #f06115;"></i> Derived Rates & Multipliers (Read-Only Preview)</h6>
                                        <small class="text-muted d-block mb-3">Computed automatically from Basic Pay and company payroll policy.</small>
                                        <div class="row mb-3">
                                            <div class="col-md-4 mb-2">
                                                <label class="form-label text-muted small mb-1">Derived Monthly Rate (₱)</label>
                                                <input type="text" id="rec_preview_monthly_rate" class="form-control form-control-sm bg-white font-weight-bold text-dark" readonly value="0.00">
                                            </div>
                                            <div class="col-md-4 mb-2">
                                                <label class="form-label text-muted small mb-1">Derived Daily Rate (₱)</label>
                                                <input type="text" id="rec_preview_daily_rate" class="form-control form-control-sm bg-white font-weight-bold text-dark" readonly value="0.00">
                                            </div>
                                            <div class="col-md-4 mb-2">
                                                <label class="form-label text-muted small mb-1">Derived Hourly Rate (₱)</label>
                                                <input type="text" id="rec_preview_hourly_rate" class="form-control form-control-sm bg-white font-weight-bold text-dark" readonly value="0.00">
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-3 mb-2">
                                                <label class="form-label text-muted small mb-1">Regular OT (125%)</label>
                                                <input type="text" class="form-control form-control-sm bg-white" id="rec_ot_regular_rate" readonly value="0.00">
                                            </div>
                                            <div class="col-md-3 mb-2">
                                                <label class="form-label text-muted small mb-1">Rest Day OT (130%)</label>
                                                <input type="text" class="form-control form-control-sm bg-white" id="rec_ot_restday_rate" readonly value="0.00">
                                            </div>
                                            <div class="col-md-3 mb-2">
                                                <label class="form-label text-muted small mb-1">Special Holiday (130%)</label>
                                                <input type="text" class="form-control form-control-sm bg-white" id="rec_ot_special_rate" readonly value="0.00">
                                            </div>
                                            <div class="col-md-3 mb-2">
                                                <label class="form-label text-muted small mb-1">Regular Holiday (200%)</label>
                                                <input type="text" class="form-control form-control-sm bg-white" id="rec_ot_regular_holiday_rate" readonly value="0.00">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            {{-- Deduction Grid --}}
                            <div class="card border mb-3">
                                <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center" style="border-bottom: 2px solid #e9ecef;">
                                    <span class="font-weight-bold text-dark"><i class="mdi mdi-minus-circle me-1" style="color: #dc3545;"></i> Recurring Deduction Assignments</span>
                                    <button type="button" class="btn btn-sm btn-outline-danger font-weight-bold" onclick="addMaintDeductionRow()">
                                        <i class="mdi mdi-plus me-1"></i> Add Deduction
                                    </button>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width: 50%;">Deduction Name</th>
                                                <th style="width: 40%;">Amount / Rate (₱)</th>
                                                <th style="width: 50px;">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody id="maint_deduction_table_body">
                                            {{-- Dynamically populated --}}
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="d-flex justify-content-end mt-3">
                                <button type="button" class="btn font-weight-bold shadow-sm text-white" style="background-color: #f06115; border: none; padding: 8px 24px;" onclick="saveEmployeeRecord()">
                                    <i class="mdi mdi-content-save me-1"></i> Save Recurring Items
                                </button>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>
