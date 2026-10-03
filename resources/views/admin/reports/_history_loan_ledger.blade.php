                <!-- 7. Employee Loan Ledger -->
                <div class="col-12 grid-margin stretch-card mb-3 report-card-item" data-category="history" data-keywords="loan ledger vale cash advance goods pdf">
                    <div class="card border-0 shadow-sm border-start border-4 border-primary">
                        <div class="card-header bg-white py-3 border-0 d-flex align-items-center justify-content-between cursor-pointer" data-bs-toggle="collapse" data-bs-target="#collapse-loan-ledger">
                            <div class="d-flex align-items-center me-2">
                                <div class="avatar-sm rounded bg-soft-primary text-primary d-flex align-items-center justify-content-center me-3 flex-shrink-0" style="width:40px; height:40px;">
                                    <i class="fe-credit-card font-18"></i>
                                </div>
                                <div>
                                    <h5 class="fw-bold text-dark font-15 mb-1">
                                        Employee Loan Ledger Report
                                        <span class="badge bg-soft-primary text-primary font-11 ms-2">Loan Ledger</span>
                                    </h5>
                                    <p class="text-muted font-12 mb-0">Generate individual employee loan ledger with payment history (PDF)</p>
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm btn-light border text-nowrap font-12" data-bs-toggle="collapse" data-bs-target="#collapse-loan-ledger">
                                <i class="fe-chevron-down me-1"></i> Expand Filters
                            </button>
                        </div>
                        <div id="collapse-loan-ledger" class="collapse">
                            <div class="card-body border-top bg-light-subtle pt-3">
                                <form action="{{ panelRoute('reports.loan-ledger') }}" method="POST" target="_blank">
                                    @csrf
                                    @if(isset($selectedCompanyId)) <input type="hidden" name="company_id" value="{{ $selectedCompanyId }}"> @endif
                                    <div class="row g-2 mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label font-12 fw-semibold">Employee <span class="text-danger">*</span></label>
                                            <x-employee-select name="employee_id" companyId="{{ $selectedCompanyId ?? null }}" :employees="$employees ?? null" required />
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label font-12 fw-semibold">Loan Type <span class="text-danger">*</span></label>
                                            <select name="loan_type" class="form-select form-select-sm" required>
                                                <option value="all">All Loans</option>
                                                <option value="vale">Vale/Cash Advance</option>
                                                <option value="goods">Goods Purchase</option>
                                                <option value="sss">SSS Loan</option>
                                                <option value="pagibig">Pag-IBIG Loan</option>
                                            </select>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-sm btn-primary w-100 fw-semibold">
                                        <i class="fe-file-text me-1"></i> Generate Loan Ledger PDF
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
