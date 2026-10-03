<style>
/* ── Variables ─────────────────────────── */
:root {
    --accent:   #f06115;
    --accent2:  #e04f00;
    --dark:     #212529;
    --card-bg:  #ffffff;
    --card2:    #f8f9fa;
    --border:   rgba(0,0,0,.08);
    --text:     #333333;
    --muted:    #6c757d;
}

/* ── Page Header ───────────────────────── */
.lm-header {
    background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 24px 28px;
    margin-bottom: 24px;
    position: relative;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0,0,0,0.03);
}
.lm-header::before {
    content: '';
    position: absolute;
    top: -40px; right: -40px;
    width: 200px; height: 200px;
    background: radial-gradient(circle, rgba(240,97,21,.08) 0%, transparent 70%);
    pointer-events: none;
}
.lm-header h2 {
    color: var(--dark);
    font-weight: 700;
    font-size: 1.6rem;
    margin: 0;
}
.lm-header p { color: var(--muted); margin: 4px 0 0; font-size:.9rem; }

/* ── Stat Cards ────────────────────────── */
.stat-card {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 20px 24px;
    position: relative;
    overflow: hidden;
    transition: transform .2s, box-shadow .2s;
    box-shadow: 0 4px 10px rgba(0,0,0,0.02);
}
.stat-card:hover { transform: translateY(-3px); box-shadow: 0 8px 30px rgba(0,0,0,.08); }
.stat-card .stat-icon {
    width: 46px; height: 46px;
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.3rem;
    margin-bottom: 12px;
}
.stat-card .stat-value { font-size: 1.5rem; font-weight: 700; color: var(--dark); }
.stat-card .stat-label { color: var(--muted); font-size: .8rem; margin-top: 2px; }
.icon-orange { background: rgba(240,97,21,.1); color: #f06115; }
.icon-green  { background: rgba(16,185,129,.1); color: #10b981; }
.icon-blue   { background: rgba(59,130,246,.1); color: #3b82f6; }
.icon-red    { background: rgba(239,68,68,.1);  color: #ef4444; }
.icon-purple { background: rgba(139,92,246,.1); color: #8b5cf6; }

/* ── Employee Selector ─────────────────── */
.emp-select-card {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 24px;
    margin-bottom: 24px;
    box-shadow: 0 4px 10px rgba(0,0,0,0.02);
}
.emp-select-card label { color: var(--dark); font-size:.85rem; font-weight:600; margin-bottom:8px; display:block; }
.emp-select {
    background: var(--card-bg);
    border: 1px solid rgba(0,0,0,.15);
    color: var(--dark);
    border-radius: 10px;
    padding: 12px 16px;
    font-size: 1rem;
    width: 100%;
    transition: border-color .2s, box-shadow .2s;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath fill='%236c757d' d='M1 1l5 5 5-5'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 16px center;
    padding-right: 40px;
}
.emp-select:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(240,97,21,.15); outline: none; }
.emp-select option { background: #ffffff; color: var(--dark); }

/* ── Loan Cards ────────────────────────── */
#loans-container { transition: opacity .3s; }

.loan-card {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 20px 22px;
    margin-bottom: 16px;
    position: relative;
    overflow: hidden;
    transition: border-color .2s, box-shadow .2s;
    box-shadow: 0 4px 10px rgba(0,0,0,0.02);
}
.loan-card:hover { border-color: rgba(240,97,21,.3); box-shadow: 0 6px 20px rgba(0,0,0,.05); }
.loan-card .type-badge {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: .72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .5px;
    margin-bottom: 12px;
}
.badge-vale    { background: rgba(240,97,21,.1); color: #f06115; }
.badge-sss     { background: rgba(59,130,246,.1); color: #3b82f6; }
.badge-pagibig { background: rgba(139,92,246,.1); color: #8b5cf6; }
.badge-goods   { background: rgba(16,185,129,.1); color: #10b981; }
.badge-other   { background: rgba(108,117,125,.1); color: #6c757d; }

.loan-title { color: var(--dark); font-weight: 600; font-size: 1rem; }
.loan-number { color: var(--muted); font-size: .78rem; }

.loan-amounts { display: flex; gap: 24px; flex-wrap: wrap; margin: 14px 0; }
.amount-item .amount-label { color: var(--muted); font-size: .72rem; font-weight: 600; text-transform: uppercase; margin-bottom: 2px; }
.amount-item .amount-value { color: var(--dark); font-size: 1rem; font-weight: 700; }
.amount-item .amount-value.paid   { color: #10b981; }
.amount-item .amount-value.balance { color: #f59e0b; }

/* Progress Bar */
.progress-wrap { margin: 10px 0; }
.progress-wrap .prog-label { display: flex; justify-content: space-between; margin-bottom: 6px; }
.progress-wrap .prog-label span { color: var(--muted); font-size: .75rem; }
.progress-wrap .prog-label strong { color: var(--dark); font-size: .75rem; }
.prog-bar-bg {
    background: rgba(0,0,0,.05);
    border-radius: 8px;
    height: 8px;
    overflow: hidden;
}
.prog-bar-fill {
    height: 100%;
    border-radius: 8px;
    background: linear-gradient(90deg, #f06115, #f59e0b);
    transition: width .8s cubic-bezier(.4,0,.2,1);
}
.prog-bar-fill.complete { background: linear-gradient(90deg, #10b981, #34d399); }

/* Status Badges */
.status-active    { background: rgba(16,185,129,.15); color: #10b981; padding: 3px 10px; border-radius: 20px; font-size: .72rem; font-weight:700; }
.status-completed { background: rgba(59,130,246,.15); color: #3b82f6; padding: 3px 10px; border-radius: 20px; font-size: .72rem; font-weight:700; }
.status-cancelled { background: rgba(239,68,68,.15);  color: #ef4444; padding: 3px 10px; border-radius: 20px; font-size: .72rem; font-weight:700; }
.status-pending   { background: rgba(245,158,11,.15); color: #f59e0b; padding: 3px 10px; border-radius: 20px; font-size: .72rem; font-weight:700; }

.installments-info {
    background: rgba(0,0,0,.02);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 10px 14px;
    display: flex; gap: 20px; flex-wrap: wrap;
    margin-top: 10px;
}
.inst-item { text-align: center; }
.inst-item .inst-val { color: var(--dark); font-weight: 700; font-size: .95rem; }
.inst-item .inst-lbl { color: var(--muted); font-size: .68rem; }

.loan-actions { position: absolute; top: 16px; right: 16px; display: flex; gap: 6px; }
.btn-edit-loan {
    background: rgba(240,97,21,.1);
    color: #f06115;
    border: 1px solid rgba(240,97,21,.2);
    border-radius: 8px;
    padding: 5px 12px;
    font-size: .75rem;
    font-weight: 600;
    text-decoration: none;
    transition: all .2s;
}
.btn-edit-loan:hover { background: #f06115; color: #fff; }

/* ── Employee Summary Strip ───────────── */
.emp-summary {
    background: linear-gradient(135deg, rgba(240,97,21,.08), rgba(240,97,21,.03));
    border: 1px solid rgba(240,97,21,.15);
    border-radius: 12px;
    padding: 16px 20px;
    margin-bottom: 20px;
    display: flex; gap: 24px; flex-wrap: wrap; align-items: center;
}
.emp-summary .emp-name { color: var(--dark); font-weight: 700; font-size: 1.05rem; margin-right: auto; }
.emp-summary .s-item .s-val { color: var(--dark); font-weight: 700; font-size: .9rem; }
.emp-summary .s-item .s-lbl { color: var(--muted); font-size: .7rem; }

/* ── Loader ─────────────────────────────── */
.loader-wrap {
    text-align: center;
    padding: 50px;
    color: var(--muted);
}
.spinner {
    width: 40px; height: 40px;
    border: 3px solid rgba(0,0,0,.08);
    border-top-color: var(--accent);
    border-radius: 50%;
    animation: spin .7s linear infinite;
    margin: 0 auto 14px;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ── Empty State ────────────────────────── */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--muted);
}
.empty-state i { font-size: 3rem; opacity: .3; display: block; margin-bottom: 16px; }

/* ── Buttons ─────────────────────────────── */
.btn-orange {
    background: linear-gradient(135deg, #f06115, #e04f00);
    color: #fff;
    border: none;
    border-radius: 10px;
    padding: 10px 22px;
    font-weight: 600;
    font-size: .9rem;
    transition: all .2s;
    text-decoration: none;
    display: inline-flex; align-items: center; gap: 6px;
}
.btn-orange:hover { background: linear-gradient(135deg, #e04f00, #c43f00); color: #fff; transform: translateY(-1px); }
.btn-ghost {
    background: rgba(0,0,0,.03);
    color: var(--dark);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 10px 18px;
    font-size: .9rem;
    text-decoration: none;
    display: inline-flex; align-items: center; gap: 6px;
    transition: all .2s;
}
.btn-ghost:hover { background: rgba(0,0,0,.06); color: var(--dark); }
</style>
<div class="container-fluid px-3 py-3">

    @if(session('statusSuccess'))
        <div class="alert alert-success alert-dismissible fade show d-flex align-items-center justify-content-between" role="alert">
            <div>
                {{ session('statusSuccess') }}
                @if(session('loanPrintUrl'))
                    <a href="{{ session('loanPrintUrl') }}" target="_blank" class="btn btn-sm btn-success ms-2 text-white font-weight-bold">
                        <i class="mdi mdi-printer me-1"></i> Print Loan Form
                    </a>
                @endif
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if(session('statusDanger'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('statusDanger') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- ── Header ── --}}
    <div class="lm-header">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div>
                <h2><i class="mdi mdi-chart-timeline-variant me-2" style="color:#f06115"></i>Loan Monitor</h2>
                <p>Select an employee to view all active and past loans (Vale Loans, Government Loans, Goods Purchase)</p>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-orange" data-bs-toggle="modal" data-bs-target="#addLoanModal">
                    <i class="mdi mdi-plus-circle me-1"></i> Add New Loan
                </button>
                @if(\App\Models\MenuRoleAccess::hasModuleAccess(auth()->user(), 'loan_record_payment'))
                <button type="button" class="btn btn-orange" style="background:#10b981; border-color:#10b981;" data-bs-toggle="modal" data-bs-target="#recordPaymentModal">
                    <i class="mdi mdi-cash-register me-1"></i> Record Payment
                </button>
                @endif
            </div>
        </div>
    </div>

    {{-- ── Company Stats ── --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3 col-lg">
            <div class="stat-card">
                <div class="stat-icon icon-orange"><i class="mdi mdi-bank"></i></div>
                <div class="stat-value" id="stat-total-loans">{{ $stats['total_loans'] }}</div>
                <div class="stat-label">Total Loans</div>
            </div>
        </div>
        <div class="col-6 col-md-3 col-lg">
            <div class="stat-card">
                <div class="stat-icon icon-green"><i class="mdi mdi-check-circle"></i></div>
                <div class="stat-value" id="stat-active-loans">{{ $stats['active_loans'] }}</div>
                <div class="stat-label">Active Loans</div>
            </div>
        </div>
        <div class="col-6 col-md-3 col-lg">
            <div class="stat-card">
                <div class="stat-icon icon-blue"><i class="mdi mdi-cash-multiple"></i></div>
                <div class="stat-value" id="stat-total-principal">₱{{ number_format($stats['total_principal'], 2) }}</div>
                <div class="stat-label">Total Loan Amount</div>
            </div>
        </div>
        <div class="col-6 col-md-3 col-lg">
            <div class="stat-card">
                <div class="stat-icon icon-purple"><i class="mdi mdi-cash-check"></i></div>
                <div class="stat-value" id="stat-total-paid">₱{{ number_format($stats['total_paid'], 2) }}</div>
                <div class="stat-label">Total Paid</div>
            </div>
        </div>
        <div class="col-6 col-md-3 col-lg">
            <div class="stat-card">
                <div class="stat-icon icon-red"><i class="mdi mdi-alert-circle"></i></div>
                <div class="stat-value" id="stat-total-balance">₱{{ number_format($stats['total_balance'], 2) }}</div>
                <div class="stat-label">Remaining Balance</div>
            </div>
        </div>
    </div>

    {{-- ── Employee Selector ── --}}
    <div class="emp-select-card">
        <label><i class="mdi mdi-account-search me-1"></i> Select Employee</label>
        <x-employee-select id="employeeSelect" name="employee_id" placeholder="— Choose an employee —" companyId="{{ $selectedCompanyId ?? null }}" :employees="$employees ?? null" />
    </div>

    {{-- ── Loans Container ── --}}
    <div id="loans-container">
        <!-- Master Outstanding Loans Overview Card -->
        <div class="card border-0 shadow-sm mb-4" style="border: 1px solid #e9ecef !important; border-radius: 14px;">
            <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center flex-wrap gap-2" style="border-bottom: 2px solid #f06115 !important;">
                <div>
                    <h5 class="mb-0 font-weight-bold text-dark"><i class="mdi mdi-cash-register text-primary me-2" style="color: #f06115 !important;"></i> Outstanding & Active Loans Overview</h5>
                    <small class="text-muted">Company-wide loan ledger with filter, search, and date range monitoring</small>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge" style="background-color: #f06115; color: white; font-weight: bold;" id="active_loans_count_badge">{{ $loans->where('current_balance', '>', 0)->count() }} Active Loans</span>
                </div>
            </div>

            <div class="card-body p-3 bg-light border-bottom">
                <!-- Multi-Criteria Filter Bar -->
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label font-weight-semibold text-dark small mb-1">Search Employee / Code</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white border-end-0"><i class="mdi mdi-magnify"></i></span>
                            <input type="text" id="loan_search_q" class="form-control border-start-0" placeholder="Type employee name or code..." onkeyup="filterOutstandingLoans()">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label font-weight-semibold text-dark small mb-1">Loan Type</label>
                        <select id="loan_type_filter" class="form-select form-select-sm font-weight-bold" onchange="filterOutstandingLoans()">
                            <option value="">All Loan Types</option>
                            @foreach($loanTypes as $lt)
                                <option value="{{ strtolower($lt->name) }}">{{ $lt->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label font-weight-semibold text-dark small mb-1">Status</label>
                        <select id="loan_status_filter" class="form-select form-select-sm font-weight-bold" onchange="filterOutstandingLoans()">
                            <option value="active" selected>Active Loans Only</option>
                            <option value="completed">Completed / Paid</option>
                            <option value="all">All Loans</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label font-weight-semibold text-dark small mb-1">Loan Date Range</label>
                        <div class="d-flex align-items-center gap-1">
                            <input type="date" id="loan_date_from" class="form-control form-control-sm" onchange="filterOutstandingLoans()">
                            <span class="small text-muted">-</span>
                            <input type="date" id="loan_date_to" class="form-control form-control-sm" onchange="filterOutstandingLoans()">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label font-weight-semibold text-dark small mb-1">Sort By</label>
                        <select id="loan_sort_by" class="form-select form-select-sm font-weight-bold" onchange="filterOutstandingLoans()">
                            <option value="emp_asc">Employee (A-Z)</option>
                            <option value="emp_desc">Employee (Z-A)</option>
                            <option value="date_desc" selected>Date (Newest First)</option>
                            <option value="date_asc">Date (Oldest First)</option>
                            <option value="balance_desc">Balance (Highest First)</option>
                            <option value="balance_asc">Balance (Lowest First)</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0" id="outstanding_loans_table">
                    <thead class="table-light">
                        <tr>
                            <th>Employee Name</th>
                            <th>Loan Type</th>
                            <th>Loan Date</th>
                            <th class="text-end">Principal Amount (₱)</th>
                            <th class="text-end">Total Paid (₱)</th>
                            <th class="text-end">Outstanding Balance (₱)</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody id="outstanding_loans_tbody">
                        @forelse($loans->sortByDesc('start_date') as $l)
                            @php
                                $empName = $l->employee ? $l->employee->full_name : 'Unknown';
                                $empCode = $l->employee->employee_code ?? '';
                                $typeTitle = $l->loanType->name ?? 'Company Loan';
                                $startDate = $l->start_date ? \Carbon\Carbon::parse($l->start_date)->format('Y-m-d') : '';
                                $formattedDate = $l->start_date ? \Carbon\Carbon::parse($l->start_date)->format('M d, Y') : '—';
                                $paid = $l->payments->sum('amount_paid');
                                $bal = (float) $l->current_balance;
                                $status = $bal > 0 ? 'active' : 'completed';
                            @endphp
                            <tr class="master-loan-row"
                                data-emp-id="{{ $l->employee_id }}"
                                data-emp-name="{{ strtolower($empName . ' ' . $empCode) }}"
                                data-emp-sort="{{ strtolower($empName) }}"
                                data-loan-type="{{ strtolower($typeTitle) }}"
                                data-loan-date="{{ $startDate }}"
                                data-balance="{{ $bal }}"
                                data-status="{{ $status }}"
                                data-principal="{{ (float)$l->principal_amount }}">
                                <td>
                                    <span class="font-weight-bold text-dark d-block">{{ $empName }}</span>
                                    <small class="badge bg-light text-dark border">{{ $empCode }}</small>
                                </td>
                                <td>
                                    <span class="badge badge-vale" style="font-size: 11px;">{{ $typeTitle }}</span>
                                </td>
                                <td>{{ $formattedDate }}</td>
                                <td class="text-end font-weight-bold">₱{{ number_format($l->principal_amount, 2) }}</td>
                                <td class="text-end text-success font-weight-bold">₱{{ number_format($paid, 2) }}</td>
                                <td class="text-end font-weight-bold" style="color: #f06115;">₱{{ number_format($bal, 2) }}</td>
                                <td class="text-center">
                                    @if($bal > 0)
                                        <span class="badge bg-success small">Active</span>
                                    @else
                                        <span class="badge bg-info small">Completed</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 font-weight-bold" onclick="selectEmployeeForLoans({{ $l->employee_id }})" title="View Employee Loans">
                                        <i class="mdi mdi-eye me-1"></i> View
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">No loans recorded yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
