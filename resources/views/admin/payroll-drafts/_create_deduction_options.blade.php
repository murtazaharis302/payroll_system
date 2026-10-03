                    @if($canSeeDeductionConfig)
                    <!-- Deduction Calculation Mode & Selective Inclusion Card -->
                    <div id="card_deduction_config" class="card border mb-4 shadow-sm bg-white" style="border: 1px solid #e9ecef !important; border-top: 3px solid #f06115 !important; display: {{ ($canSeeIncomeConfig && $canSeeDeductionConfig) ? 'none' : 'block' }};">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <h5 class="font-weight-bold text-dark mb-0">
                                    <i class="mdi mdi-tune text-primary me-1" style="color: #f06115 !important;"></i> Deduction Configuration & Source Mode
                                    @if(!empty($companyDeductionDefaults['deductions']))
                                        <span class="badge bg-success text-white ms-2" style="font-size: 11px;" title="Company-wide deduction settings applied">
                                            <i class="mdi mdi-shield-check me-1"></i> Company Defaults Active
                                        </span>
                                    @endif
                                </h5>

                                @if($canManageCompanyDefaults)
                                    <button type="button" onclick="saveCompanyDefaults()" class="btn btn-sm btn-outline-primary font-weight-bold" style="border-color: #f06115; color: #f06115; font-weight: bold;">
                                        <i class="mdi mdi-content-save-cog me-1"></i> Save as Company Defaults
                                    </button>
                                @endif
                            </div>
                            <p class="text-muted small mb-3">Selectively include/exclude deductions for this draft cycle, and toggle between automated system calculations and fixed manual overrides. Company Admins can save these as enforced company-wide defaults.</p>

                            @php
                                $isFixedModeDefault = old('use_fixed_amount') !== null ? (string)old('use_fixed_amount') === '1' : (isset($companyDeductionDefaults['use_fixed_amount']) ? (bool)$companyDeductionDefaults['use_fixed_amount'] : true);
                                $savedDeductions = collect($companyDeductionDefaults['deductions'] ?? []);
                            @endphp

                            <div class="row mb-3">
                                <div class="col-md-8">
                                    <label class="form-label font-weight-bold text-dark mb-1">Deduction Source Mode</label>
                                    <div class="d-flex gap-4">
                                        <div class="form-check me-3">
                                            <input class="form-check-input" type="radio" name="use_fixed_amount" id="mode_auto" value="0" {{ !$isFixedModeDefault ? 'checked' : '' }} onchange="toggleFixedDeductionInputs()">
                                            <label class="form-check-label font-weight-semibold text-dark" for="mode_auto">
                                                <i class="mdi mdi-calculator text-success me-1"></i> Automated / Assigned System Rates
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="use_fixed_amount" id="mode_fixed" value="1" {{ $isFixedModeDefault ? 'checked' : '' }} onchange="toggleFixedDeductionInputs()">
                                            <label class="form-check-label font-weight-semibold text-dark" for="mode_fixed">
                                                <i class="mdi mdi-pencil-lock text-warning me-1"></i> Manual / Fixed Amount Entry
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-sm table-bordered align-middle mb-0" style="max-width: 680px;">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 50px;" class="text-center">
                                                <input type="checkbox" id="select_all_deductions" class="form-check-input" onchange="toggleSelectAllDeductions(this)" title="Select / Deselect All Deductions">
                                            </th>
                                            <th style="width: 360px;">Deduction Type</th>
                                            <th style="width: 220px;">Manual Fixed Amount (₱)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php
                                            $defaultDeductionTypes = [];
                                            $standardDeductionMap = [
                                                'SSS' => ['label' => 'SSS Contribution', 'category' => 'Statutory'],
                                                'PHILHEALTH' => ['label' => 'PhilHealth Contribution', 'category' => 'Statutory'],
                                                'PAGIBIG' => ['label' => 'Pag-IBIG Contribution', 'category' => 'Statutory'],
                                                'TAX' => ['label' => 'Withholding Tax', 'category' => 'Statutory'],
                                                'SSS_LOAN' => ['label' => 'SSS Loan Deduction', 'category' => 'Loan'],
                                                'PAGIBIG_LOAN' => ['label' => 'Pag-IBIG Loan Deduction', 'category' => 'Loan'],
                                                'VALE' => ['label' => 'Vale / Cash Advance Deduction', 'category' => 'Loan'],
                                                'GOODS' => ['label' => 'Goods Purchase Deduction', 'category' => 'Loan'],
                                                'TARDINESS' => ['label' => 'Late / Tardiness Deduction', 'category' => 'Attendance'],
                                                'UNDERTIME' => ['label' => 'Undertime Deduction', 'category' => 'Attendance'],
                                                'OTHER' => ['label' => 'Other Deductions', 'category' => 'Other'],
                                            ];

                                            $processedDeductionCodes = [];

                                            if (isset($allCompanyDeductionTypes) && $allCompanyDeductionTypes->count() > 0) {
                                                foreach ($allCompanyDeductionTypes as $decTypeModel) {
                                                    $code = strtoupper($decTypeModel->code ?? '');
                                                    if (in_array($code, $processedDeductionCodes, true)) {
                                                        continue;
                                                    }
                                                    $processedDeductionCodes[] = $code;

                                                    if (isset($standardDeductionMap[$code])) {
                                                        $meta = $standardDeductionMap[$code];
                                                        $defaultDeductionTypes[] = array_merge(['type' => $code], $meta);
                                                    } else {
                                                        $defaultDeductionTypes[] = [
                                                            'type' => $decTypeModel->code ?? (string)$decTypeModel->id,
                                                            'label' => $decTypeModel->name,
                                                            'category' => 'Master Catalog',
                                                        ];
                                                    }
                                                }
                                            } else {
                                                foreach ($standardDeductionMap as $code => $meta) {
                                                    $defaultDeductionTypes[] = array_merge(['type' => $code], $meta);
                                                }
                                            }
                                        @endphp
                                        @foreach($defaultDeductionTypes as $index => $item)
                                            @php
                                                $matchedSaved = $savedDeductions->firstWhere('deduction_type', $item['type']);
                                                $defaultDisplayed = $matchedSaved ? (bool)($matchedSaved['display'] ?? false) : false;
                                                $isDisplayed = old('deductions.'.$index.'.display') !== null ? (string)old('deductions.'.$index.'.display') === '1' : $defaultDisplayed;

                                                $defaultAmount = $matchedSaved ? number_format((float)($matchedSaved['amount'] ?? 0), 2, '.', '') : '0.00';
                                                $savedAmount = old('deductions.'.$index.'.amount', $defaultAmount);
                                            @endphp
                                            <tr>
                                                <td class="text-center">
                                                    <input type="hidden" name="deductions[{{ $index }}][display]" value="0">
                                                    <input type="checkbox" name="deductions[{{ $index }}][display]" value="1" class="form-check-input deduction-display-chk" {{ $isDisplayed ? 'checked' : '' }} onchange="syncMasterDeductionCheckbox()">
                                                    <input type="hidden" name="deductions[{{ $index }}][deduction_type]" value="{{ $item['type'] }}">
                                                </td>
                                                <td class="deduction-label-cell" style="cursor: pointer;" onclick="focusDeductionInput({{ $index }})" title="Click to focus amount field">
                                                    <span class="font-weight-semibold text-dark">{{ $item['label'] }}</span>
                                                    <span class="badge bg-light text-muted border ms-2 small">{{ $item['category'] }}</span>
                                                </td>
                                                <td>
                                                    <input type="number" step="0.01" name="deductions[{{ $index }}][amount]" id="deduction_input_{{ $index }}" class="form-control form-control-sm fixed-amount-input" placeholder="0.00" value="{{ $savedAmount }}" {{ !$isFixedModeDefault ? 'disabled' : '' }}>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    @endif

                    <style>
                        @keyframes deductionPulse {
                            0% { box-shadow: 0 0 0 3px rgba(240, 97, 21, 0.6); border-color: #f06115; }
                            50% { box-shadow: 0 0 0 4px rgba(240, 97, 21, 0.4); border-color: #f06115; }
                            100% { box-shadow: 0 0 0 0 rgba(240, 97, 21, 0); }
                        }
                        .deduction-highlight-pulse {
                            animation: deductionPulse 1.5s ease-out;
                            border-color: #f06115 !important;
                        }
                        .deduction-label-cell:hover, .income-label-cell:hover {
                            background-color: #f8fafc;
                        }
                    </style>

                    <script>
                        function switchConfigTab(tabName) {
                            const incomeCard = document.getElementById('card_income_config');
                            const deductionCard = document.getElementById('card_deduction_config');
                            const btnIncomes = document.getElementById('tab_btn_incomes');
                            const btnDeductions = document.getElementById('tab_btn_deductions');

                            if (tabName === 'incomes') {
                                if (incomeCard) incomeCard.style.display = 'block';
                                if (deductionCard) deductionCard.style.display = 'none';

                                btnIncomes.style.backgroundColor = '#10b981';
                                btnIncomes.style.borderColor = '#10b981';
                                btnIncomes.style.color = '#ffffff';

                                btnDeductions.style.backgroundColor = '#ffffff';
                                btnDeductions.style.borderColor = '#cbd5e1';
                                btnDeductions.style.color = '#475569';
                            } else {
                                if (incomeCard) incomeCard.style.display = 'none';
                                if (deductionCard) deductionCard.style.display = 'block';

                                btnDeductions.style.backgroundColor = '#f06115';
                                btnDeductions.style.borderColor = '#f06115';
                                btnDeductions.style.color = '#ffffff';

                                btnIncomes.style.backgroundColor = '#ffffff';
                                btnIncomes.style.borderColor = '#cbd5e1';
                                btnIncomes.style.color = '#475569';
                            }
                        }

                        function saveCompanyIncomeDefaults() {
                            if (!confirm('Save these income rules and fixed values as company-wide defaults? Future draft runs will automatically enforce these settings.')) {
                                return;
                            }
                            const draftForm = document.querySelector('form[action*="payroll-drafts"]');
                            if (!draftForm) return;

                            document.querySelectorAll('.fixed-income-input, .income-number-input').forEach(input => input.disabled = false);

                            const periodSelect = document.getElementById('payroll_period_id');
                            if (periodSelect) {
                                periodSelect.removeAttribute('required');
                            }

                            draftForm.action = "{{ panelRoute('payroll-drafts.save-company-income-defaults') }}";
                            draftForm.submit();
                        }

                        function toggleFixedIncomeInputs() {
                            const isFixed = document.getElementById('mode_income_fixed').checked;
                            document.querySelectorAll('.fixed-income-input').forEach(input => {
                                input.disabled = !isFixed;
                            });
                        }

                        function focusIncomeInput(index) {
                            const modeFixed = document.getElementById('mode_income_fixed');
                            if (modeFixed && !modeFixed.checked) {
                                modeFixed.checked = true;
                                toggleFixedIncomeInputs();
                            }
                            const input = document.getElementById('income_input_' + index);
                            if (input) {
                                input.focus();
                                input.select();
                                input.classList.remove('deduction-highlight-pulse');
                                void input.offsetWidth;
                                input.classList.add('deduction-highlight-pulse');
                                setTimeout(() => {
                                    input.classList.remove('deduction-highlight-pulse');
                                }, 1500);
                            }
                        }

                        function toggleSelectAllIncomes(masterChk) {
                            document.querySelectorAll('.income-display-chk').forEach(chk => {
                                chk.checked = masterChk.checked;
                            });
                        }

                        function syncMasterIncomeCheckbox() {
                            const allChks = document.querySelectorAll('.income-display-chk');
                            const checkedChks = document.querySelectorAll('.income-display-chk:checked');
                            const master = document.getElementById('select_all_incomes');
                            if (master) {
                                master.checked = (allChks.length === checkedChks.length);
                            }
                        }

                        function focusDeductionInput(index) {
                            const modeFixed = document.getElementById('mode_fixed');
                            if (modeFixed && !modeFixed.checked) {
                                modeFixed.checked = true;
                                toggleFixedDeductionInputs();
                            }
                            const input = document.getElementById('deduction_input_' + index);
                            if (input) {
                                input.focus();
                                input.select();
                                input.classList.remove('deduction-highlight-pulse');
                                void input.offsetWidth;
                                input.classList.add('deduction-highlight-pulse');
                                setTimeout(() => {
                                    input.classList.remove('deduction-highlight-pulse');
                                }, 1500);
                            }
                        }

                        function toggleFixedDeductionInputs() {
                            const isFixed = document.getElementById('mode_fixed').checked;
                            document.querySelectorAll('.fixed-amount-input').forEach(input => {
                                input.disabled = !isFixed;
                            });
                        }

                        function saveCompanyDefaults() {
                            if (!confirm('Save these deduction rules and fixed amounts as company-wide defaults? Future draft runs will automatically enforce these settings.')) {
                                return;
                            }
                            const draftForm = document.querySelector('form[action*="payroll-drafts"]');
                            if (!draftForm) return;

                            // Enable inputs temporarily so amounts are posted
                            document.querySelectorAll('.fixed-amount-input').forEach(input => input.disabled = false);

                            // Remove required attribute from period select temporarily
                            const periodSelect = document.getElementById('payroll_period_id');
                            if (periodSelect) {
                                periodSelect.removeAttribute('required');
                            }

                            const originalAction = draftForm.action;
                            draftForm.action = "{{ panelRoute('payroll-drafts.save-company-deduction-defaults') }}";
                            draftForm.submit();
                        }

                        function toggleSelectAllDeductions(masterChk) {
                            document.querySelectorAll('.deduction-display-chk').forEach(chk => {
                                chk.checked = masterChk.checked;
                            });
                        }

                        function syncMasterDeductionCheckbox() {
                            const allChks = document.querySelectorAll('.deduction-display-chk');
                            const checkedChks = document.querySelectorAll('.deduction-display-chk:checked');
                            const master = document.getElementById('select_all_deductions');
                            if (master) {
                                master.checked = (allChks.length === checkedChks.length);
                            }
                        }

                        document.addEventListener('DOMContentLoaded', function() {
                            const form = document.querySelector('form[action*="payroll-drafts"]');
                            if (form) {
                                form.addEventListener('submit', function() {
                                    document.querySelectorAll('.fixed-amount-input, .fixed-income-input, .income-number-input').forEach(input => {
                                        input.disabled = false;
                                    });
                                });
                            }
                        });
                    </script>

                    <div class="row mb-3">
                        <div class="col-12">
                            <h5>Active Employees (<span id="emp_count_display">{{ $employees->count() }}</span>)</h5>
                            <div class="table-responsive">
                                <table class="table table-sm table-striped">
                                    <thead>
                                        <tr>
                                            <th>Code</th>
                                            <th>Name</th>
                                            <th>Position</th>
                                            <th>Department</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($employees as $employee)
                                        <tr class="employee-row" data-dept-id="{{ $employee->dept_id }}">
                                            <td>{{ $employee->employee_code }}</td>
                                            <td>{{ $employee->full_name }}</td>
                                            <td>{{ $employee->position ?? '-' }}</td>
                                            <td>{{ $employee->department ?? '-' }}</td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-warning">
                        <strong>Note:</strong> Existing drafts for this period will NOT be overwritten. Only new employee records will be created.
                    </div>

                    <div class="d-flex justify-content-end mt-4">
                        <button type="submit" id="btn_generate_drafts" class="btn btn-primary" style="background: linear-gradient(135deg, #1f618d, #2980b9); border: none; padding: 8px 24px; font-weight: bold; color: #fff;" {{ $payrollPeriods->isEmpty() ? 'disabled' : '' }} onclick="const count = document.getElementById('emp_count_display')?.textContent || '{{ $employees->count() }}'; return confirm('Generate payroll drafts for ' + count + ' employees?')">
                            <i class="fa fa-magic"></i> Generate Payroll Drafts
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <script>
            function filterEmployeesByDepartment(deptId) {
                const rows = document.querySelectorAll('.employee-row');
                let visibleCount = 0;
                rows.forEach(row => {
                    const rowDeptId = row.getAttribute('data-dept-id') || '';
                    if (!deptId || deptId === '' || deptId === 'all' || rowDeptId == deptId) {
                        row.style.display = '';
                        visibleCount++;
                    } else {
                        row.style.display = 'none';
                    }
                });
                const badge = document.getElementById('emp_count_display');
                if (badge) {
                    badge.textContent = visibleCount;
                }
            }

            document.addEventListener('DOMContentLoaded', function() {
                const deptSelect = document.getElementById('dept_id_select');
                if (deptSelect && deptSelect.value) {
                    filterEmployeesByDepartment(deptSelect.value);
                }
            });
        </script>
    </div>
