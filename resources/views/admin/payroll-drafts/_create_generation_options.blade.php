    <div class="col-12">
        <div class="card mt-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Generate Payroll Drafts</h4>
                <a href="{{ route('payroll-drafts.index') }}" class="btn btn-secondary" style="background-color: #6c757d; border: none; padding: 8px 18px; font-weight: bold; color: #fff;">
                    <i class="mdi mdi-arrow-left"></i> Back to List
                </a>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <h5><i class="fa fa-info-circle"></i> Generate Payroll Drafts</h5>
                    <p>This will create payroll draft records for all active employees in the selected period.
                       You can then edit each employee's payroll details individually.</p>
                    <ul>
                        <li>Only active employees will be included</li>
                        <li>If drafts already exist for an employee in the period, they will be skipped</li>
                        <li>After generation, you can input attendance and other payroll data</li>
                    </ul>
                </div>

                @if(session('generation_errors') && is_array(session('generation_errors')) && count(session('generation_errors')) > 0)
                    <div class="alert alert-danger mb-4 shadow-sm" style="border-left: 4px solid #dc3545;">
                        <h5 class="font-weight-bold mb-2">
                            <i class="fa fa-exclamation-circle me-1"></i> Payroll Generation Blocked ({{ count(session('generation_errors')) }} {{ count(session('generation_errors')) === 1 ? 'issue' : 'issues' }} found)
                        </h5>
                        <p class="mb-2 small">The following setup issues must be resolved before payroll drafts can be generated:</p>
                        <ul class="mb-0 ps-3">
                            @foreach(session('generation_errors') as $genError)
                                <li class="small font-weight-semibold">{{ $genError }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if(auth()->user()->role === 'admin' && isset($companies) && $companies->count() > 0)
                    <div class="row mb-3">
                        <div class="col-md-8">
                            <label for="company_id" class="form-label font-weight-bold">Select Company <span class="text-danger">*</span></label>
                            <select name="company_id" id="company_id_select" class="form-control" onchange="window.location.href='{{ route('payroll-drafts.create') }}?company_id=' + this.value">
                                @foreach($companies as $company)
                                    <option value="{{ $company->id }}" {{ $selectedCompanyId == $company->id ? 'selected' : '' }}>
                                        {{ $company->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                @endif

                @if($payrollPeriods->isEmpty())
                    <div class="alert alert-warning mb-4">
                        <i class="fa fa-exclamation-triangle me-1"></i>
                        No payroll periods found for <strong>{{ $companies->firstWhere('id', $selectedCompanyId)?->name ?? 'the selected company' }}</strong>.
                        Please <a href="{{ panelRoute('payroll-periods.index') }}" class="alert-link">create a payroll period</a> for this company first before generating payroll drafts.
                    </div>
                @endif

                <form action="{{ route('payroll-drafts.generate') }}" method="POST">
                    @csrf
                    <input type="hidden" name="company_id" value="{{ $selectedCompanyId }}">

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="payroll_period_id" class="form-label font-weight-bold">Payroll Period <span class="text-danger">*</span></label>
                            <select name="payroll_period_id" id="payroll_period_id"
                                    class="form-control @error('payroll_period_id') is-invalid @enderror" required {{ $payrollPeriods->isEmpty() ? 'disabled' : '' }}>
                                <option value="">Select Payroll Period</option>
                                @foreach($payrollPeriods as $period)
                                    <option value="{{ $period->id }}" {{ old('payroll_period_id', $selectedPeriodId) == $period->id ? 'selected' : '' }}>
                                        {{ $period->period_name }} - {{ date('M d', strtotime($period->period_start)) }} to {{ date('M d, Y', strtotime($period->period_end)) }}
                                    </option>
                                @endforeach
                            </select>
                            @error('payroll_period_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="dept_id_select" class="form-label font-weight-bold">Department</label>
                            <select name="dept_id" id="dept_id_select" class="form-control @error('dept_id') is-invalid @enderror" onchange="if(typeof filterEmployeesByDepartment === 'function') filterEmployeesByDepartment(this.value)">
                                <option value="">All Departments</option>
                                @if(isset($departments) && $departments->count() > 0)
                                    @foreach($departments as $dept)
                                        <option value="{{ $dept->id }}" {{ old('dept_id', $selectedDeptId ?? '') == $dept->id ? 'selected' : '' }}>
                                            {{ $dept->name }} ({{ $dept->code }})
                                        </option>
                                    @endforeach
                                @endif
                            </select>
                            @error('dept_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    @php
                        $canSeeIncomeRates = $hasIncomeRateVisibilityPermission ?? \App\Models\MenuRoleAccess::hasModuleAccess(auth()->user(), 'payroll_income_rate_visibility');
                        $isFixedIncomeModeDefault = old('use_fixed_income_amount') !== null ? (string)old('use_fixed_income_amount') === '1' : (isset($companyIncomeDefaults['use_fixed_income_amount']) ? (bool)$companyIncomeDefaults['use_fixed_income_amount'] : true);
                        $savedIncomes = collect($companyIncomeDefaults['incomes'] ?? []);
                        $hasBothConfigCards = $canSeeIncomeConfig && $canSeeDeductionConfig;
                    @endphp

                    <!-- Only show tabs when there is a choice between two permitted cards. -->
                    @if($hasBothConfigCards)
                    <div class="card border-0 bg-transparent mb-3">
                        <div class="d-flex align-items-center gap-2">
                            <button type="button" id="tab_btn_incomes" onclick="switchConfigTab('incomes')" class="btn btn-md font-weight-bold shadow-sm" style="border-radius: 8px; border: 2px solid #10b981; background-color: #10b981; color: #ffffff; min-width: 190px;">
                                <i class="mdi mdi-cash-plus me-1"></i> Income Configuration
                            </button>
                            <button type="button" id="tab_btn_deductions" onclick="switchConfigTab('deductions')" class="btn btn-md font-weight-bold shadow-sm" style="border-radius: 8px; border: 2px solid #cbd5e1; background-color: #ffffff; color: #475569; min-width: 190px;">
                                <i class="mdi mdi-tune me-1"></i> Deduction Configuration
                            </button>
                        </div>
                    </div>
                    @endif

                    @if($canSeeIncomeConfig)
                    <!-- Income Calculation Mode & Selective Inclusion Card -->
                    <div id="card_income_config" class="card border mb-4 shadow-sm bg-white" style="border: 1px solid #e9ecef !important; border-top: 3px solid #10b981 !important; display: block;">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <h5 class="font-weight-bold text-dark mb-0">
                                    <i class="mdi mdi-cash-plus text-success me-1"></i> Income Configuration & Source Mode
                                    @if(!empty($companyIncomeDefaults['incomes']))
                                        <span class="badge bg-success text-white ms-2" style="font-size: 11px;" title="Company-wide income settings applied">
                                            <i class="mdi mdi-shield-check me-1"></i> Company Defaults Active
                                        </span>
                                    @endif
                                </h5>

                                @if($canManageCompanyDefaults)
                                    <button type="button" onclick="saveCompanyIncomeDefaults()" class="btn btn-sm btn-outline-success font-weight-bold" style="border-color: #10b981; color: #10b981; font-weight: bold;">
                                        <i class="mdi mdi-content-save-cog me-1"></i> Save as Company Defaults
                                    </button>
                                @endif
                            </div>
                            <p class="text-muted small mb-3">Selectively include/exclude earnings for this draft cycle.</p>

                            @if($canSeeIncomeRates)
                                <div class="row mb-3">
                                    <div class="col-md-8">
                                        <label class="form-label font-weight-bold text-dark mb-1">Income Source Mode</label>
                                        <div class="d-flex gap-4">
                                            <div class="form-check me-3">
                                                <input class="form-check-input" type="radio" name="use_fixed_income_amount" id="mode_income_auto" value="0" {{ !$isFixedIncomeModeDefault ? 'checked' : '' }} onchange="toggleFixedIncomeInputs()">
                                                <label class="form-check-label font-weight-semibold text-dark" for="mode_income_auto">
                                                    <i class="mdi mdi-calculator text-success me-1"></i> Automated / Assigned System Rates
                                                </label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="use_fixed_income_amount" id="mode_income_fixed" value="1" {{ $isFixedIncomeModeDefault ? 'checked' : '' }} onchange="toggleFixedIncomeInputs()">
                                                <label class="form-check-label font-weight-semibold text-dark" for="mode_income_fixed">
                                                    <i class="mdi mdi-pencil-lock text-warning me-1"></i> Manual / Fixed Amount & Value Entry
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @else
                                <div class="mb-3">
                                    <span class="badge bg-light text-success border font-12 py-1 px-2">
                                        <i class="mdi mdi-calculator me-1"></i> Automated calculation mode
                                    </span>
                                </div>
                            @endif

                            <div class="table-responsive">
                                <table class="table table-sm table-bordered align-middle mb-0" style="max-width: {{ !$canSeeIncomeRates ? '540px' : '720px' }};">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 50px;" class="text-center">
                                                <input type="checkbox" id="select_all_incomes" class="form-check-input" onchange="toggleSelectAllIncomes(this)" title="Select / Deselect All Incomes">
                                            </th>
                                            <th style="width: {{ $canSeeIncomeRates ? '280px' : '300px' }};">Income Type</th>
                                            <th style="width: 150px;">Number Input</th>
                                            @if($canSeeIncomeRates)
                                                <th style="width: 180px;">Manual Value / Amount</th>
                                            @endif
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($allCompanyIncomeTypes as $index => $incTypeModel)
                                            @php
                                                $matchedSaved = $savedIncomes->firstWhere('income_type_id', $incTypeModel->id)
                                                    ?? $savedIncomes->firstWhere('income_type', (string)$incTypeModel->id)
                                                    ?? $savedIncomes->firstWhere('income_type', $incTypeModel->id)
                                                    ?? $savedIncomes->firstWhere('income_type', $incTypeModel->code);
                                                $defaultDisplayed = $matchedSaved ? (bool)($matchedSaved['display'] ?? false) : false;
                                                $isDisplayed = old('incomes.'.$index.'.display') !== null ? (string)old('incomes.'.$index.'.display') === '1' : $defaultDisplayed;

                                                $defaultNumberInput = ($matchedSaved && isset($matchedSaved['number_input']) && is_numeric($matchedSaved['number_input'])) ? (float)$matchedSaved['number_input'] : '';
                                                $savedNumberInput = old('incomes.'.$index.'.number_input', $defaultNumberInput);

                                                $defaultAmount = $matchedSaved ? number_format((float)($matchedSaved['amount'] ?? 0), 2, '.', '') : '';
                                                $savedAmount = old('incomes.'.$index.'.amount', $defaultAmount);
                                            @endphp
                                            <tr>
                                                <td class="text-center">
                                                    <input type="hidden" name="incomes[{{ $index }}][display]" value="0">
                                                    <input type="checkbox" name="incomes[{{ $index }}][display]" value="1" class="form-check-input income-display-chk" {{ $isDisplayed ? 'checked' : '' }} onchange="syncMasterIncomeCheckbox()">
                                                    <input type="hidden" name="incomes[{{ $index }}][income_type_id]" value="{{ $incTypeModel->id }}">
                                                    <input type="hidden" name="incomes[{{ $index }}][income_type]" value="{{ $incTypeModel->id }}">
                                                </td>
                                                <td class="income-label-cell">
                                                    <span class="font-weight-semibold text-dark">{{ $incTypeModel->name }}</span>
                                                    <span class="badge bg-light text-muted border ms-2 small">{{ $incTypeModel->code }}</span>
                                                </td>
                                                <td>
                                                    <input type="number" step="0.01" min="0" name="incomes[{{ $index }}][number_input]" id="income_number_input_{{ $index }}" class="form-control form-control-sm income-number-input" placeholder="0.00" value="{{ $savedNumberInput }}">
                                                </td>
                                                @if($canSeeIncomeRates)
                                                    <td>
                                                        <input type="number" step="0.01" min="0" name="incomes[{{ $index }}][amount]" id="income_input_{{ $index }}" class="form-control form-control-sm fixed-income-input" placeholder="₱ 0.00" value="{{ $savedAmount }}" {{ !$isFixedIncomeModeDefault ? 'disabled' : '' }}>
                                                    </td>
                                                @endif
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="{{ $canSeeIncomeRates ? 4 : 3 }}" class="text-center text-muted py-3">
                                                    No active income types found for this company.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    @endif
