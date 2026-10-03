<div class="container-fluid py-3">
    <!-- Header Section -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-0 text-dark font-weight-bold">
                <i class="mdi mdi-view-dashboard-variant text-primary me-2" style="color: #f06115 !important;"></i> Company Master Quick-Entry View
            </h4>
            <small class="text-muted">Interactive quick-entry workspace for employee rates, recurring incomes, and deductions</small>
        </div>
        <div>
            <a href="{{ panelRoute('payroll-employees.index') }}" class="btn btn-secondary" style="background-color: #6c757d; border: none; padding: 8px 18px; font-weight: bold; color: #fff;">
                <i class="mdi mdi-account-group me-1"></i> Employee Record Management
            </a>
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
                        <select class="form-select form-select-sm font-weight-bold" id="filter_company_id" onchange="window.location.href='{{ panelRoute('payroll-employees.company-master') }}?company_id=' + this.value">
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
        <div class="col-md-5 col-lg-4 col-xl-4 mb-3">
            <div class="card border-0 shadow-sm h-100" style="border: 1px solid #e9ecef !important;">
                <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center" style="border-bottom: 2px solid #f06115 !important;">
                    <span class="font-weight-bold text-dark"><i class="mdi mdi-account-group me-1" style="color: #f06115;"></i> Employee Directory</span>
                    <span class="badge ms-2" style="background-color: #f06115; color: white; font-weight: bold;" id="emp_count_badge">{{ $employees->count() }}</span>
                </div>
                <div class="card-body p-2" style="max-height: 72vh; overflow-y: auto;">
                    <div class="list-group list-group-flush" id="employee_directory_list">
                        @forelse($employees as $index => $emp)
                            <button type="button" class="list-group-item list-group-item-action text-start employee-item {{ $index === 0 ? 'active' : '' }}"
                                    data-emp-id="{{ $emp->id }}"
                                    data-dept-id="{{ $emp->dept_id ?? '' }}"
                                    data-search-term="{{ strtolower($emp->full_name . ' ' . $emp->employee_code . ' ' . ($emp->department ?? '')) }}"
                                    onclick="loadEmployeeQuickDetails({{ $emp->id }}, this)">
                                <!-- Row 1: 2 Columns (Name & Rate) -->
                                <div class="row align-items-center g-0 mb-2">
                                    <div class="col">
                                        <span class="font-weight-bold text-dark text-truncate d-block emp-name-text" style="font-size: 14px;" title="{{ $emp->full_name }}">{{ $emp->full_name }}</span>
                                    </div>
                                    <div class="col-auto text-end ps-2">
                                        @if(auth()->user()->role !== 'hr' && auth()->user()->role_id !== \App\Models\Role::HR)
                                            <span class="font-weight-bold emp-monthly-text text-nowrap" style="font-size: 14px; color: #f06115;">₱{{ number_format($emp->monthly_rate, 2) }}</span>
                                        @endif
                                    </div>
                                </div>
                                <!-- Row 2: Merged Full-Width Row ([EMP-CODE] Position) -->
                                <div class="d-flex align-items-center text-truncate">
                                    <span class="badge bg-light text-dark border me-2 emp-code-badge font-weight-semibold" style="font-size: 11px; padding: 3px 8px; border-radius: 4px;">{{ $emp->employee_code }}</span>
                                    <span class="text-muted small text-truncate emp-subtitle-text" style="font-size: 12px;" title="{{ $emp->position ?? 'Staff' }}">{{ $emp->position ?? 'Staff' }}</span>
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

        {{-- RIGHT PANEL: Interactive Entry Pane --}}
        <div class="col-md-7 col-lg-8 col-xl-8">
            <div class="card border-0 shadow-sm" style="border: 1px solid #e9ecef !important;">
                {{-- Active Employee Header --}}
                <div class="card-header bg-white p-3 d-flex justify-content-between align-items-center" style="border-bottom: 2px solid #f06115 !important;">
                    <div>
                        <h5 class="mb-0 font-weight-bold text-dark" id="active_emp_name">Loading Employee...</h5>
                        <small class="text-muted" id="active_emp_subtitle">Code: - | Position: - | Department: -</small>
                    </div>
                    <div>
                        <button type="button" class="btn font-weight-bold px-4 shadow-sm text-white" style="background-color: #f06115; border: none;" onclick="saveQuickMasterData()">
                            <i class="mdi mdi-content-save me-1"></i> Save Quick Master
                        </button>
                    </div>
                </div>

                <div class="card-body p-4" id="pane_loading_spinner" style="display: none;">
                    <div class="text-center py-5">
                        <div class="spinner-border text-warning" role="status" style="color: #f06115 !important;">
                            <span class="visually-hidden">Loading details...</span>
                        </div>
                        <p class="mt-2 text-muted">Fetching employee financial records...</p>
                    </div>
                </div>

                <div class="card-body p-4" id="pane_content_area">
                    @if(auth()->user()->role !== 'hr' && auth()->user()->role_id !== \App\Models\Role::HR)
                        {{-- Income Grid --}}
                        <div class="card border mb-4">
                            <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center" style="border-bottom: 2px solid #e9ecef;">
                                <span class="font-weight-bold text-dark"><i class="mdi mdi-plus-circle me-1" style="color: #f06115;"></i> Recurring Income Grid</span>
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-sm text-white font-weight-bold me-2" style="background-color: #f06115; border: none;" onclick="addIncomeRow()">
                                        <i class="mdi mdi-plus me-1"></i> Add Income
                                    </button>
                                    <button type="button" class="btn btn-outline-danger btn-sm font-weight-semibold" onclick="deleteUnusedIncomes()"><i class="mdi mdi-trash-can-outline"></i> Delete Unused ($0)</button>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0" id="income-table">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 40%;">Income Type</th>
                                            <th style="width: 20%;">Number / Hours</th>
                                            <th style="width: 20%;">Rate (₱)</th>
                                            <th style="width: 20%;">Total Amount (₱)</th>
                                            <th style="width: 50px;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="income_table_body">
                                        {{-- Dynamically populated --}}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                    {{-- Deduction Grid --}}
                    <div class="card border mb-4">
                        <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center" style="border-bottom: 2px solid #e9ecef;">
                            <span class="font-weight-bold text-dark"><i class="mdi mdi-minus-circle me-1" style="color: #dc3545;"></i> Recurring Deduction Grid</span>
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-sm btn-outline-danger font-weight-bold me-2" onclick="addDeductionRow()">
                                    <i class="mdi mdi-plus me-1"></i> Add Deduction
                                </button>
                                <button type="button" class="btn btn-outline-danger btn-sm font-weight-semibold" onclick="deleteUnusedDeductions()"><i class="mdi mdi-trash-can-outline"></i> Delete Unused ($0)</button>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0" id="deduction-table">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 50%;">Deduction Name</th>
                                        <th style="width: 40%;">Amount / Rate (₱)</th>
                                        <th style="width: 50px;">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="deduction_table_body">
                                    {{-- Dynamically populated --}}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Live Summary Footer --}}
                    @php
                        $isHr = auth()->user()->role === 'hr' || auth()->user()->role_id === \App\Models\Role::HR;
                    @endphp
                    <div class="card border-0 shadow-sm" style="background-color: #f8fafc; border: 1px solid #e9ecef !important; border-top: 4px solid #f06115 !important;">
                        <div class="card-body p-3">
                            <div class="row text-center align-items-center">
                                @if(!$isHr)
                                    <div class="col-md-4 border-end border-light mb-2 mb-md-0">
                                        <span class="text-uppercase small text-muted font-weight-bold d-block">Estimated Gross Pay</span>
                                        <h4 class="mb-0 font-weight-bold text-dark" id="badge_gross_pay">₱ 0.00</h4>
                                    </div>
                                @endif
                                <div class="{{ $isHr ? 'col-md-12' : 'col-md-4 border-end border-light mb-2 mb-md-0' }}">
                                    <span class="text-uppercase small text-muted font-weight-bold d-block">Total Deductions</span>
                                    <h4 class="mb-0 font-weight-bold text-dark" id="badge_total_deductions">₱ 0.00</h4>
                                </div>
                                @if(!$isHr)
                                    <div class="col-md-4">
                                        <span class="text-uppercase small text-muted font-weight-bold d-block">Estimated Net Pay</span>
                                        <h4 class="mb-0 font-weight-bold" style="color: #f06115;" id="badge_net_pay">₱ 0.00</h4>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>
