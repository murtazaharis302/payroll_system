                <!-- 5. Deduction Ledger Report -->
                <div class="col-12 grid-margin stretch-card mb-3 report-card-item" data-category="history" data-keywords="deduction ledger vertical period history item single statutory loan savings pdf excel csv">
                    <div class="card border-0 shadow-sm border-start border-4 border-secondary">
                        <div class="card-header bg-white py-3 border-0 d-flex align-items-center justify-content-between cursor-pointer" data-bs-toggle="collapse" data-bs-target="#collapse-ded-ledger">
                            <div class="d-flex align-items-center me-2">
                                <div class="avatar-sm rounded bg-soft-secondary text-secondary d-flex align-items-center justify-content-center me-3 flex-shrink-0" style="width:40px; height:40px;">
                                    <i class="fe-file-text font-18"></i>
                                </div>
                                <div>
                                    <h5 class="fw-bold text-dark font-15 mb-1">
                                        Deduction Ledger Report
                                        <span class="badge bg-soft-secondary text-secondary font-11 ms-2">Vertical Period History</span>
                                    </h5>
                                    <p class="text-muted font-12 mb-0">Vertical line-item deduction ledger for a single selected deduction item across bounded periods (PDF, Excel, CSV)</p>
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm btn-light border text-nowrap font-12" data-bs-toggle="collapse" data-bs-target="#collapse-ded-ledger">
                                <i class="fe-chevron-down me-1"></i> Expand Filters
                            </button>
                        </div>
                        <div id="collapse-ded-ledger" class="collapse">
                            <div class="card-body border-top bg-light-subtle pt-3">
                                <form action="{{ panelRoute('reports.deduction-ledger') }}" method="POST" target="_blank">
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
                                        <div class="col-md-4">
                                            <label class="form-label font-12 fw-semibold">Employee</label>
                                            <x-employee-select name="employee_id" placeholder="All Employees" :companyId="$selectedCompanyId ?? null" :employees="$employees ?? null" />
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
                                            <label class="form-label font-12 fw-semibold">Deduction Type <span class="text-danger">*</span></label>
                                            <select name="deduction_type_id" class="form-select form-select-sm" required>
                                                <optgroup label="Statutory & Standard Deductions">
                                                    <option value="sss_contribution">SSS Contribution</option>
                                                    <option value="philhealth_contribution">PhilHealth Contribution</option>
                                                    <option value="pagibig_contribution">Pag-IBIG Contribution</option>
                                                    <option value="withholding_tax">Withholding Tax</option>
                                                    <option value="sss_loan" selected>SSS Loan</option>
                                                    <option value="pagibig_loan">Pag-IBIG Loan</option>
                                                    <option value="vale_deduction">Vale (Cash Advance)</option>
                                                    <option value="goods_deduction">Goods Purchase</option>
                                                    <option value="absences_deduction">Absences Ded.</option>
                                                </optgroup>
                                                @if(isset($deductionTypes) && count($deductionTypes) > 0)
                                                    <optgroup label="Custom & Other Deductions">
                                                        @foreach($deductionTypes as $dedType)
                                                            @if(!in_array(strtolower(trim($dedType->name)), $stdDeductionNames, true) && !in_array(preg_replace('/\s*\d+$/', '', strtolower(trim($dedType->name))), $stdDeductionNames, true) && (empty($dedType->system_key) || !in_array($dedType->system_key, ['sss', 'philhealth', 'pagibig', 'tax', 'sss_loan', 'pagibig_loan', 'vale', 'goods', 'absences'], true)))
                                                            <option value="{{ $dedType->id }}">{{ $dedType->name }}</option>
                                                            @endif
                                                        @endforeach
                                                    </optgroup>
                                                @endif
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label font-12 fw-semibold">Export Format</label>
                                            <select name="export_format" class="form-select form-select-sm">
                                                <option value="pdf" selected>PDF Document (Landscape Legal)</option>
                                                <option value="xlsx">Excel Sheet (.xlsx)</option>
                                                <option value="csv">CSV File (.csv)</option>
                                            </select>
                                        </div>
                                    </div>

                                    <button type="submit" class="btn btn-sm btn-secondary w-100 fw-semibold">
                                        <i class="fe-download me-1"></i> Generate Deduction Ledger
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 6. Single-Deduction Multi-Period Matrix -->
                <div class="col-12 grid-margin stretch-card mb-3 report-card-item" data-category="history" data-keywords="single deduction multi period matrix cross tabulation sss loan savings bank deposit pdf excel csv">
                    <div class="card border-0 shadow-sm border-start border-4 border-purple">
                        <div class="card-header bg-white py-3 border-0 d-flex align-items-center justify-content-between cursor-pointer" data-bs-toggle="collapse" data-bs-target="#collapse-single-ded-matrix">
                            <div class="d-flex align-items-center me-2">
                                <div class="avatar-sm rounded bg-soft-purple text-purple d-flex align-items-center justify-content-center me-3 flex-shrink-0" style="width:40px; height:40px; background-color:#f4ecf7; color:#8e44ad;">
                                    <i class="fe-grid font-18"></i>
                                </div>
                                <div>
                                    <h5 class="fw-bold text-dark font-15 mb-1">
                                        Single-Deduction Multi-Period Matrix
                                        <span class="badge bg-soft-purple text-purple font-11 ms-2" style="background-color:#f4ecf7; color:#8e44ad;">Item Matrix Cross-Tabulation</span>
                                    </h5>
                                    <p class="text-muted font-12 mb-0">Track a single selected deduction type over a range of payroll periods with employees in rows and periods as horizontal columns</p>
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm btn-light border text-nowrap font-12" data-bs-toggle="collapse" data-bs-target="#collapse-single-ded-matrix">
                                <i class="fe-chevron-down me-1"></i> Expand Filters
                            </button>
                        </div>
                        <div id="collapse-single-ded-matrix" class="collapse">
                            <div class="card-body border-top bg-light-subtle pt-3">
                                <form action="{{ panelRoute('reports.single-deduction-matrix') }}" method="POST" target="_blank">
                                    @csrf
                                    @if(isset($selectedCompanyId)) <input type="hidden" name="company_id" value="{{ $selectedCompanyId }}"> @endif

                                    <div class="row g-2 mb-3">
                                        <div class="col-md-3">
                                            <label class="form-label font-12 fw-semibold">From Period <span class="text-danger">*</span></label>
                                            <x-payroll-period-select name="from_period_id" required />
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label font-12 fw-semibold">To Period <span class="text-danger">*</span></label>
                                            <x-payroll-period-select name="to_period_id" required />
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label font-12 fw-semibold">Department</label>
                                            <select name="department_id" class="form-select form-select-sm">
                                                <option value="all">All Departments</option>
                                                @php $targetDepts = isset($departments) ? $departments : \App\Models\Department::where('is_active', true)->when(isset($selectedCompanyId) && $selectedCompanyId, fn($q) => $q->where('company_id', $selectedCompanyId))->orderBy('name')->get(); @endphp
                                                @foreach($targetDepts as $dept)
                                                <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label font-12 fw-semibold">Deduction Type <span class="text-danger">*</span></label>
                                            <select name="deduction_type_id" class="form-select form-select-sm" required>
                                                <optgroup label="Statutory & Standard Deductions">
                                                    <option value="sss_contribution">SSS Contribution</option>
                                                    <option value="philhealth_contribution">PhilHealth Contribution</option>
                                                    <option value="pagibig_contribution">Pag-IBIG Contribution</option>
                                                    <option value="withholding_tax">Withholding Tax</option>
                                                    <option value="sss_loan" selected>SSS Loan</option>
                                                    <option value="pagibig_loan">Pag-IBIG Loan</option>
                                                    <option value="vale_deduction">Vale (Cash Advance)</option>
                                                    <option value="goods_deduction">Goods Purchase</option>
                                                    <option value="absences_deduction">Absences Ded.</option>
                                                </optgroup>
                                                @if(isset($deductionTypes) && count($deductionTypes) > 0)
                                                    <optgroup label="Custom & Other Deductions">
                                                        @foreach($deductionTypes as $dedType)
                                                            @if(!in_array(strtolower(trim($dedType->name)), $stdDeductionNames, true) && !in_array(preg_replace('/\s*\d+$/', '', strtolower(trim($dedType->name))), $stdDeductionNames, true) && (empty($dedType->system_key) || !in_array($dedType->system_key, ['sss', 'philhealth', 'pagibig', 'tax', 'sss_loan', 'pagibig_loan', 'vale', 'goods', 'absences'], true)))
                                                            <option value="{{ $dedType->id }}">{{ $dedType->name }}</option>
                                                            @endif
                                                        @endforeach
                                                    </optgroup>
                                                @endif
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label font-12 fw-semibold">Export Format</label>
                                            <select name="export_format" class="form-select form-select-sm">
                                                <option value="pdf" selected>PDF Document (Landscape Legal)</option>
                                                <option value="xlsx">Excel Sheet (.xlsx)</option>
                                                <option value="csv">CSV File (.csv)</option>
                                            </select>
                                        </div>
                                    </div>

                                    <button type="submit" class="btn btn-sm text-white w-100 fw-semibold" style="background-color: #8e44ad;">
                                        <i class="fe-download me-1"></i> Generate Single-Deduction Matrix Report
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
