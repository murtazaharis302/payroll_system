                <!-- 4. Single-Period Deduction History -->
                <div class="col-12 grid-margin stretch-card mb-3 report-card-item" data-category="history" data-keywords="single period deduction history itemized sss philhealth pagibig loan pdf excel csv">
                    <div class="card border-0 shadow-sm border-start border-4 border-danger">
                        <div class="card-header bg-white py-3 border-0 d-flex align-items-center justify-content-between cursor-pointer" data-bs-toggle="collapse" data-bs-target="#collapse-sp-deduction">
                            <div class="d-flex align-items-center me-2">
                                <div class="avatar-sm rounded bg-soft-danger text-danger d-flex align-items-center justify-content-center me-3 flex-shrink-0" style="width:40px; height:40px;">
                                    <i class="fe-minus-circle font-18"></i>
                                </div>
                                <div>
                                    <h5 class="fw-bold text-dark font-15 mb-1">
                                        Single-Period Deduction History
                                        <span class="badge bg-soft-danger text-danger font-11 ms-2">Single Period Itemized</span>
                                    </h5>
                                    <p class="text-muted font-12 mb-0">Itemized deduction category breakdown for a single specific payroll period (PDF, Excel, CSV)</p>
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm btn-light border text-nowrap font-12" data-bs-toggle="collapse" data-bs-target="#collapse-sp-deduction">
                                <i class="fe-chevron-down me-1"></i> Expand Filters
                            </button>
                        </div>
                        <div id="collapse-sp-deduction" class="collapse">
                            <div class="card-body border-top bg-light-subtle pt-3">
                                <form action="{{ panelRoute('reports.deduction-history') }}" method="POST" target="_blank">
                                    @csrf
                                    @if(isset($selectedCompanyId)) <input type="hidden" name="company_id" value="{{ $selectedCompanyId }}"> @endif

                                    <div class="row g-2 mb-2">
                                        <div class="col-md-4">
                                            <label class="form-label font-12 fw-semibold">Payroll Period <span class="text-danger">*</span></label>
                                            <x-payroll-period-select name="payroll_period_id" required />
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label font-12 fw-semibold">Department</label>
                                            <select name="department_id" class="form-select form-select-sm">
                                                <option value="all">All Departments</option>
                                                @php $targetDepts = isset($departments) ? $departments : \App\Models\Department::where('is_active', true)->when(isset($selectedCompanyId) && $selectedCompanyId, fn($q) => $q->where('company_id', $selectedCompanyId))->orderBy('name')->get(); @endphp
                                                @foreach($targetDepts as $dept)
                                                <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label font-12 fw-semibold">Export Format</label>
                                            <select name="export_format" class="form-select form-select-sm">
                                                <option value="pdf" selected>PDF Document (Landscape Legal)</option>
                                                <option value="xlsx">Excel Sheet (.xlsx)</option>
                                                <option value="csv">CSV File (.csv)</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="row g-2 mb-3">
                                        <div class="col-md-4">
                                            <div class="form-check mt-2">
                                                <input class="form-check-input" type="checkbox" name="dont_include_zero" value="1" id="dontIncZeroSingleDed" checked>
                                                <label class="form-check-label font-12 text-dark fw-semibold" for="dontIncZeroSingleDed">Don't include 0 amount</label>
                                            </div>
                                            <small class="text-muted font-11">Hide employees with zero total deductions in period.</small>
                                        </div>
                                        <div class="col-md-8">
                                            <div class="d-flex align-items-center justify-content-between mb-1">
                                                <label class="form-label font-12 fw-semibold mb-0">Select Deduction Types to Include</label>
                                                <div class="font-11">
                                                    <a href="javascript:void(0)" onclick="toggleAllCheckboxes('sp_ded_box', true)" class="text-danger fw-semibold me-2">Select All</a>
                                                    <a href="javascript:void(0)" onclick="toggleAllCheckboxes('sp_ded_box', false)" class="text-muted">Clear</a>
                                                </div>
                                            </div>
                                            <div class="border rounded p-2 bg-light" id="sp_ded_box" style="max-height: 85px; overflow-y: auto;">
                                                <div class="row g-1">
                                                    <div class="col-md-3 col-6">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" name="selected_deduction_types[]" value="sss_contribution" id="sp_ded_sss" checked>
                                                            <label class="form-check-label font-11" for="sp_ded_sss">SSS</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-3 col-6">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" name="selected_deduction_types[]" value="philhealth_contribution" id="sp_ded_ph" checked>
                                                            <label class="form-check-label font-11" for="sp_ded_ph">PhilHealth</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-3 col-6">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" name="selected_deduction_types[]" value="pagibig_contribution" id="sp_ded_pagibig" checked>
                                                            <label class="form-check-label font-11" for="sp_ded_pagibig">Pag-IBIG</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-3 col-6">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" name="selected_deduction_types[]" value="withholding_tax" id="sp_ded_tax" checked>
                                                            <label class="form-check-label font-11" for="sp_ded_tax">W/Tax</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-3 col-6">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" name="selected_deduction_types[]" value="sss_loan" id="sp_ded_sss_loan" checked>
                                                            <label class="form-check-label font-11" for="sp_ded_sss_loan">SSS Loan</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-3 col-6">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" name="selected_deduction_types[]" value="pagibig_loan" id="sp_ded_pagibig_loan" checked>
                                                            <label class="form-check-label font-11" for="sp_ded_pagibig_loan">Pag-IBIG Loan</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-3 col-6">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" name="selected_deduction_types[]" value="vale_deduction" id="sp_ded_vale" checked>
                                                            <label class="form-check-label font-11" for="sp_ded_vale">Vale</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-3 col-6">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" name="selected_deduction_types[]" value="goods_deduction" id="sp_ded_goods" checked>
                                                            <label class="form-check-label font-11" for="sp_ded_goods">Goods</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-3 col-6">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" name="selected_deduction_types[]" value="absences_deduction" id="sp_ded_absences" checked>
                                                            <label class="form-check-label font-11" for="sp_ded_absences">Absences</label>
                                                        </div>
                                                    </div>
                                                    @if(isset($deductionTypes) && count($deductionTypes) > 0)
                                                        @foreach($deductionTypes as $dedType)
                                                            @if(!in_array(strtolower(trim($dedType->name)), $stdDeductionNames, true) && !in_array(preg_replace('/\s*\d+$/', '', strtolower(trim($dedType->name))), $stdDeductionNames, true) && (empty($dedType->system_key) || !in_array($dedType->system_key, ['sss', 'philhealth', 'pagibig', 'tax', 'sss_loan', 'pagibig_loan', 'vale', 'goods', 'absences'], true)))
                                                            <div class="col-md-3 col-6">
                                                                <div class="form-check">
                                                                    <input class="form-check-input" type="checkbox" name="selected_deduction_types[]" value="{{ $dedType->id }}" id="sp_ded_custom_{{ $dedType->id }}">
                                                                    <label class="form-check-label font-11" for="sp_ded_custom_{{ $dedType->id }}">{{ $dedType->name }}</label>
                                                                </div>
                                                            </div>
                                                            @endif
                                                        @endforeach
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <button type="submit" class="btn btn-sm btn-danger w-100 fw-semibold">
                                        <i class="fe-download me-1"></i> Generate Single-Period Deduction History
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
