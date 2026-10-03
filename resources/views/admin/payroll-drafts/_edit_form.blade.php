    <div class="col-12">
        @php
            $isHr = auth()->user()->role === 'hr' || auth()->user()->role_id === \App\Models\Role::HR;
            $statusClass = match($draft->status) {
                'draft' => 'bg-secondary',
                'pending_approval' => 'bg-warning text-dark',
                'approved' => 'bg-success',
                default => 'bg-danger',
            };
            $isEditable = $draft->canBeEditedBy(auth()->user());
        @endphp

        {{-- Top Header / Period Summary Bar --}}
        <div class="card border-0 shadow-sm mt-4 mb-3" style="border: 1px solid #e9ecef !important; border-top: 4px solid #f06115 !important;">
            <div class="card-body p-3 d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div>
                    <h4 class="mb-1 font-weight-bold text-dark"><i class="mdi mdi-calculator me-2" style="color: #f06115;"></i> Payroll Input Quick-Entry</h4>
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <span class="badge bg-primary font-weight-bold" style="font-size: 13px; padding: 5px 12px;">
                            <i class="mdi mdi-calendar me-1"></i> {{ $period->period_name }} ({{ date('M d, Y', strtotime($period->period_start)) }} &ndash; {{ date('M d, Y', strtotime($period->period_end)) }})
                        </span>
                        <span class="badge bg-info text-white font-weight-bold" style="font-size: 12px; padding: 5px 10px;">
                            Cutoff #{{ $period->cutoff_number ?? 1 }}
                        </span>
                        <span class="badge {{ $statusClass }} font-weight-bold" style="font-size: 12px; padding: 5px 10px;">
                            {{ strtoupper(str_replace('_', ' ', $draft->status)) }}
                        </span>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('payroll-drafts.index', ['payroll_period_id' => $period->id]) }}" class="btn btn-sm btn-secondary font-weight-bold" style="background-color: #6c757d; border: none; padding: 6px 18px; color: #fff;">
                        <i class="mdi mdi-arrow-left me-1"></i> Back to Draft List
                    </a>
                    @if($isEditable)
                        <button type="submit" form="payrollForm" class="btn btn-sm font-weight-bold text-white shadow-sm" style="background-color: #f06115; border: none; padding: 6px 18px;">
                            <i class="mdi mdi-content-save me-1"></i> {{ $draft->status === 'pending_approval' ? 'Save Payroll Correction' : 'Save Payroll Input' }}
                        </button>
                        @if($draft->canSubmit())
                            <button type="button" class="btn btn-sm btn-success font-weight-bold shadow-sm btn-submit-approval" style="padding: 6px 18px;">
                                <i class="mdi mdi-check-circle me-1"></i> Save &amp; Submit
                            </button>
                        @endif
                    @endif
                </div>
            </div>
        </div>

        {{-- Read-only Locked Draft Alert Banner / Correction Mode Banner --}}
        @if($draft->status === 'pending_approval' && $isEditable)
            <div class="alert alert-info font-14 font-weight-bold mb-3 shadow-sm" style="border-left: 5px solid #0dcaf0;">
                <i class="mdi mdi-shield-edit font-18 me-1 align-middle"></i>
                Administrative Correction Mode: You are correcting a submitted payroll awaiting approval. Saving your changes will update calculations and retain Pending Approval status.
            </div>
        @elseif($draft->status === 'approved' && ($canReopen ?? false))
            <div class="alert alert-warning font-14 font-weight-bold mb-3 shadow-sm d-flex flex-wrap justify-content-between align-items-center gap-2" style="border-left: 5px solid #ffc107;">
                <div>
                    <i class="mdi mdi-lock font-18 me-1 align-middle text-warning"></i>
                    This payroll has already been Approved and is locked against direct edits. To make corrections, you must reopen it.
                </div>
                <button type="button" class="btn btn-sm btn-warning font-weight-bold text-dark shadow-sm" data-bs-toggle="modal" data-bs-target="#reopenModal">
                    <i class="mdi mdi-lock-open-variant me-1"></i> Reopen for Correction
                </button>
            </div>
        @elseif(!$isEditable)
            <div class="alert alert-danger font-14 font-weight-bold mb-3 shadow-sm" style="border-left: 5px solid #dc3545;">
                <i class="mdi mdi-lock font-18 me-1 align-middle"></i>
                Payroll draft is locked ({{ strtoupper(str_replace('_', ' ', $draft->status)) }}). All inputs are read-only.
            </div>
        @endif

        @if($draft->requires_review)
            @php
                $revReasonLower = strtolower($draft->review_reason ?? '');
                $isMissingLoans = str_contains($revReasonLower, 'missing active loans') || str_contains($revReasonLower, 'no active loans') || str_contains($revReasonLower, 'loan deductions skipped') || str_contains($revReasonLower, 'skipped due to missing');
                $suggestedOverride = $isMissingLoans ? 'Employee has no active loans; skipped loan deductions confirmed.' : '';
            @endphp
            <div class="alert alert-warning mb-3 shadow-sm border-warning" style="border-left: 4px solid #ffc107 !important;">
                <div class="mb-2">
                    <strong class="text-dark"><i class="mdi mdi-alert font-18 me-1 align-middle text-warning"></i> Requires Review Before Submission:</strong>
                    <span class="d-block text-dark mt-1">{{ $draft->review_reason ?? 'Requires review before submission.' }}</span>
                </div>
                @if($isEditable)
                    <div class="mt-2 pt-2 border-top">
                        <label for="override_reason" class="form-label font-12 fw-semibold text-dark mb-1">
                            Confirmation / Override Reason:
                        </label>
                        <div class="input-group input-group-sm">
                            <input type="text" name="override_reason" id="override_reason" form="payrollForm" class="form-control form-control-sm" placeholder="Enter override reason to authorize submission..." value="{{ old('override_reason', $suggestedOverride) }}">
                            <button type="submit" form="payrollForm" class="btn btn-warning btn-sm font-weight-bold">
                                <i class="mdi mdi-check-circle me-1"></i> Confirm &amp; Clear Review Flag
                            </button>
                        </div>
                        <small class="text-muted font-11 mt-1 d-block">
                            <i class="mdi mdi-information-outline me-1"></i> Submitting with a confirmed override reason clears the review requirement and records an audit trail.
                        </small>
                    </div>
                @endif
            </div>
        @endif

        <div class="row">
            {{-- Left Panel: Employees With Generated Drafts --}}
            <div class="col-lg-3 col-md-4 mb-3">
                <div class="card border-0 shadow-sm" style="border: 1px solid #e9ecef !important; border-top: 3px solid #6c757d !important;">
                    <div class="card-header bg-white py-2 px-3">
                        <span class="font-weight-bold text-dark small"><i class="mdi mdi-account-group me-1"></i> Period Draft Employees</span>
                        <small class="text-muted d-block font-11">({{ $periodDrafts->count() }} drafts generated)</small>
                    </div>
                    <div class="p-2 border-bottom">
                        <input type="text" id="employeeSearchInput" class="form-control form-control-sm" placeholder="Search employee code or name...">
                    </div>
                    <div class="list-group list-group-flush" id="periodEmployeeList" style="max-height: 550px; overflow-y: auto;">
                        @forelse($periodDrafts as $pDraft)
                            @php
                                $isCurrent = ($pDraft->id === $draft->id);
                                $empItem = $pDraft->employee;
                            @endphp
                            <a href="{{ route('payroll-drafts.edit', ['id' => $pDraft->id, 'payroll_period_id' => $period->id]) }}"
                               class="list-group-item list-group-item-action p-2 employee-draft-item {{ $isCurrent ? 'active text-white' : '' }}"
                               data-search-term="{{ strtolower(($empItem->full_name ?? '') . ' ' . ($empItem->employee_code ?? '')) }}"
                               style="{{ $isCurrent ? 'background-color: #f06115 !important; border-color: #f06115 !important;' : '' }}">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <strong class="small text-truncate" style="max-width: 140px;">{{ $empItem->full_name ?? 'N/A' }}</strong>
                                    <span class="badge {{ $isCurrent ? 'bg-white text-dark' : 'bg-light text-dark border' }}" style="font-size: 10px;">{{ $empItem->employee_code ?? 'N/A' }}</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center font-11">
                                    <span class="{{ $isCurrent ? 'text-white-50' : 'text-muted' }}">{{ $empItem->department ?? 'N/A' }}</span>
                                    @if(!$isHr)
                                        <span class="fw-bold {{ $isCurrent ? 'text-white' : 'text-success' }}">₱ {{ number_format($pDraft->net_pay, 2) }}</span>
                                    @endif
                                </div>
                            </a>
                        @empty
                            <div class="p-3 text-center text-muted small">No generated drafts found for this period.</div>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- Right Panel: Selected Employee Payroll Draft --}}
            <div class="col-lg-9 col-md-8">
                <form action="{{ route('payroll-drafts.update', $draft->id) }}" method="POST" id="payrollForm">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="submit_after_save" id="submit_after_save" value="0">

                    <!-- Negative Net Pay Alert Banner -->
                    @if(!$isHr)
                        <div id="negative_net_pay_alert" class="alert alert-danger font-13 fw-bold mb-3 shadow-sm" style="display: {{ ($draft->net_pay < 0) ? 'block' : 'none' }}; border-left: 4px solid #dc3545;">
                            <i class="mdi mdi-alert-circle font-18 me-1 align-middle"></i>
                            Cannot save payroll draft. Employee <strong>{{ $employee->employee_code }} - {{ $employee->full_name }}</strong> has a negative net income of <span id="negative_net_amount">₱ {{ number_format($draft->net_pay, 2) }}</span>. Please adjust number inputs before saving.
                        </div>
                    @endif

                    {{-- Selected Employee Info Bar --}}
                    <div class="card border-0 shadow-sm mb-3 bg-light" style="border: 1px solid #e9ecef !important;">
                        <div class="card-body p-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
                            <div>
                                <h5 class="mb-0 font-weight-bold text-dark">{{ $employee->full_name }}</h5>
                                <small class="text-muted">
                                    Code: <strong>{{ $employee->employee_code }}</strong> |
                                    Dept: <strong>{{ $employee->department ?? 'N/A' }}</strong> |
                                    Position: <strong>{{ $employee->position ?? 'N/A' }}</strong>
                                </small>
                            </div>
                            @if(!$isHr)
                                <div class="text-end">
                                    <span class="font-11 text-muted d-block">Draft Net Pay</span>
                                    <h4 class="mb-0 text-success font-weight-bold" id="net_pay_header_display">₱ {{ number_format($draft->net_pay, 2) }}</h4>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Hidden Attendance Fields (Attendance Grid removed as requested) --}}
                    <input type="hidden" name="days_worked" id="days_worked" value="{{ old('days_worked', $draft->days_worked ?? 0) }}">
                    <input type="hidden" name="days_absent" id="days_absent" value="{{ old('days_absent', $draft->days_absent ?? 0) }}">
                    <input type="hidden" name="overtime_hours" id="overtime_hours" value="{{ old('overtime_hours', $draft->overtime_hours ?? 0) }}">
                    <input type="hidden" name="late_hours" id="late_hours" value="{{ old('late_hours', $draft->late_hours ?? 0) }}">

                    @php
                        $canEditIncomeNumberInputs = (bool) ($hasIncomeInputPermission ?? false);
                        $canSeeIncomeRates = (bool) ($hasIncomeRateVisibilityPermission ?? false);
                        $canAccessIncomeGrid = $canEditIncomeNumberInputs || $canSeeIncomeRates;
                        $canEditDeductions = (bool) ($hasDeductionInputPermission ?? false);
                    @endphp

                    @if($canAccessIncomeGrid)
                        {{-- 2. Master Quick-Entry Income Grid --}}
                        <div class="card border-0 shadow-sm mb-4" style="border: 1px solid #e9ecef !important;">
                            <div class="card-header bg-white py-2 px-3 d-flex justify-content-between align-items-center" style="border-bottom: 2px solid #10b981 !important;">
                                <span class="font-weight-bold text-dark"><i class="mdi mdi-cash-plus me-2 text-success"></i> Income / Earnings Grid (Master Quick-Entry)</span>

                                <div class="d-flex align-items-center gap-2">
                                @if($canSeeIncomeRates && $canEditIncomeNumberInputs)
                                    <small class="text-muted font-11">White cell = Editable Number Input | Gray cells = Fixed Rate &amp; Calculated Total</small>
                                @elseif($canSeeIncomeRates)
                                    <small class="text-muted font-11">Income Number Inputs are view-only. Rate and calculated total columns are visible.</small>
                                @else
                                    <small class="text-muted font-11">White cell = Editable Number Input</small>
                                @endif
                                @if($canAddIncome && $availableIncomeTypes->isNotEmpty())
                                    <button type="button" class="btn btn-sm btn-outline-success font-weight-bold" data-bs-toggle="modal" data-bs-target="#addIncomeModal">
                                        <i class="mdi mdi-plus-circle me-1"></i> Add Income
                                    </button>
                                @endif
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered align-middle mb-0" id="incomeGridTable">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: {{ $canSeeIncomeRates ? '30%' : '50%' }};">Income Type</th>
                                            <th style="width: {{ $canSeeIncomeRates ? '20%' : '30%' }}; background-color: #ffffff !important;" class="text-center">Number Input{{ $canEditIncomeNumberInputs ? ' (Editable)' : '' }}</th>
                                            @if($canSeeIncomeRates)
                                                <th style="width: 20%;" class="text-end">Rate (Fixed)</th>
                                                <th style="width: 20%;" class="text-end">Total (Calculated)</th>
                                            @endif
                                            <th style="width: {{ $isHr ? '20%' : '10%' }};" class="text-center">Source</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($payIns as $in)
                                            @php
                                                $lineTotal = (float) $in->rate * (float) $in->number_input;
                                            @endphp
                                            <tr>
                                                <td class="font-weight-semibold bg-light">{{ $in->incomeType->name ?? 'Income' }}</td>
                                                <td class="bg-white p-1 text-center">
                                                    <input type="number" step="any" min="0"
                                                           name="pay_ins[{{ $in->id }}][number_input]"
                                                           id="pay_in_num_{{ $in->id }}"
                                                           class="form-control form-control-sm pay-in-number-input text-center font-weight-bold"
                                                           data-pay-in-id="{{ $in->id }}"
                                                           data-code="{{ strtoupper($in->incomeType->code ?? '') }}"
                                                           @if(!$isHr) data-rate="{{ $in->rate }}" @endif
                                                           value="{{ old("pay_ins.{$in->id}.number_input", $in->number_input) }}"
                                                           style="border: 2px solid #10b981; background-color: #ffffff;"
                                                           {{ (!$isEditable || !$canEditIncomeNumberInputs) ? 'disabled' : '' }}>
                                                    <input type="hidden" name="pay_ins[{{ $in->id }}][id]" value="{{ $in->id }}">
                                                </td>
                                                @if($canSeeIncomeRates)
                                                    <td class="bg-light text-end">₱ {{ number_format($in->rate, 2) }}</td>
                                                    <td class="bg-light text-end font-weight-bold">
                                                        <span id="pay_in_total_{{ $in->id }}" class="pay-in-total-span" data-amount="{{ $lineTotal }}">₱ {{ number_format($lineTotal, 2) }}</span>
                                                    </td>
                                                @endif
                                                <td class="bg-light text-center">
                                                    <span class="badge bg-light text-dark border">{{ $in->incomeType->code ?? 'DRAFT' }}</span>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="{{ $canSeeIncomeRates ? '5' : '3' }}" class="text-center text-muted p-3">No generated income lines found for this draft.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            @if(!$isHr)
                                <div class="card-footer bg-white py-2 px-3 d-flex justify-content-between align-items-center" style="border-top: 1px solid #e9ecef;">
                                    <span class="font-weight-bold text-dark font-13">
                                        <i class="mdi mdi-cash-plus text-success me-1"></i> Total Income (Gross Pay)
                                    </span>
                                    <h5 class="mb-0 text-success font-weight-bold" id="section_total_gross">₱ {{ number_format($draft->total_gross_pay, 2) }}</h5>
                                </div>
                            @endif
                        </div>
                    @endif

                    {{-- 3. Deduction Grid --}}
                    <div class="card border-0 shadow-sm mb-4" style="border: 1px solid #e9ecef !important;">
                        <div class="card-header bg-white py-2 px-3 d-flex justify-content-between align-items-center" style="border-bottom: 2px solid #dc3545 !important;">
                            <span class="font-weight-bold text-dark"><i class="mdi mdi-minus-circle me-2 text-danger"></i> Deductions Grid</span>

                            <div class="d-flex align-items-center gap-2">
                                <small class="text-muted font-11">Generated draft deduction lines{{ !$canEditDeductions ? ' (View-only)' : '' }}</small>
                                @if($canAddDeduction && ($availableDeductionTypes->isNotEmpty() || $availableLoanDeductions->isNotEmpty()))
                                    <button type="button" class="btn btn-sm btn-outline-danger font-weight-bold" data-bs-toggle="modal" data-bs-target="#addDeductionModal">
                                        <i class="mdi mdi-plus-circle me-1"></i> Add Deduction
                                    </button>
                                @endif
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered align-middle mb-0" id="deductionGridTable">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 40%;">Deduction Type</th>
                                        <th style="width: 35%;" class="text-end">Calculated Deduction (₱)</th>
                                        <th style="width: 25%;" class="text-center">Deduction Source</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($payOuts as $out)
                                        @php
                                            $isAbsences = strtoupper($out->deductionType->code ?? '') === 'ABSENCES';
                                            $rawQty = (float) ($out->number_input ?? 1.00);
                                            $rawRate = (float) $out->rate;
                                            $rawTotal = (float) ($out->amount ?? ($rawRate * $rawQty));

                                            if ($isAbsences && $isEditable && $canEditDeductions) {
                                                $effectiveQty = 1.00;
                                                $displayRate = ($rawQty != 1.00) ? $rawTotal : $rawRate;
                                                $outTotal = $displayRate;
                                            } else {
                                                $effectiveQty = $rawQty;
                                                $displayRate = $rawRate;
                                                $outTotal = $rawTotal;
                                            }
                                            $isLoanLine = !empty($out->loan_id);
                                        @endphp
                                        <tr>
                                            <td class="font-weight-semibold bg-light">
                                                {{ $out->deductionType->name ?? 'Deduction' }}
                                                @if($isLoanLine)
                                                    <br><small class="text-primary font-11"><i class="mdi mdi-link me-1"></i>Loan: <strong>{{ $out->loan?->notes ?? $out->loan?->loanType?->name ?? 'Active Loan' }}</strong> (Current Bal: ₱{{ number_format((float)($out->loan?->current_balance ?? 0), 2) }})</small>
                                                @endif
                                            </td>
                                            <td class="bg-light text-end font-weight-bold">
                                                <input type="number" step="any" min="0"
                                                       name="pay_outs[{{ $out->id }}][rate]"
                                                       id="pay_out_rate_{{ $out->id }}"
                                                       class="form-control form-control-sm pay-out-rate-input text-end font-weight-bold mb-1"
                                                       data-pay-out-id="{{ $out->id }}"
                                                       data-code="{{ strtoupper($out->deductionType->code ?? '') }}"
                                                       data-qty="{{ $effectiveQty }}"
                                                       data-loan-id="{{ $out->loan_id }}"
                                                       data-loan-balance="{{ $out->loan?->current_balance ?? '' }}"
                                                       value="{{ old("pay_outs.{$out->id}.rate", $displayRate) }}"
                                                       style="border: 2px solid #dc3545; background-color: #ffffff;"
                                                       {{ (!$isEditable || !$canEditDeductions) ? 'disabled' : '' }}>
                                                @if($effectiveQty > 1.00)
                                                    <small class="text-muted d-block font-11">Total: <span id="pay_out_total_{{ $out->id }}" class="pay-out-total-span" data-amount="{{ $outTotal }}">₱ {{ number_format($outTotal, 2) }}</span> ({{ $effectiveQty }} &times; ₱{{ number_format((float)$displayRate, 2) }})</small>
                                                @else
                                                    <span id="pay_out_total_{{ $out->id }}" class="pay-out-total-span d-none" data-amount="{{ $outTotal }}">₱ {{ number_format($outTotal, 2) }}</span>
                                                @endif
                                                <input type="hidden" name="pay_outs[{{ $out->id }}][id]" value="{{ $out->id }}">
                                            </td>
                                            <td class="bg-light text-center">
                                                <span class="badge bg-light text-dark border">{{ $out->deductionType->code ?? 'DEDUCTION' }}</span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="text-center text-muted p-3">No generated deduction lines found for this draft.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="card-footer bg-white py-2 px-3 d-flex justify-content-between align-items-center" style="border-top: 1px solid #e9ecef;">
                            <span class="font-weight-bold text-dark font-13">
                                <i class="mdi mdi-minus-circle text-danger me-1"></i> Total Deductions
                            </span>
                            <h5 class="mb-0 text-danger font-weight-bold" id="section_total_deductions">₱ {{ number_format($draft->total_deductions, 2) }}</h5>
                        </div>
                    </div>

                    @if(!$isHr)
                        {{-- 4. Totals Summary Box --}}
                        <div class="card border-0 shadow-sm mb-4" style="border: 1px solid #e9ecef !important; border-left: 4px solid #10b981 !important;">
                            <div class="card-header bg-white py-2 px-3">
                                <span class="font-weight-bold text-dark"><i class="mdi mdi-chart-line me-2 text-primary"></i> Payroll Summary</span>
                            </div>
                            <div class="card-body p-3">

                                <div class="row text-center align-items-center">
                                    <div class="col-md-4">
                                        <span class="text-muted small d-block mb-1">Total Income (Gross Pay)</span>
                                        <h4 class="mb-0 text-success font-weight-bold" id="summary_total_gross">₱ {{ number_format($draft->total_gross_pay, 2) }}</h4>
                                    </div>
                                    <div class="col-md-4 border-start border-end">
                                        <span class="text-muted small d-block mb-1">Total Deductions</span>
                                        <h4 class="mb-0 text-danger font-weight-bold" id="summary_total_deductions">₱ {{ number_format($draft->total_deductions, 2) }}</h4>
                                    </div>
                                    <div class="col-md-4">
                                        <span class="text-muted small d-block mb-1">Net Pay (Estimated)</span>
                                        <h3 class="mb-0 text-primary font-weight-bold" id="summary_net_pay">₱ {{ number_format($draft->net_pay, 2) }}</h3>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- Form Submit Controls --}}
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <a href="{{ route('payroll-drafts.index', ['payroll_period_id' => $draft->payroll_period_id]) }}" class="btn btn-secondary btn-sm">
                            <i class="mdi mdi-arrow-left me-1"></i> Back to Drafts List
                        </a>

                        @if($isEditable)
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-success btn-sm font-weight-bold" id="btn_save_draft">
                                    <i class="mdi mdi-content-save me-1"></i> {{ $draft->status === 'pending_approval' ? 'Save Payroll Correction' : 'Save Payroll Input' }}
                                </button>
                                @if($draft->canSubmit())
                                    <button type="button" id="btn_submit_approval" class="btn btn-primary btn-sm font-weight-bold btn-submit-approval">
                                        <i class="mdi mdi-check-circle me-1"></i> Save &amp; Submit for Approval
                                    </button>
                                @endif
                            </div>
                        @endif
                    </div>
                </form>
            </div>
        </div>
    </div>
