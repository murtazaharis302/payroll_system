        <!-- ==================== TAB 2: STATUTORY & TAX ==================== -->
        <div class="tab-pane fade" id="pane-statutory" role="tabpanel" aria-labelledby="tab-statutory">
            <div class="row">

                <!-- 1. SSS Contribution Report -->
                <div class="col-12 grid-margin stretch-card mb-3 report-card-item" data-category="statutory" data-keywords="sss contribution report statutory excel">
                    <div class="card border-0 shadow-sm border-start border-4 border-info h-100">
                        <div class="card-header bg-white py-3 border-0 d-flex align-items-center justify-content-between cursor-pointer" data-bs-toggle="collapse" data-bs-target="#collapse-sss">
                            <div class="d-flex align-items-center me-2">
                                <div class="avatar-sm rounded bg-soft-info text-info d-flex align-items-center justify-content-center me-3 flex-shrink-0" style="width:40px; height:40px;">
                                    <i class="fe-file-minus font-18"></i>
                                </div>
                                <div>
                                    <h5 class="fw-bold text-dark font-15 mb-1">
                                        SSS Contribution Report
                                        <span class="badge bg-soft-info text-info font-11 ms-2">Statutory</span>
                                    </h5>
                                    <p class="text-muted font-12 mb-0">Generate monthly SSS contribution report (Excel export)</p>
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm btn-light border text-nowrap font-12" data-bs-toggle="collapse" data-bs-target="#collapse-sss">
                                <i class="fe-chevron-down me-1"></i> Expand Filters
                            </button>
                        </div>
                        <div id="collapse-sss" class="collapse">
                            <div class="card-body border-top bg-light-subtle pt-3">
                                <form action="{{ panelRoute('reports.sss') }}" method="POST">
                                    @csrf
                                    @if(isset($selectedCompanyId)) <input type="hidden" name="company_id" value="{{ $selectedCompanyId }}"> @endif
                                    <div class="row g-2 mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label font-12 fw-semibold">From Month <span class="text-danger">*</span></label>
                                            <select name="from_month" class="form-select form-select-sm" required {{ (isset($reportMonths) && count($reportMonths) > 0) ? '' : 'disabled' }}>
                                                @if(isset($reportMonths) && count($reportMonths) > 0)
                                                    @foreach($reportMonths as $rm)
                                                        <option value="{{ $rm['value'] }}">{{ $rm['label'] }}</option>
                                                    @endforeach
                                                @else
                                                    <option value="" disabled selected>No payroll periods available</option>
                                                @endif
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label font-12 fw-semibold">To Month <span class="text-danger">*</span></label>
                                            <select name="to_month" class="form-select form-select-sm" required {{ (isset($reportMonths) && count($reportMonths) > 0) ? '' : 'disabled' }}>
                                                @if(isset($reportMonths) && count($reportMonths) > 0)
                                                    @foreach($reportMonths as $rm)
                                                        <option value="{{ $rm['value'] }}">{{ $rm['label'] }}</option>
                                                    @endforeach
                                                @else
                                                    <option value="" disabled selected>No payroll periods available</option>
                                                @endif
                                            </select>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-sm btn-info text-white w-100 fw-semibold" {{ (isset($reportMonths) && count($reportMonths) > 0) ? '' : 'disabled' }}>
                                        <i class="fe-download me-1"></i> Export SSS Excel
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. PhilHealth Contribution Report -->
                <div class="col-12 grid-margin stretch-card mb-3 report-card-item" data-category="statutory" data-keywords="philhealth contribution statutory excel">
                    <div class="card border-0 shadow-sm border-start border-4 border-success h-100">
                        <div class="card-header bg-white py-3 border-0 d-flex align-items-center justify-content-between cursor-pointer" data-bs-toggle="collapse" data-bs-target="#collapse-philhealth">
                            <div class="d-flex align-items-center me-2">
                                <div class="avatar-sm rounded bg-soft-success text-success d-flex align-items-center justify-content-center me-3 flex-shrink-0" style="width:40px; height:40px;">
                                    <i class="fe-heart font-18"></i>
                                </div>
                                <div>
                                    <h5 class="fw-bold text-dark font-15 mb-1">
                                        PhilHealth Contribution Report
                                        <span class="badge bg-soft-success text-success font-11 ms-2">Statutory</span>
                                    </h5>
                                    <p class="text-muted font-12 mb-0">Generate PhilHealth contribution report for selected month range (Excel export)</p>
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm btn-light border text-nowrap font-12" data-bs-toggle="collapse" data-bs-target="#collapse-philhealth">
                                <i class="fe-chevron-down me-1"></i> Expand Filters
                            </button>
                        </div>
                        <div id="collapse-philhealth" class="collapse">
                            <div class="card-body border-top bg-light-subtle pt-3">
                                <form action="{{ panelRoute('reports.philhealth') }}" method="POST">
                                    @csrf
                                    @if(isset($selectedCompanyId)) <input type="hidden" name="company_id" value="{{ $selectedCompanyId }}"> @endif
                                    <div class="row g-2 mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label font-12 fw-semibold">From Month <span class="text-danger">*</span></label>
                                            <select name="from_month" class="form-select form-select-sm" required {{ (isset($reportMonths) && count($reportMonths) > 0) ? '' : 'disabled' }}>
                                                @if(isset($reportMonths) && count($reportMonths) > 0)
                                                    @foreach($reportMonths as $rm)
                                                        <option value="{{ $rm['value'] }}">{{ $rm['label'] }}</option>
                                                    @endforeach
                                                @else
                                                    <option value="" disabled selected>No payroll periods available</option>
                                                @endif
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label font-12 fw-semibold">To Month <span class="text-danger">*</span></label>
                                            <select name="to_month" class="form-select form-select-sm" required {{ (isset($reportMonths) && count($reportMonths) > 0) ? '' : 'disabled' }}>
                                                @if(isset($reportMonths) && count($reportMonths) > 0)
                                                    @foreach($reportMonths as $rm)
                                                        <option value="{{ $rm['value'] }}">{{ $rm['label'] }}</option>
                                                    @endforeach
                                                @else
                                                    <option value="" disabled selected>No payroll periods available</option>
                                                @endif
                                            </select>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-sm btn-success w-100 fw-semibold" {{ (isset($reportMonths) && count($reportMonths) > 0) ? '' : 'disabled' }}>
                                        <i class="fe-download me-1"></i> Export PhilHealth Excel
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 3. Pag-IBIG Contribution Report -->
                <div class="col-12 grid-margin stretch-card mb-3 report-card-item" data-category="statutory" data-keywords="pagibig hdmf contribution statutory excel">
                    <div class="card border-0 shadow-sm border-start border-4 border-warning h-100">
                        <div class="card-header bg-white py-3 border-0 d-flex align-items-center justify-content-between cursor-pointer" data-bs-toggle="collapse" data-bs-target="#collapse-pagibig">
                            <div class="d-flex align-items-center me-2">
                                <div class="avatar-sm rounded bg-soft-warning text-warning d-flex align-items-center justify-content-center me-3 flex-shrink-0" style="width:40px; height:40px;">
                                    <i class="fe-home font-18"></i>
                                </div>
                                <div>
                                    <h5 class="fw-bold text-dark font-15 mb-1">
                                        Pag-IBIG Contribution Report
                                        <span class="badge bg-soft-warning text-warning font-11 ms-2">Statutory</span>
                                    </h5>
                                    <p class="text-muted font-12 mb-0">Generate Pag-IBIG contribution report for selected month range (Excel export)</p>
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm btn-light border text-nowrap font-12" data-bs-toggle="collapse" data-bs-target="#collapse-pagibig">
                                <i class="fe-chevron-down me-1"></i> Expand Filters
                            </button>
                        </div>
                        <div id="collapse-pagibig" class="collapse">
                            <div class="card-body border-top bg-light-subtle pt-3">
                                <form action="{{ panelRoute('reports.pagibig') }}" method="POST">
                                    @csrf
                                    @if(isset($selectedCompanyId)) <input type="hidden" name="company_id" value="{{ $selectedCompanyId }}"> @endif
                                    <div class="row g-2 mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label font-12 fw-semibold">From Month <span class="text-danger">*</span></label>
                                            <select name="from_month" class="form-select form-select-sm" required {{ (isset($reportMonths) && count($reportMonths) > 0) ? '' : 'disabled' }}>
                                                @if(isset($reportMonths) && count($reportMonths) > 0)
                                                    @foreach($reportMonths as $rm)
                                                        <option value="{{ $rm['value'] }}">{{ $rm['label'] }}</option>
                                                    @endforeach
                                                @else
                                                    <option value="" disabled selected>No payroll periods available</option>
                                                @endif
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label font-12 fw-semibold">To Month <span class="text-danger">*</span></label>
                                            <select name="to_month" class="form-select form-select-sm" required {{ (isset($reportMonths) && count($reportMonths) > 0) ? '' : 'disabled' }}>
                                                @if(isset($reportMonths) && count($reportMonths) > 0)
                                                    @foreach($reportMonths as $rm)
                                                        <option value="{{ $rm['value'] }}">{{ $rm['label'] }}</option>
                                                    @endforeach
                                                @else
                                                    <option value="" disabled selected>No payroll periods available</option>
                                                @endif
                                            </select>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-sm btn-warning text-dark w-100 fw-semibold" {{ (isset($reportMonths) && count($reportMonths) > 0) ? '' : 'disabled' }}>
                                        <i class="fe-download me-1"></i> Export Pag-IBIG Excel
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 4. Withholding Tax Report -->
                <div class="col-12 grid-margin stretch-card mb-3 report-card-item" data-category="statutory" data-keywords="withholding tax bir tax contribution excel">
                    <div class="card border-0 shadow-sm border-start border-4 border-danger h-100">
                        <div class="card-header bg-white py-3 border-0 d-flex align-items-center justify-content-between cursor-pointer" data-bs-toggle="collapse" data-bs-target="#collapse-wtax">
                            <div class="d-flex align-items-center me-2">
                                <div class="avatar-sm rounded bg-soft-danger text-danger d-flex align-items-center justify-content-center me-3 flex-shrink-0" style="width:40px; height:40px;">
                                    <i class="fe-dollar-sign font-18"></i>
                                </div>
                                <div>
                                    <h5 class="fw-bold text-dark font-15 mb-1">
                                        Withholding Tax Report
                                        <span class="badge bg-soft-danger text-danger font-11 ms-2">Tax</span>
                                    </h5>
                                    <p class="text-muted font-12 mb-0">Generate withholding tax report for BIR for selected month range (Excel export)</p>
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm btn-light border text-nowrap font-12" data-bs-toggle="collapse" data-bs-target="#collapse-wtax">
                                <i class="fe-chevron-down me-1"></i> Expand Filters
                            </button>
                        </div>
                        <div id="collapse-wtax" class="collapse">
                            <div class="card-body border-top bg-light-subtle pt-3">
                                <form action="{{ panelRoute('reports.withholding-tax') }}" method="POST">
                                    @csrf
                                    @if(isset($selectedCompanyId)) <input type="hidden" name="company_id" value="{{ $selectedCompanyId }}"> @endif
                                    <div class="row g-2 mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label font-12 fw-semibold">From Month <span class="text-danger">*</span></label>
                                            <select name="from_month" class="form-select form-select-sm" required {{ (isset($reportMonths) && count($reportMonths) > 0) ? '' : 'disabled' }}>
                                                @if(isset($reportMonths) && count($reportMonths) > 0)
                                                    @foreach($reportMonths as $rm)
                                                        <option value="{{ $rm['value'] }}">{{ $rm['label'] }}</option>
                                                    @endforeach
                                                @else
                                                    <option value="" disabled selected>No payroll periods available</option>
                                                @endif
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label font-12 fw-semibold">To Month <span class="text-danger">*</span></label>
                                            <select name="to_month" class="form-select form-select-sm" required {{ (isset($reportMonths) && count($reportMonths) > 0) ? '' : 'disabled' }}>
                                                @if(isset($reportMonths) && count($reportMonths) > 0)
                                                    @foreach($reportMonths as $rm)
                                                        <option value="{{ $rm['value'] }}">{{ $rm['label'] }}</option>
                                                    @endforeach
                                                @else
                                                    <option value="" disabled selected>No payroll periods available</option>
                                                @endif
                                            </select>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-sm btn-danger w-100 fw-semibold" {{ (isset($reportMonths) && count($reportMonths) > 0) ? '' : 'disabled' }}>
                                        <i class="fe-download me-1"></i> Export Tax Excel
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 5. Tax Alpha List Report -->
                <div class="col-12 grid-margin stretch-card mb-3 report-card-item" data-category="statutory" data-keywords="alpha list alphabetical employee list pdf">
                    <div class="card border-0 shadow-sm border-start border-4 border-success h-100">
                        <div class="card-header bg-white py-3 border-0 d-flex align-items-center justify-content-between cursor-pointer" data-bs-toggle="collapse" data-bs-target="#collapse-alphalist">
                            <div class="d-flex align-items-center me-2">
                                <div class="avatar-sm rounded bg-soft-success text-success d-flex align-items-center justify-content-center me-3 flex-shrink-0" style="width:40px; height:40px;">
                                    <i class="fe-list font-18"></i>
                                </div>
                                <div>
                                    <h5 class="fw-bold text-dark font-15 mb-1">
                                        Tax Alpha List Report
                                        <span class="badge bg-soft-success text-success font-11 ms-2">Annual Tax</span>
                                    </h5>
                                    <p class="text-muted font-12 mb-0">Generate alphabetical employee list with compensation & withholding tax (PDF)</p>
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm btn-light border text-nowrap font-12" data-bs-toggle="collapse" data-bs-target="#collapse-alphalist">
                                <i class="fe-chevron-down me-1"></i> Expand Filters
                            </button>
                        </div>
                        <div id="collapse-alphalist" class="collapse">
                            <div class="card-body border-top bg-light-subtle pt-3">
                                <form action="{{ panelRoute('reports.alpha-list') }}" method="POST" target="_blank">
                                    @csrf
                                    @if(isset($selectedCompanyId)) <input type="hidden" name="company_id" value="{{ $selectedCompanyId }}"> @endif
                                    <div class="form-group mb-3">
                                        <label class="form-label font-12 fw-semibold">Payroll Period <span class="text-danger">*</span></label>
                                        <x-payroll-period-select name="payroll_period_id" required />
                                    </div>
                                    <button type="submit" class="btn btn-sm btn-success w-100 fw-semibold">
                                        <i class="fe-file-text me-1"></i> Generate Alpha List PDF
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 6. 13th Month Pay Computation -->
                <div class="col-12 grid-margin stretch-card mb-3 report-card-item" data-category="statutory" data-keywords="13th month pay computation bonus pd 851 statutory basic salary pdf excel csv">
                    <div class="card border-0 shadow-sm border-start border-4 border-warning h-100">
                        <div class="card-header bg-white py-3 border-0 d-flex align-items-center justify-content-between cursor-pointer" data-bs-toggle="collapse" data-bs-target="#collapse-13th">
                            <div class="d-flex align-items-center me-2">
                                <div class="avatar-sm rounded bg-soft-warning text-warning d-flex align-items-center justify-content-center me-3 flex-shrink-0" style="width:40px; height:40px;">
                                    <i class="fe-gift font-18"></i>
                                </div>
                                <div>
                                    <h5 class="fw-bold text-dark font-15 mb-1">
                                        13th Month Pay Computation
                                        <span class="badge bg-soft-warning text-warning font-11 ms-2">Statutory Bonus</span>
                                    </h5>
                                    <p class="text-muted font-12 mb-0">Compute annual 13th month statutory bonus (Total Basic ÷ 12)</p>
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm btn-light border text-nowrap font-12" data-bs-toggle="collapse" data-bs-target="#collapse-13th">
                                <i class="fe-chevron-down me-1"></i> Expand Filters
                            </button>
                        </div>
                        <div id="collapse-13th" class="collapse">
                            <div class="card-body border-top bg-light-subtle pt-3">
                                <form action="{{ panelRoute('reports.13th-month-computation') }}" method="POST" target="_blank">
                                    @csrf
                                    @if(isset($selectedCompanyId)) <input type="hidden" name="company_id" value="{{ $selectedCompanyId }}"> @endif
                                    <div class="row g-2 mb-2">
                                        <div class="col-md-6">
                                            <label class="form-label font-12 fw-semibold">From Period <span class="text-danger">*</span></label>
                                            <x-payroll-period-select name="from_period_id" required />
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label font-12 fw-semibold">To Period <span class="text-danger">*</span></label>
                                            <x-payroll-period-select name="to_period_id" required />
                                        </div>
                                    </div>
                                    <div class="row g-2 mb-3">
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
                                                <option value="pdf" selected>PDF Document (Landscape Legal)</option>
                                                <option value="xlsx">Excel Sheet (.xlsx)</option>
                                                <option value="csv">CSV File (.csv)</option>
                                            </select>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-sm btn-warning text-dark w-100 fw-semibold">
                                        <i class="fe-download me-1"></i> Generate 13th Month Pay Computation
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
