        <!-- ==================== TAB 1: PAYROLL & SLIPS ==================== -->
        <div class="tab-pane fade show active" id="pane-payroll" role="tabpanel" aria-labelledby="tab-payroll">
            <div class="row">

                <!-- 1. Payroll Summary 1 (Master Sheet) -->
                <div class="col-12 grid-margin stretch-card mb-3 report-card-item" data-category="payroll" data-keywords="payroll summary 1 master sheet department pdf excel csv earnings deductions">
                    <div class="card border-0 shadow-sm border-start border-4 border-primary h-100">
                        <div class="card-header bg-white py-3 border-0 d-flex align-items-center justify-content-between cursor-pointer" data-bs-toggle="collapse" data-bs-target="#collapse-ps1">
                            <div class="d-flex align-items-center me-2">
                                <div class="avatar-sm rounded bg-soft-primary text-primary d-flex align-items-center justify-content-center me-3 flex-shrink-0" style="width:40px; height:40px;">
                                    <i class="fe-file-text font-18"></i>
                                </div>
                                <div>
                                    <h5 class="fw-bold text-dark font-15 mb-1">
                                        Payroll Summary 1 (Master Sheet)
                                        <span class="badge bg-soft-primary text-primary font-11 ms-2">Master Sheet</span>
                                    </h5>
                                    <p class="text-muted font-12 mb-0">Executive master sheet with dynamic earnings & deduction overflow (PDF, Excel, CSV)</p>
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm btn-light border text-nowrap font-12" data-bs-toggle="collapse" data-bs-target="#collapse-ps1">
                                <i class="fe-chevron-down me-1"></i> Expand Filters
                            </button>
                        </div>
                        <div id="collapse-ps1" class="collapse">
                            <div class="card-body border-top bg-light-subtle pt-3">
                                <form action="{{ panelRoute('reports.payroll-summary-1') }}" method="POST" target="_blank">
                                    @csrf
                                    @if(isset($selectedCompanyId)) <input type="hidden" name="company_id" value="{{ $selectedCompanyId }}"> @endif
                                    <div class="form-group mb-2">
                                        <label class="form-label font-12 fw-semibold">Payroll Period <span class="text-danger">*</span></label>
                                        <x-payroll-period-select name="payroll_period_id" required />
                                    </div>
                                    <div class="row g-2 mb-2">
                                        <div class="col-md-6">
                                            <label class="form-label font-12 fw-semibold">Department</label>
                                            <select name="department_id" class="form-select form-select-sm">
                                                <option value="all">All Departments</option>
                                                @php $targetDepts = isset($departments) ? $departments : \App\Models\Department::where('is_active', true)->when(isset($selectedCompanyId) && $selectedCompanyId, fn($q) => $q->where('company_id', $selectedCompanyId))->orderBy('name')->get(); @endphp
                                                @foreach($targetDepts as $dept)
                                                <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label font-12 fw-semibold">Export Format</label>
                                            <select name="export_format" class="form-select form-select-sm">
                                                <option value="pdf" selected>PDF Document</option>
                                                <option value="xlsx">Excel Sheet (.xlsx)</option>
                                                <option value="csv">CSV File (.csv)</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="form-group mb-3">
                                        <label class="form-label font-12 fw-semibold">Paper Size (PDF)</label>
                                        <select name="paper_size" class="form-select form-select-sm">
                                            <option value="legal" selected>Legal (Landscape)</option>
                                            <option value="letter">Letter (Landscape)</option>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-sm btn-primary w-100 fw-semibold">
                                        <i class="fe-download me-1"></i> Generate Payroll Summary 1
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. Payroll Summary 2 (Departmental) -->
                <div class="col-12 grid-margin stretch-card mb-3 report-card-item" data-category="payroll" data-keywords="payroll summary 2 report department pdf excel csv letter legal">
                    <div class="card border-0 shadow-sm border-start border-4 border-info h-100">
                        <div class="card-header bg-white py-3 border-0 d-flex align-items-center justify-content-between cursor-pointer" data-bs-toggle="collapse" data-bs-target="#collapse-ps2">
                            <div class="d-flex align-items-center me-2">
                                <div class="avatar-sm rounded bg-soft-info text-info d-flex align-items-center justify-content-center me-3 flex-shrink-0" style="width:40px; height:40px;">
                                    <i class="fe-list font-18"></i>
                                </div>
                                <div>
                                    <h5 class="fw-bold text-dark font-15 mb-1">
                                        Payroll Summary 2 Report
                                        <span class="badge bg-soft-info text-info font-11 ms-2">Departmental</span>
                                    </h5>
                                    <p class="text-muted font-12 mb-0">Department-scoped payroll summary breakdown (PDF, Excel, CSV)</p>
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm btn-light border text-nowrap font-12" data-bs-toggle="collapse" data-bs-target="#collapse-ps2">
                                <i class="fe-chevron-down me-1"></i> Expand Filters
                            </button>
                        </div>
                        <div id="collapse-ps2" class="collapse">
                            <div class="card-body border-top bg-light-subtle pt-3">
                                <form action="{{ panelRoute('reports.payroll-summary-2') }}" method="POST" target="_blank">
                                    @csrf
                                    @if(isset($selectedCompanyId)) <input type="hidden" name="company_id" value="{{ $selectedCompanyId }}"> @endif
                                    <div class="form-group mb-2">
                                        <label class="form-label font-12 fw-semibold">Payroll Period <span class="text-danger">*</span></label>
                                        <x-payroll-period-select name="payroll_period_id" required />
                                    </div>
                                    <div class="row g-2 mb-2">
                                        <div class="col-md-6">
                                            <label class="form-label font-12 fw-semibold">Department</label>
                                            <select name="department_id" class="form-select form-select-sm">
                                                <option value="all">All Departments</option>
                                                @php $targetDepts = isset($departments) ? $departments : \App\Models\Department::where('is_active', true)->when(isset($selectedCompanyId) && $selectedCompanyId, fn($q) => $q->where('company_id', $selectedCompanyId))->orderBy('name')->get(); @endphp
                                                @foreach($targetDepts as $dept)
                                                <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label font-12 fw-semibold">Export Format</label>
                                            <select name="export_format" class="form-select form-select-sm">
                                                <option value="pdf" selected>PDF Document</option>
                                                <option value="xlsx">Excel Sheet (.xlsx)</option>
                                                <option value="csv">CSV File (.csv)</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="form-group mb-3">
                                        <label class="form-label font-12 fw-semibold">Paper Size (PDF)</label>
                                        <select name="paper_size" class="form-select form-select-sm">
                                            <option value="legal" selected>Legal (Landscape)</option>
                                            <option value="letter">Letter (Landscape)</option>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-sm btn-info text-white w-100 fw-semibold">
                                        <i class="fe-download me-1"></i> Generate Payroll Summary 2
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 3. Cash Voucher (2-Up) -->
                <div class="col-12 grid-margin stretch-card mb-3 report-card-item" data-category="payroll" data-keywords="cash voucher payment slip 2-up half page gross earnings particulars">
                    <div class="card border-0 shadow-sm border-start border-4 border-purple h-100" style="border-left-color: #6f42c1 !important;">
                        <div class="card-header bg-white py-3 border-0 d-flex align-items-center justify-content-between cursor-pointer" data-bs-toggle="collapse" data-bs-target="#collapse-voucher">
                            <div class="d-flex align-items-center me-2">
                                <div class="avatar-sm rounded bg-soft-purple text-purple d-flex align-items-center justify-content-center me-3 flex-shrink-0" style="width:40px; height:40px; background-color: #f4ecf7; color: #6f42c1;">
                                    <i class="fe-credit-card font-18"></i>
                                </div>
                                <div>
                                    <h5 class="fw-bold text-dark font-15 mb-1">
                                        Cash Voucher (2-Up Slip)
                                        <span class="badge bg-soft-purple text-purple font-11 ms-2" style="background-color:#f4ecf7; color:#6f42c1;">Voucher</span>
                                    </h5>
                                    <p class="text-muted font-12 mb-0">Generate 2-up cash disbursement slips with spelled-out PESOS words</p>
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm btn-light border text-nowrap font-12" data-bs-toggle="collapse" data-bs-target="#collapse-voucher">
                                <i class="fe-chevron-down me-1"></i> Expand Filters
                            </button>
                        </div>
                        <div id="collapse-voucher" class="collapse">
                            <div class="card-body border-top bg-light-subtle pt-3">
                                <p class="font-12 text-muted mb-2">Click below to configure detailed pre-generation filters (Department, Voucher Header, and Item Visibility Checklist):</p>
                                <button type="button" class="btn btn-sm btn-purple text-white w-100 fw-semibold" style="background-color: #6f42c1; border-color: #6f42c1;" data-bs-toggle="modal" data-bs-target="#cashVoucherModal">
                                    <i class="fe-sliders me-1"></i> Open Voucher Pre-Generation Modal
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 4. Batch Payslips -->
                <div class="col-12 grid-margin stretch-card mb-3 report-card-item" data-category="payroll" data-keywords="batch payslips generate all payslips pdf period">
                    <div class="card border-0 shadow-sm border-start border-4 border-success h-100">
                        <div class="card-header bg-white py-3 border-0 d-flex align-items-center justify-content-between cursor-pointer" data-bs-toggle="collapse" data-bs-target="#collapse-batch-payslips">
                            <div class="d-flex align-items-center me-2">
                                <div class="avatar-sm rounded bg-soft-success text-success d-flex align-items-center justify-content-center me-3 flex-shrink-0" style="width:40px; height:40px;">
                                    <i class="fe-layers font-18"></i>
                                </div>
                                <div>
                                    <h5 class="fw-bold text-dark font-15 mb-1">
                                        Batch Generate Payslips
                                        <span class="badge bg-soft-success text-success font-11 ms-2">Batch Export</span>
                                    </h5>
                                    <p class="text-muted font-12 mb-0">Generate all employee payslips for a period into a single combined PDF</p>
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm btn-light border text-nowrap font-12" data-bs-toggle="collapse" data-bs-target="#collapse-batch-payslips">
                                <i class="fe-chevron-down me-1"></i> Expand Filters
                            </button>
                        </div>
                        <div id="collapse-batch-payslips" class="collapse">
                            <div class="card-body border-top bg-light-subtle pt-3">
                                <form action="{{ panelRoute('reports.batch-payslips') }}" method="POST" target="_blank">
                                    @csrf
                                    @if(isset($selectedCompanyId)) <input type="hidden" name="company_id" value="{{ $selectedCompanyId }}"> @endif
                                    <div class="form-group mb-3">
                                        <label class="form-label font-12 fw-semibold">Payroll Period <span class="text-danger">*</span></label>
                                        <x-payroll-period-select name="payroll_period_id" required />
                                    </div>
                                    <button type="submit" class="btn btn-sm btn-success w-100 fw-semibold">
                                        <i class="fe-file-text me-1"></i> Generate All Payslips PDF
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 5. Individual Payslip -->
                <div class="col-12 grid-margin stretch-card mb-3 report-card-item" data-category="payroll" data-keywords="payslip individual employee pdf pay slip">
                    <div class="card border-0 shadow-sm border-start border-4 border-secondary h-100">
                        <div class="card-header bg-white py-3 border-0 d-flex align-items-center justify-content-between cursor-pointer" data-bs-toggle="collapse" data-bs-target="#collapse-payslip">
                            <div class="d-flex align-items-center me-2">
                                <div class="avatar-sm rounded bg-soft-secondary text-secondary d-flex align-items-center justify-content-center me-3 flex-shrink-0" style="width:40px; height:40px;">
                                    <i class="fe-user font-18"></i>
                                </div>
                                <div>
                                    <h5 class="fw-bold text-dark font-15 mb-1">
                                        Individual Payslip
                                        <span class="badge bg-soft-secondary text-secondary font-11 ms-2">Single Period</span>
                                    </h5>
                                    <p class="text-muted font-12 mb-0">Generate individual employee payslip (PDF format)</p>
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm btn-light border text-nowrap font-12" data-bs-toggle="collapse" data-bs-target="#collapse-payslip">
                                <i class="fe-chevron-down me-1"></i> Expand Filters
                            </button>
                        </div>
                        <div id="collapse-payslip" class="collapse">
                            <div class="card-body border-top bg-light-subtle pt-3">
                                <form action="{{ panelRoute('reports.payslip') }}" method="POST" target="_blank">
                                    @csrf
                                    @if(isset($selectedCompanyId)) <input type="hidden" name="company_id" value="{{ $selectedCompanyId }}"> @endif
                                    <div class="form-group mb-2">
                                        <label class="form-label font-12 fw-semibold">Payroll Period <span class="text-danger">*</span></label>
                                        <x-payroll-period-select name="payroll_period_id" required />
                                    </div>
                                    <div class="form-group mb-3">
                                        <label class="form-label font-12 fw-semibold">Employee <span class="text-danger">*</span></label>
                                        <x-employee-select name="employee_id" companyId="{{ $selectedCompanyId ?? null }}" :employees="$employees ?? null" required />
                                    </div>
                                    <button type="submit" class="btn btn-sm btn-secondary w-100 fw-semibold">
                                        <i class="fe-file-text me-1"></i> Generate Payslip PDF
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 6. Payroll Register -->
                <div class="col-12 grid-margin stretch-card mb-3 report-card-item" data-category="payroll" data-keywords="payroll register summary all employees pdf">
                    <div class="card border-0 shadow-sm border-start border-4 border-dark h-100">
                        <div class="card-header bg-white py-3 border-0 d-flex align-items-center justify-content-between cursor-pointer" data-bs-toggle="collapse" data-bs-target="#collapse-payroll-register">
                            <div class="d-flex align-items-center me-2">
                                <div class="avatar-sm rounded bg-soft-dark text-dark d-flex align-items-center justify-content-center me-3 flex-shrink-0" style="width:40px; height:40px;">
                                    <i class="fe-grid font-18"></i>
                                </div>
                                <div>
                                    <h5 class="fw-bold text-dark font-15 mb-1">
                                        Payroll Register
                                        <span class="badge bg-soft-dark text-dark font-11 ms-2">Single Period</span>
                                    </h5>
                                    <p class="text-muted font-12 mb-0">Generate complete payroll register for all employees (PDF)</p>
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm btn-light border text-nowrap font-12" data-bs-toggle="collapse" data-bs-target="#collapse-payroll-register">
                                <i class="fe-chevron-down me-1"></i> Expand Filters
                            </button>
                        </div>
                        <div id="collapse-payroll-register" class="collapse">
                            <div class="card-body border-top bg-light-subtle pt-3">
                                <form action="{{ panelRoute('reports.payroll-register') }}" method="POST" target="_blank">
                                    @csrf
                                    @if(isset($selectedCompanyId)) <input type="hidden" name="company_id" value="{{ $selectedCompanyId }}"> @endif
                                    <div class="form-group mb-3">
                                        <label class="form-label font-12 fw-semibold">Payroll Period <span class="text-danger">*</span></label>
                                        <x-payroll-period-select name="payroll_period_id" required />
                                    </div>
                                    <button type="submit" class="btn btn-sm btn-dark w-100 fw-semibold">
                                        <i class="fe-file-text me-1"></i> Generate Register PDF
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
