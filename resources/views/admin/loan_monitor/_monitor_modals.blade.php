<div class="modal fade" id="historyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="background:#ffffff; border:1px solid rgba(0,0,0,.15); color:#212529;">
            <div class="modal-header" style="border-bottom:1px solid rgba(0,0,0,.08)">
                <h5 class="modal-title text-dark" id="modalTitle">Payment History</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="modalBody">
                <!-- Loaded Dynamically -->
            </div>
        </div>
    </div>
</div>

{{-- ── Add Loan Modal ── --}}
<div class="modal fade" id="addLoanModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content" style="background:#ffffff; border:1px solid rgba(0,0,0,.15); color:#212529;">
            <div class="modal-header" style="border-bottom:1px solid rgba(0,0,0,.08)">
                <h5 class="modal-title text-dark">Add New Loan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ panelRoute('loan-monitor.store-loan') }}" method="POST">
                @csrf
                <div class="modal-body">
                    @if($errors->addLoan->any())
                        <div class="alert alert-danger py-2 px-3 mb-3 small">
                            <ul class="mb-0 ps-3">
                                @foreach($errors->addLoan->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="form-group mb-3">
                        <label class="form-label text-dark font-weight-bold">Employee <span class="text-danger">*</span></label>
                        <x-employee-select name="employee_id" id="modal_add_employee_id" placeholder="-- Search / Select Employee --" companyId="{{ $selectedCompanyId ?? null }}" :employees="$employees ?? null" required />
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label text-dark font-weight-bold">Loan Type / Deduction <span class="text-danger">*</span></label>
                        <select name="loan_type_id" class="form-control" style="background:#fff; color:#212529;" required>
                            <option value="">-- Select Loan Type / Deduction --</option>
                            @foreach($loanTypes as $lt)
                                <option value="{{ $lt->id }}" {{ (string)old('loan_type_id') === (string)$lt->id ? 'selected' : '' }}>
                                    {{ $lt->name }} @if($lt->deductionType) — Deduction: {{ $lt->deductionType->name }} ({{ $lt->deductionType->code }}) @endif
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label text-dark font-weight-bold">Loan Date / Start Date <span class="text-danger">*</span></label>
                        <input type="date" name="start_date" class="form-control" style="background:#fff; color:#212529;" value="{{ old('start_date', date('Y-m-d')) }}" required>
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label text-dark font-weight-bold">Purpose of Loan <span class="text-danger">*</span></label>
                        <input type="text" name="purpose" class="form-control" style="background:#fff; color:#212529;" placeholder="e.g. Emergency / Medical / Tuition" value="{{ old('purpose') }}" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 form-group mb-3">
                            <label class="form-label text-dark font-weight-bold">Principal Amount <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" id="add_principal_amount" name="principal_amount" class="form-control" style="background:#fff; color:#212529;" min="0.01" placeholder="e.g. 10000.00" value="{{ old('principal_amount') }}" required>
                        </div>
                        <div class="col-md-6 form-group mb-3">
                            <label class="form-label text-dark font-weight-bold">Term (Months) <span class="text-danger">*</span></label>
                            <input type="number" id="add_terms_months" name="terms_months" class="form-control" style="background:#fff; color:#212529;" min="1" value="{{ old('terms_months', 1) }}" required>
                        </div>
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label text-dark font-weight-bold">Monthly Interest Rate (%) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" id="add_monthly_interest_rate" name="monthly_interest_rate" class="form-control" style="background:#fff; color:#212529;" min="0" value="{{ old('monthly_interest_rate', '0.00') }}" placeholder="e.g. 1.00" required>
                    </div>
                    <div class="card p-3 mb-2" style="background:#f8f9fa; border:1px solid #e9ecef;">
                        <div class="d-flex justify-content-between small mb-1">
                            <span>Computed Interest:</span>
                            <strong id="add_preview_interest">₱0.00</strong>
                        </div>
                        <div class="d-flex justify-content-between small mb-1">
                            <span>Total Payable Amount:</span>
                            <strong id="add_preview_total">₱0.00</strong>
                        </div>
                        <div class="d-flex justify-content-between small">
                            <span class="text-primary font-weight-bold">Per Payday Cutoff (2x/mo):</span>
                            <strong class="text-primary" id="add_preview_cutoff">₱0.00</strong>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="border-top:1px solid rgba(0,0,0,.08)">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-orange">Save Loan</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ── Record Payment Modal ── --}}
<div class="modal fade" id="recordPaymentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content" style="background:#ffffff; border:1px solid rgba(0,0,0,.15); color:#212529;">
            <div class="modal-header" style="border-bottom:1px solid rgba(0,0,0,.08)">
                <h5 class="modal-title text-dark">Record Manual Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ panelRoute('loan-monitor.record-payment') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="form-group mb-3">
                        <label class="form-label text-dark font-weight-bold">Select Active Loan</label>
                        <select name="emp_loan_id" class="form-control" style="background:#fff; color:#212529;" required>
                            <option value="">Select Active Loan</option>
                            @foreach($loans->where('current_balance', '>', 0) as $l)
                                <option value="{{ $l->id }}">{{ $l->employee->full_name }} - {{ $l->loanType->name ?? 'Loan' }} (Bal: ₱{{ number_format($l->current_balance, 2) }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label text-dark font-weight-bold">Payment Amount</label>
                        <input type="number" step="0.01" name="amount_paid" class="form-control" style="background:#fff; color:#212529;" min="0.01" placeholder="e.g. 500.00" required>
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label text-dark font-weight-bold">Payment Date</label>
                        <input type="date" name="payment_date" class="form-control" style="background:#fff; color:#212529;" value="{{ date('Y-m-d') }}" required>
                    </div>
                </div>
                <div class="modal-footer" style="border-top:1px solid rgba(0,0,0,.08)">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-orange" style="background:#10b981; border-color:#10b981;">Save Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ── Edit Loan Modal ── --}}
<div class="modal fade" id="editLoanModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content" style="background:#ffffff; border:1px solid rgba(0,0,0,.15); color:#212529;">
            <div class="modal-header" style="border-bottom:1px solid rgba(0,0,0,.08)">
                <h5 class="modal-title text-dark">Edit Loan Amount</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editLoanForm" action="" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="form-group mb-3">
                        <label class="form-label text-dark font-weight-bold">Loan Amount</label>
                        <input type="number" step="0.01" name="principal_amount" id="editLoanPrincipal" class="form-control" style="background:#fff; color:#212529;" min="0.01" required>
                    </div>
                </div>
                <div class="modal-footer" style="border-top:1px solid rgba(0,0,0,.08)">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-orange">Update Loan Amount</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ── Edit Manual Payment Modal ── --}}
<div class="modal fade" id="editPaymentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content" style="background:#ffffff; border:1px solid rgba(0,0,0,.15); color:#212529;">
            <div class="modal-header" style="border-bottom:1px solid rgba(0,0,0,.08)">
                <h5 class="modal-title text-dark">Edit Manual Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editPaymentForm" action="" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="form-group mb-3">
                        <label class="form-label text-dark font-weight-bold">Payment Amount</label>
                        <input type="number" step="0.01" name="amount_paid" id="editPaymentAmount" class="form-control" style="background:#fff; color:#212529;" min="0.01" required>
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label text-dark font-weight-bold">Payment Date</label>
                        <input type="date" name="payment_date" id="editPaymentDate" class="form-control" style="background:#fff; color:#212529;" required>
                    </div>
                </div>
                <div class="modal-footer" style="border-top:1px solid rgba(0,0,0,.08)">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-orange" style="background:#10b981; border-color:#10b981;">Update Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>

