@php
    $isHr = auth()->user()->role === 'hr' || auth()->user()->role_id === \App\Models\Role::HR;
@endphp

@if($canAddIncome && $availableIncomeTypes->isNotEmpty())
        <div class="modal fade" id="addIncomeModal" tabindex="-1" aria-labelledby="addIncomeModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form method="POST" action="{{ panelRoute('payroll-drafts.add-income', ['id' => $draft->id]) }}" class="modal-content">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title font-weight-bold" id="addIncomeModalLabel"><i class="mdi mdi-cash-plus text-success me-1"></i> Add Income to Draft</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label font-weight-semibold" for="add_income_type_id">Income Type</label>
                            <select class="form-select" id="add_income_type_id" name="income_type_id" required>
                                <option value="">Select an income type</option>
                                @foreach($availableIncomeTypes as $type)
                                    <option value="{{ $type->id }}">{{ $type->name }} ({{ $type->code }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label font-weight-semibold" for="add_income_number_input">Number Input / Quantity</label>
                            <input type="number" step="any" min="0" class="form-control" id="add_income_number_input" name="number_input" value="0" required>
                        </div>
                        @if(!$isHr)
                            <div class="mb-0">
                                <label class="form-label font-weight-semibold" for="add_income_rate">One-Period Rate / Value <small class="text-muted">(optional)</small></label>
                                <input type="number" step="0.01" min="0" class="form-control" id="add_income_rate" name="rate">
                            </div>
                        @else
                            <small class="text-muted d-block">The system derives any eligible rate. Unassigned types are added at zero rate for this draft only.</small>
                        @endif
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success"><i class="mdi mdi-plus-circle me-1"></i> Add Income</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if($canAddDeduction && ($availableDeductionTypes->isNotEmpty() || $availableLoanDeductions->isNotEmpty()))
        <div class="modal fade" id="addDeductionModal" tabindex="-1" aria-labelledby="addDeductionModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form method="POST" action="{{ panelRoute('payroll-drafts.add-deduction', ['id' => $draft->id]) }}" class="modal-content">
                    @csrf
                    <input type="hidden" name="loan_id" id="add_deduction_loan_id">
                    <div class="modal-header">
                        <h5 class="modal-title font-weight-bold" id="addDeductionModalLabel"><i class="mdi mdi-minus-circle text-danger me-1"></i> Add Deduction to Draft</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label font-weight-semibold" for="add_deduction_type_id">Deduction Type</label>
                            <select class="form-select" id="add_deduction_type_id" name="deduction_type_id" required>
                                <option value="">Select a deduction type</option>
                                @if($availableDeductionTypes->isNotEmpty())
                                    <optgroup label="Deduction Types">
                                        @foreach($availableDeductionTypes as $type)
                                            <option value="{{ $type->id }}">{{ $type->name }} ({{ $type->code }})</option>
                                        @endforeach
                                    </optgroup>
                                @endif
                                @if($availableLoanDeductions->isNotEmpty())
                                    <optgroup label="Active Loans">
                                        @foreach($availableLoanDeductions as $loan)
                                            <option value="{{ $loan->loanType->deduction_type_id }}" data-loan-id="{{ $loan->id }}">
                                                {{ $loan->loanType->deductionType->name }} — {{ $loan->notes }} (Balance: ₱{{ number_format((float) $loan->current_balance, 2) }})
                                            </option>
                                        @endforeach
                                    </optgroup>
                                @endif
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3 mb-md-0">
                                <label class="form-label font-weight-semibold" for="add_deduction_number_input">Number Input</label>
                                <input type="number" step="any" min="0" class="form-control" id="add_deduction_number_input" name="number_input" value="0">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label font-weight-semibold" for="add_deduction_rate">Amount / Rate <small class="text-muted">(optional)</small></label>
                                <input type="number" step="0.01" min="0" class="form-control" id="add_deduction_rate" name="rate">
                            </div>
                        </div>
                        <small class="text-muted d-block mt-3">Statutory deductions are initialized from the current draft calculation and remain editable after adding.</small>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger"><i class="mdi mdi-plus-circle me-1"></i> Add Deduction</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

    <!-- Unsaved Changes Protection Modal -->
    <div class="modal fade" id="unsavedChangesModal" tabindex="-1" aria-labelledby="unsavedChangesModalLabel" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-warning text-dark py-3">
                    <h5 class="modal-title font-weight-bold" id="unsavedChangesModalLabel">
                        <i class="mdi mdi-alert-circle me-1"></i> Unsaved Changes
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" id="btnCloseUnsavedModal"></button>
                </div>
                <div class="modal-body p-4 text-center">
                    <i class="mdi mdi-alert-outline display-4 text-warning d-block mb-2"></i>
                    <p class="font-weight-semibold text-dark fs-5 mb-1">You have unsaved changes for the current employee payroll draft.</p>
                    <p class="text-muted small mb-0">Would you like to save your edits before switching to another employee or leaving?</p>
                </div>
                <div class="modal-footer bg-light d-flex justify-content-between py-2">
                    <button type="button" class="btn btn-secondary font-weight-bold" id="btnUnsavedCancel" data-bs-dismiss="modal">
                        Cancel
                    </button>
                    <div>
                        <button type="button" class="btn btn-outline-danger font-weight-bold me-2" id="btnUnsavedDiscard">
                            <i class="mdi mdi-delete-outline me-1"></i> Discard
                        </button>
                        <button type="button" class="btn text-white font-weight-bold" id="btnUnsavedSave" style="background-color: #f06115; border: none;">
                            <i class="mdi mdi-content-save me-1"></i> Save
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($canReopen ?? false)
    <!-- Reopen for Correction Modal -->
    <div class="modal fade" id="reopenModal" tabindex="-1" aria-labelledby="reopenModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" action="{{ panelRoute('payroll-approvals.reopen', ['id' => $draft->id]) }}" class="modal-content">
                @csrf
                <div class="modal-header bg-warning text-dark py-3">
                    <h5 class="modal-title font-weight-bold" id="reopenModalLabel">
                        <i class="mdi mdi-lock-open-variant me-1"></i> Reopen Approved Payroll for Correction
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-3">
                    <p class="text-muted small mb-3">
                        Reopening will move this payroll record back to <strong>Pending Approval</strong> and clear its existing approval timestamp. The original payroll lines will be preserved and become editable.
                    </p>
                    <div class="mb-3">
                        <label for="reopen_reason" class="form-label font-weight-semibold">Reason for Reopening <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="reopen_reason" name="reopen_reason" rows="3" required placeholder="State the reason why this approved payroll is being reopened for corrections..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary btn-sm font-weight-bold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning btn-sm font-weight-bold text-dark">
                        <i class="mdi mdi-check-circle me-1"></i> Confirm &amp; Reopen
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif
