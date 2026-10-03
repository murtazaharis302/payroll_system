                <!-- 3. Single-Period Income History -->
                <div class="col-12 grid-margin stretch-card mb-3 report-card-item" data-category="history" data-keywords="single period income history itemized basic overtime allowance incentive pdf excel csv">
                    <div class="card border-0 shadow-sm border-start border-4 border-success">
                        <div class="card-header bg-white py-3 border-0 d-flex align-items-center justify-content-between cursor-pointer" data-bs-toggle="collapse" data-bs-target="#collapse-sp-income">
                            <div class="d-flex align-items-center me-2">
                                <div class="avatar-sm rounded bg-soft-success text-success d-flex align-items-center justify-content-center me-3 flex-shrink-0" style="width:40px; height:40px;">
                                    <i class="fe-plus-circle font-18"></i>
                                </div>
                                <div>
                                    <h5 class="fw-bold text-dark font-15 mb-1">
                                        Single-Period Income History
                                        <span class="badge bg-soft-success text-success font-11 ms-2">Single Period Itemized</span>
                                    </h5>
                                    <p class="text-muted font-12 mb-0">Itemized earnings category breakdown for a single specific payroll period (PDF, Excel, CSV)</p>
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm btn-light border text-nowrap font-12" data-bs-toggle="collapse" data-bs-target="#collapse-sp-income">
                                <i class="fe-chevron-down me-1"></i> Expand Filters
                            </button>
                        </div>
                        <div id="collapse-sp-income" class="collapse">
                            <div class="card-body border-top bg-light-subtle pt-3">
                                <form action="{{ panelRoute('reports.income-history') }}" method="POST" target="_blank">
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
                                                <input class="form-check-input" type="checkbox" name="dont_include_zero" value="1" id="dontIncZeroSingleInc" checked>
                                                <label class="form-check-label font-12 text-dark fw-semibold" for="dontIncZeroSingleInc">Don't include 0 amount</label>
                                            </div>
                                            <small class="text-muted font-11">Hide employees with zero total earnings in period.</small>
                                        </div>
                                        <div class="col-md-8">
                                            <div class="d-flex align-items-center justify-content-between mb-1">
                                                <label class="form-label font-12 fw-semibold mb-0">Select Income Types to Include</label>
                                                <div class="font-11">
                                                    <a href="javascript:void(0)" onclick="toggleAllCheckboxes('sp_inc_box', true)" class="text-success fw-semibold me-2">Select All</a>
                                                    <a href="javascript:void(0)" onclick="toggleAllCheckboxes('sp_inc_box', false)" class="text-muted">Clear</a>
                                                </div>
                                            </div>
                                            <div class="border rounded p-2 bg-light" id="sp_inc_box" style="max-height: 85px; overflow-y: auto;">
                                                <div class="row g-1">
                                                    <div class="col-md-3 col-6">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" name="selected_income_types[]" value="basic_salary" id="sp_inc_basic" checked>
                                                            <label class="form-check-label font-11" for="sp_inc_basic">Basic Salary</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-3 col-6">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" name="selected_income_types[]" value="overtime_pay" id="sp_inc_ot" checked>
                                                            <label class="form-check-label font-11" for="sp_inc_ot">Overtime</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-3 col-6">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" name="selected_income_types[]" value="night_diff_pay" id="sp_inc_nd" checked>
                                                            <label class="form-check-label font-11" for="sp_inc_nd">Night Diff</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-3 col-6">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" name="selected_income_types[]" value="holiday_pay" id="sp_inc_holiday" checked>
                                                            <label class="form-check-label font-11" for="sp_inc_holiday">Holiday Pay</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-3 col-6">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" name="selected_income_types[]" value="thirteenth_month_pay" id="sp_inc_13th" checked>
                                                            <label class="form-check-label font-11" for="sp_inc_13th">13th Month Pay</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-3 col-6">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" name="selected_income_types[]" value="allowances" id="sp_inc_allowance" checked>
                                                            <label class="form-check-label font-11" for="sp_inc_allowance">Allowance</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-3 col-6">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" name="selected_income_types[]" value="incentives" id="sp_inc_incentive" checked>
                                                            <label class="form-check-label font-11" for="sp_inc_incentive">Incentive</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-3 col-6">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" name="selected_income_types[]" value="other_income" id="sp_inc_other" checked>
                                                            <label class="form-check-label font-11" for="sp_inc_other">Other Income</label>
                                                        </div>
                                                    </div>
                                                    @if(isset($incomeTypes) && count($incomeTypes) > 0)
                                                        @foreach($incomeTypes as $incType)
                                                            @if(!in_array(strtolower(trim($incType->name)), $stdIncomeNames, true) && !in_array(preg_replace('/\s*\d+$/', '', strtolower(trim($incType->name))), $stdIncomeNames, true) && (empty($incType->system_key) || !in_array($incType->system_key, ['basic', 'overtime', 'allowance', 'bonus', 'other'], true)))
                                                            <div class="col-md-3 col-6">
                                                                <div class="form-check">
                                                                    <input class="form-check-input" type="checkbox" name="selected_income_types[]" value="{{ $incType->id }}" id="sp_inc_custom_{{ $incType->id }}">
                                                                    <label class="form-check-label font-11" for="sp_inc_custom_{{ $incType->id }}">{{ $incType->name }}</label>
                                                                </div>
                                                            </div>
                                                            @endif
                                                        @endforeach
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <button type="submit" class="btn btn-sm btn-success w-100 fw-semibold">
                                        <i class="fe-download me-1"></i> Generate Single-Period Income History
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
