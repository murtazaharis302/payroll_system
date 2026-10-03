        <!-- ==================== TAB 3: BANKING & TRANSMITTALS ==================== -->
        <div class="tab-pane fade" id="pane-banking" role="tabpanel" aria-labelledby="tab-banking">
            <div class="row">

                <!-- 1. Bank Transmittal Letter & Upload Export -->
                <div class="col-12 grid-margin stretch-card mb-3 report-card-item" data-category="banking" data-keywords="bank transmittal letter advice bank upload csv bdo bpi metrobank pdf export">
                    <div class="card border-0 shadow-sm border-start border-4 border-info">
                        <div class="card-header bg-white py-3 border-0 d-flex align-items-center justify-content-between cursor-pointer" data-bs-toggle="collapse" data-bs-target="#collapse-bank-advice">
                            <div class="d-flex align-items-center me-2">
                                <div class="avatar-sm rounded bg-soft-info text-info d-flex align-items-center justify-content-center me-3 flex-shrink-0" style="width:40px; height:40px;">
                                    <i class="fe-briefcase font-18"></i>
                                </div>
                                <div>
                                    <h5 class="fw-bold text-dark font-15 mb-1">
                                        Bank Advice & Direct Deposit Transmittal
                                        <span class="badge bg-soft-info text-info font-11 ms-2">Bank Transmittal</span>
                                    </h5>
                                    <p class="text-muted font-12 mb-0">Generate formal authorization letter to bank manager requesting bulk net pay transfer or export transmittal sheet (PDF, Excel, CSV)</p>
                                </div>
                            </div>
                            <div class="d-flex align-items-center">
                                <button type="button" class="btn btn-sm btn-soft-info me-2 fw-semibold" data-bs-toggle="modal" data-bs-target="#bankSettingsModal">
                                    <i class="fe-settings me-1"></i> Routing Settings
                                </button>
                                <button type="button" class="btn btn-sm btn-light border text-nowrap font-12" data-bs-toggle="collapse" data-bs-target="#collapse-bank-advice">
                                    <i class="fe-chevron-down me-1"></i> Expand Filters
                                </button>
                            </div>
                        </div>
                        <div id="collapse-bank-advice" class="collapse">
                            <div class="card-body border-top bg-light-subtle pt-3">
                                <form id="bankReportForm" action="{{ panelRoute('reports.bank-report') }}" method="POST" target="_blank">
                                    @csrf
                                    @if(isset($selectedCompanyId)) <input type="hidden" name="company_id" value="{{ $selectedCompanyId }}"> @endif

                                    <div class="row g-2 mb-2">
                                        <div class="col-md-3">
                                            <label class="form-label font-12 fw-semibold">From Period <span class="text-danger">*</span></label>
                                            <x-payroll-period-select name="from_period_id" id="bank_from_period_id" required />
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label font-12 fw-semibold">To Period <span class="text-danger">*</span></label>
                                            <x-payroll-period-select name="to_period_id" id="bank_to_period_id" required />
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label font-12 fw-semibold">Department</label>
                                            <select name="department_id" class="form-select form-select-sm">
                                                <option value="all">All Departments</option>
                                                @php $targetDepts = isset($departments) ? $departments : \App\Models\Department::where('is_active', true)->when(isset($selectedCompanyId) && $selectedCompanyId, fn($q) => $q->where('company_id', $selectedCompanyId))->orderBy('name')->get(); @endphp
                                                @foreach($targetDepts as $dept)
                                                <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label font-12 fw-semibold">Export Format</label>
                                            <select name="export_format" id="bank_export_format" class="form-select form-select-sm">
                                                <option value="pdf" selected>PDF Transmittal Letter</option>
                                                <option value="xlsx">Excel Sheet (.xlsx)</option>
                                                <option value="csv">CSV Direct Deposit Stream (.csv)</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="row g-2 mb-3">
                                        <div class="col-md-4">
                                            <label class="form-label font-12 fw-semibold">Bank Name <span class="text-danger">*</span></label>
                                            <input type="text" name="bank_name" id="bank_input_name" class="form-control form-control-sm" value="{{ $defaultBank->bank_name ?? 'BDO Unibank, Inc.' }}" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label font-12 fw-semibold">Company Account <span class="text-danger">*</span></label>
                                            <input type="text" name="company_acct" id="bank_input_acct" class="form-control form-control-sm" value="{{ $defaultBank->account_number ?? '' }}" placeholder="Enter Account Number" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label font-12 fw-semibold">Authorized Signatory <span class="text-danger">*</span></label>
                                            <input type="text" name="signatory_name" id="bank_input_signatory" class="form-control form-control-sm" value="{{ $defaultBank->corporate_signee_1 ?? Auth::user()?->name }}" required>
                                        </div>
                                    </div>

                                    <button type="submit" id="btnGenerateBankAdvice" class="btn btn-sm btn-info text-white w-100 fw-semibold">
                                        <i class="fe-download me-1"></i> Generate Bank Advice Transmittal
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
