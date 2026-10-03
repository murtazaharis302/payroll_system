                <!-- 1. Income Payroll History (Multi-Period Matrix) -->
                <div class="col-12 grid-margin stretch-card mb-3 report-card-item" data-category="history" data-keywords="income payroll history multi-period matrix gross overtime basic pdf excel csv">
                    <div class="card border-0 shadow-sm border-start border-4 border-primary">
                        <div class="card-header bg-white py-3 border-0 d-flex align-items-center justify-content-between cursor-pointer" data-bs-toggle="collapse" data-bs-target="#collapse-inc-matrix">
                            <div class="d-flex align-items-center me-2">
                                <div class="avatar-sm rounded bg-soft-primary text-primary d-flex align-items-center justify-content-center me-3 flex-shrink-0" style="width:40px; height:40px;">
                                    <i class="fe-trending-up font-18"></i>
                                </div>
                                <div>
                                    <h5 class="fw-bold text-dark font-15 mb-1">
                                        Income Payroll History Matrix
                                        <span class="badge bg-soft-primary text-primary font-11 ms-2">Multi-Period Matrix</span>
                                    </h5>
                                    <p class="text-muted font-12 mb-0">Cross-period historical income matrix tracking employee earnings (PDF, Excel, CSV)</p>
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm btn-light border text-nowrap font-12" data-bs-toggle="collapse" data-bs-target="#collapse-inc-matrix">
                                <i class="fe-chevron-down me-1"></i> Expand Filters
                            </button>
                        </div>
                        <div id="collapse-inc-matrix" class="collapse">
                            <div class="card-body border-top bg-light-subtle pt-3">
                                <form action="{{ panelRoute('reports.income-payroll-history') }}" method="POST" target="_blank">
                                    @csrf
                                    @if(isset($selectedCompanyId)) <input type="hidden" name="company_id" value="{{ $selectedCompanyId }}"> @endif

                                    <div class="row g-2 mb-2">
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
                                            <label class="form-label font-12 fw-semibold">Display Mode</label>
                                            <select name="display_mode" class="form-select form-select-sm">
                                                <option value="rate_and_number" selected>Rate & Number</option>
                                                <option value="rate_only">Rate Only</option>
                                                <option value="number_only">Number Only</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label font-12 fw-semibold">Export Format</label>
                                            <select name="export_format" class="form-select form-select-sm">
                                                <option value="pdf" selected>PDF Document</option>
                                                <option value="xlsx">Excel Sheet (.xlsx)</option>
                                                <option value="csv">CSV File (.csv)</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="row g-2 mb-3">
                                        <div class="col-md-3">
                                            <div class="form-check mt-2">
                                                <input class="form-check-input" type="checkbox" name="include_zero_amount" value="1" id="incZeroAmountCheck" checked>
                                                <label class="form-check-label font-12 text-dark fw-semibold" for="incZeroAmountCheck">Include Zero Amount</label>
                                            </div>
                                            <small class="text-muted font-11">Uncheck to hide zero-income rows.</small>
                                        </div>
                                        <div class="col-md-9">
                                            <div class="d-flex align-items-center justify-content-between mb-1">
                                                <label class="form-label font-12 fw-semibold mb-0">Select Income Types to Include</label>
                                                <div class="font-11">
                                                    <a href="javascript:void(0)" onclick="toggleAllCheckboxes('matrix_inc_box', true)" class="text-primary fw-semibold me-2">Select All</a>
                                                    <a href="javascript:void(0)" onclick="toggleAllCheckboxes('matrix_inc_box', false)" class="text-muted">Clear</a>
                                                </div>
                                            </div>
                                            <div class="border rounded p-2 bg-light" id="matrix_inc_box" style="max-height: 85px; overflow-y: auto;">
                                                <div class="row g-1">
                                                    <div class="col-md-4 col-6">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" name="selected_income_types[]" value="basic_pay" id="inc_basic" checked>
                                                            <label class="form-check-label font-11" for="inc_basic">Basic Pay</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4 col-6">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" name="selected_income_types[]" value="overtime_pay" id="inc_ot" checked>
                                                            <label class="form-check-label font-11" for="inc_ot">Overtime</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4 col-6">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" name="selected_income_types[]" value="allowances" id="inc_allow" checked>
                                                            <label class="form-check-label font-11" for="inc_allow">Allowances</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4 col-6">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" name="selected_income_types[]" value="bonuses" id="inc_bonus" checked>
                                                            <label class="form-check-label font-11" for="inc_bonus">Bonuses</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4 col-6">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" name="selected_income_types[]" value="other_income" id="inc_other" checked>
                                                            <label class="form-check-label font-11" for="inc_other">Other Income</label>
                                                        </div>
                                                    </div>
                                                    @if(isset($incomeTypes) && count($incomeTypes) > 0)
                                                        @foreach($incomeTypes as $incType)
                                                            @if(!in_array(strtolower(trim($incType->name)), $stdIncomeNames, true) && !in_array(preg_replace('/\s*\d+$/', '', strtolower(trim($incType->name))), $stdIncomeNames, true) && (empty($incType->system_key) || !in_array($incType->system_key, ['basic', 'overtime', 'allowance', 'bonus', 'other'], true)))
                                                            <div class="col-md-4 col-6">
                                                                <div class="form-check">
                                                                    <input class="form-check-input" type="checkbox" name="selected_income_types[]" value="{{ $incType->id }}" id="inc_custom_{{ $incType->id }}">
                                                                    <label class="form-check-label font-11" for="inc_custom_{{ $incType->id }}">{{ $incType->name }}</label>
                                                                </div>
                                                            </div>
                                                            @endif
                                                        @endforeach
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <button type="submit" class="btn btn-sm btn-primary w-100 fw-semibold">
                                        <i class="fe-download me-1"></i> Generate Income History Report Matrix
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- 1b. Recurring Income Summary by Payroll Period (Issue #27) -->
                <div class="col-12 grid-margin stretch-card mb-3 report-card-item" data-category="history" data-keywords="recurring income summary payroll period sum grouped 13th month pdf excel csv">
                    <div class="card border-0 shadow-sm border-start border-4" style="border-color: #059669 !important;">
                        <div class="card-header bg-white py-3 border-0 d-flex align-items-center justify-content-between cursor-pointer" data-bs-toggle="collapse" data-bs-target="#collapse-rec-inc-summary">
                            <div class="d-flex align-items-center me-2">
                                <div class="avatar-sm rounded d-flex align-items-center justify-content-center me-3 flex-shrink-0" style="width:40px; height:40px; background-color: #d1fae5; color: #065f46;">
                                    <i class="fe-bar-chart-2 font-18"></i>
                                </div>
                                <div>
                                    <h5 class="fw-bold text-dark font-15 mb-1">
                                        Recurring Income Summary by Payroll Period
                                        <span class="badge font-11 ms-2" style="background-color:#d1fae5; color:#065f46;">Grouped &amp; Summed</span>
                                    </h5>
                                    <p class="text-muted font-12 mb-0">Sum of selected recurring incomes per employee, grouped by payroll period as columns (PDF, Excel, CSV)</p>
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm btn-light border text-nowrap font-12" data-bs-toggle="collapse" data-bs-target="#collapse-rec-inc-summary">
                                <i class="fe-chevron-down me-1"></i> Expand Filters
                            </button>
                        </div>
                        <div id="collapse-rec-inc-summary" class="collapse">
                            <div class="card-body border-top bg-light-subtle pt-3">
                                <form action="{{ panelRoute('reports.recurring-income-summary') }}" method="POST" target="_blank">
                                    @csrf
                                    @if(isset($selectedCompanyId)) <input type="hidden" name="company_id" value="{{ $selectedCompanyId }}"> @endif

                                    <div class="row g-2 mb-2">
                                        <div class="col-md-3">
                                            <label class="form-label font-12 fw-semibold">Payroll Period <span class="text-danger">*</span></label>
                                            <x-payroll-period-select name="payroll_period_id" required />
                                            <small class="text-muted font-11">Select any period — all periods will appear as columns.</small>
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
                                            <select name="export_format" class="form-select form-select-sm">
                                                <option value="pdf" selected>PDF Document</option>
                                                <option value="xlsx">Excel Sheet (.xlsx)</option>
                                                <option value="csv">CSV File (.csv)</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-check mt-4">
                                                <input class="form-check-input" type="checkbox" name="include_zero_amount" value="1" id="recIncZeroCheck" checked>
                                                <label class="form-check-label font-12 text-dark fw-semibold" for="recIncZeroCheck">Include Zero Amount</label>
                                            </div>
                                            <small class="text-muted font-11">Uncheck to hide zero-income rows.</small>
                                        </div>
                                    </div>

                                    <div class="row g-2 mb-3">
                                        <div class="col-12">
                                            <div class="d-flex align-items-center justify-content-between mb-1">
                                                <label class="form-label font-12 fw-semibold mb-0">Select Recurring Income Types to Sum</label>
                                                <div class="font-11">
                                                    <a href="javascript:void(0)" onclick="toggleAllCheckboxes('rec_inc_box', true)" class="fw-semibold me-2" style="color:#059669;">Select All</a>
                                                    <a href="javascript:void(0)" onclick="toggleAllCheckboxes('rec_inc_box', false)" class="text-muted">Clear</a>
                                                </div>
                                            </div>
                                            <div class="border rounded p-2 bg-light" id="rec_inc_box" style="max-height: 85px; overflow-y: auto;">
                                                <div class="row g-1">
                                                    <div class="col-md-2 col-6">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" name="selected_income_types[]" value="basic_pay" id="rec_inc_basic" checked>
                                                            <label class="form-check-label font-11" for="rec_inc_basic">Basic Pay</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-2 col-6">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" name="selected_income_types[]" value="overtime_pay" id="rec_inc_ot" checked>
                                                            <label class="form-check-label font-11" for="rec_inc_ot">Overtime</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-2 col-6">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" name="selected_income_types[]" value="allowances" id="rec_inc_allow" checked>
                                                            <label class="form-check-label font-11" for="rec_inc_allow">Allowances</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-2 col-6">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" name="selected_income_types[]" value="bonuses" id="rec_inc_bonus" checked>
                                                            <label class="form-check-label font-11" for="rec_inc_bonus">Bonuses</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-2 col-6">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" name="selected_income_types[]" value="other_income" id="rec_inc_other" checked>
                                                            <label class="form-check-label font-11" for="rec_inc_other">Other Income</label>
                                                        </div>
                                                    </div>
                                                    @if(isset($incomeTypes) && count($incomeTypes) > 0)
                                                        @foreach($incomeTypes as $incType)
                                                            @if(!in_array(strtolower(trim($incType->name)), $stdIncomeNames, true) && (empty($incType->system_key) || !in_array($incType->system_key, ['basic', 'overtime', 'allowance', 'bonus', 'other'], true)))
                                                            <div class="col-md-2 col-6">
                                                                <div class="form-check">
                                                                    <input class="form-check-input" type="checkbox" name="selected_income_types[]" value="{{ $incType->id }}" id="rec_inc_custom_{{ $incType->id }}" checked>
                                                                    <label class="form-check-label font-11" for="rec_inc_custom_{{ $incType->id }}">{{ $incType->name }}</label>
                                                                </div>
                                                            </div>
                                                            @endif
                                                        @endforeach
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <button type="submit" class="btn btn-sm w-100 fw-semibold" style="background-color:#059669; color:#fff;">
                                        <i class="fe-download me-1"></i> Generate Recurring Income Summary
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. Deduction Payroll History (Multi-Period Matrix) -->

                <div class="col-12 grid-margin stretch-card mb-3 report-card-item" data-category="history" data-keywords="deduction payroll history multi-period matrix sss philhealth pagibig tax pdf excel csv">
                    <div class="card border-0 shadow-sm border-start border-4 border-danger">
                        <div class="card-header bg-white py-3 border-0 d-flex align-items-center justify-content-between cursor-pointer" data-bs-toggle="collapse" data-bs-target="#collapse-ded-matrix">
                            <div class="d-flex align-items-center me-2">
                                <div class="avatar-sm rounded bg-soft-danger text-danger d-flex align-items-center justify-content-center me-3 flex-shrink-0" style="width:40px; height:40px;">
                                    <i class="fe-trending-down font-18"></i>
                                </div>
                                <div>
                                    <h5 class="fw-bold text-dark font-15 mb-1">
                                        Deduction Payroll History Matrix
                                        <span class="badge bg-soft-danger text-danger font-11 ms-2">Multi-Period Matrix</span>
                                    </h5>
                                    <p class="text-muted font-12 mb-0">Cross-period historical deduction matrix tracking employee statutory & loan deductions (PDF, Excel, CSV)</p>
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm btn-light border text-nowrap font-12" data-bs-toggle="collapse" data-bs-target="#collapse-ded-matrix">
                                <i class="fe-chevron-down me-1"></i> Expand Filters
                            </button>
                        </div>
                        <div id="collapse-ded-matrix" class="collapse">
                            <div class="card-body border-top bg-light-subtle pt-3">
                                <form action="{{ panelRoute('reports.deduction-payroll-history') }}" method="POST" target="_blank">
                                    @csrf
                                    @if(isset($selectedCompanyId)) <input type="hidden" name="company_id" value="{{ $selectedCompanyId }}"> @endif

                                    <div class="row g-2 mb-2">
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
                                            <label class="form-label font-12 fw-semibold">Display Mode</label>
                                            <select name="display_mode" class="form-select form-select-sm">
                                                <option value="rate_and_number" selected>Rate & Number</option>
                                                <option value="rate_only">Rate Only</option>
                                                <option value="number_only">Number Only</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label font-12 fw-semibold">Export Format</label>
                                            <select name="export_format" class="form-select form-select-sm">
                                                <option value="pdf" selected>PDF Document</option>
                                                <option value="xlsx">Excel Sheet (.xlsx)</option>
                                                <option value="csv">CSV File (.csv)</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="row g-2 mb-3">
                                        <div class="col-md-3">
                                            <div class="form-check mt-2">
                                                <input class="form-check-input" type="checkbox" name="include_zero_amount" value="1" id="incZeroAmountCheckDed" checked>
                                                <label class="form-check-label font-12 text-dark fw-semibold" for="incZeroAmountCheckDed">Include Zero Amount</label>
                                            </div>
                                            <small class="text-muted font-11">Uncheck to prune zero-deduction rows.</small>
                                        </div>
                                        <div class="col-md-9">
                                            <div class="d-flex align-items-center justify-content-between mb-1">
                                                <label class="form-label font-12 fw-semibold mb-0">Select Deduction Types to Include</label>
                                                <div class="font-11">
                                                    <a href="javascript:void(0)" onclick="toggleAllCheckboxes('matrix_ded_box', true)" class="text-danger fw-semibold me-2">Select All</a>
                                                    <a href="javascript:void(0)" onclick="toggleAllCheckboxes('matrix_ded_box', false)" class="text-muted">Clear</a>
                                                </div>
                                            </div>
                                            <div class="border rounded p-2 bg-light" id="matrix_ded_box" style="max-height: 85px; overflow-y: auto;">
                                                <div class="row g-1">
                                                    <div class="col-md-3 col-6">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" name="selected_deduction_types[]" value="sss_contribution" id="ded_sss" checked>
                                                            <label class="form-check-label font-11" for="ded_sss">SSS</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-3 col-6">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" name="selected_deduction_types[]" value="philhealth_contribution" id="ded_ph" checked>
                                                            <label class="form-check-label font-11" for="ded_ph">PhilHealth</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-3 col-6">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" name="selected_deduction_types[]" value="pagibig_contribution" id="ded_pagibig" checked>
                                                            <label class="form-check-label font-11" for="ded_pagibig">Pag-IBIG</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-3 col-6">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" name="selected_deduction_types[]" value="withholding_tax" id="ded_tax" checked>
                                                            <label class="form-check-label font-11" for="ded_tax">W/Tax</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-3 col-6">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" name="selected_deduction_types[]" value="sss_loan" id="ded_sss_loan" checked>
                                                            <label class="form-check-label font-11" for="ded_sss_loan">SSS Loan</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-3 col-6">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" name="selected_deduction_types[]" value="pagibig_loan" id="ded_pagibig_loan" checked>
                                                            <label class="form-check-label font-11" for="ded_pagibig_loan">Pag-IBIG Loan</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-3 col-6">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" name="selected_deduction_types[]" value="vale_deduction" id="ded_vale" checked>
                                                            <label class="form-check-label font-11" for="ded_vale">Vale</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-3 col-6">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" name="selected_deduction_types[]" value="goods_deduction" id="ded_goods" checked>
                                                            <label class="form-check-label font-11" for="ded_goods">Goods</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-3 col-6">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" name="selected_deduction_types[]" value="absences_deduction" id="ded_absences" checked>
                                                            <label class="form-check-label font-11" for="ded_absences">Absences</label>
                                                        </div>
                                                    </div>
                                                    @if(isset($deductionTypes) && count($deductionTypes) > 0)
                                                        @foreach($deductionTypes as $dedType)
                                                            @if(!in_array(strtolower(trim($dedType->name)), $stdDeductionNames, true) && !in_array(preg_replace('/\s*\d+$/', '', strtolower(trim($dedType->name))), $stdDeductionNames, true) && (empty($dedType->system_key) || !in_array($dedType->system_key, ['sss', 'philhealth', 'pagibig', 'tax', 'sss_loan', 'pagibig_loan', 'vale', 'goods', 'absences'], true)))
                                                            <div class="col-md-3 col-6">
                                                                <div class="form-check">
                                                                    <input class="form-check-input" type="checkbox" name="selected_deduction_types[]" value="{{ $dedType->id }}" id="ded_custom_{{ $dedType->id }}">
                                                                    <label class="form-check-label font-11" for="ded_custom_{{ $dedType->id }}">{{ $dedType->name }}</label>
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
                                        <i class="fe-download me-1"></i> Generate Deduction History Report Matrix
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 3. 13th Month Payroll History Matrix -->
                <div class="col-12 grid-margin stretch-card mb-3 report-card-item" data-category="history" data-keywords="13th month payroll history matrix total basic pay 11.5 annual bonus cutoff pdf excel csv">
                    <div class="card border-0 shadow-sm border-start border-4 border-success">
                        <div class="card-header bg-white py-3 border-0 d-flex align-items-center justify-content-between cursor-pointer" data-bs-toggle="collapse" data-bs-target="#collapse-13th-history">
                            <div class="d-flex align-items-center me-2">
                                <div class="avatar-sm rounded bg-soft-success text-success d-flex align-items-center justify-content-center me-3 flex-shrink-0" style="width:40px; height:40px;">
                                    <i class="fe-calendar font-18"></i>
                                </div>
                                <div>
                                    <h5 class="fw-bold text-dark font-15 mb-1">
                                        13th Month Payroll History
                                        <span class="badge bg-soft-success text-success font-11 ms-2">Total Basic ÷ 11.5</span>
                                    </h5>
                                    <p class="text-muted font-12 mb-0">Legacy wide payroll-history matrix of cutoff basic pay totals and 13th Month Pay computation (PDF, Excel, CSV)</p>
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm btn-light border text-nowrap font-12" data-bs-toggle="collapse" data-bs-target="#collapse-13th-history">
                                <i class="fe-chevron-down me-1"></i> Expand Filters
                            </button>
                        </div>
                        <div id="collapse-13th-history" class="collapse">
                            <div class="card-body border-top bg-light-subtle pt-3">
                                <form action="{{ panelRoute('reports.thirteenth-month-history') }}" method="POST" target="_blank">
                                    @csrf
                                    @if(isset($selectedCompanyId)) <input type="hidden" name="company_id" value="{{ $selectedCompanyId }}"> @endif

                                    <div class="row g-2 mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label font-12 fw-semibold">Select Target Year <span class="text-danger">*</span></label>
                                            <select name="year" class="form-select form-select-sm">
                                                @for($y = date('Y'); $y >= date('Y') - 5; $y--)
                                                    <option value="{{ $y }}" {{ (int)($year ?? date('Y')) === (int)$y ? 'selected' : '' }}>
                                                        {{ $y }} Calendar Year
                                                    </option>
                                                @endfor
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label font-12 fw-semibold">Export Format</label>
                                            <select name="export_format" class="form-select form-select-sm">
                                                <option value="pdf" selected>PDF Document (Landscape Matrix)</option>
                                                <option value="xlsx">Excel Sheet (.xlsx)</option>
                                                <option value="csv">CSV File (.csv)</option>
                                                <option value="html">Interactive Web Matrix View</option>
                                            </select>
                                        </div>
                                    </div>

                                    <button type="submit" class="btn btn-sm btn-success w-100 fw-semibold">
                                        <i class="fe-download me-1"></i> Generate 13th Month Payroll History
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
