<script>
const COMPANY_STATS = {
    total: {{ $stats['total_loans'] }},
    active: {{ $stats['active_loans'] }},
    principal: {{ $stats['total_principal'] }},
    paid: {{ $stats['total_paid'] }},
    balance: {{ $stats['total_balance'] }}
};
const AJAX_URL = '{{ url(panelPrefix() . "/loan-monitor/employee") }}';
const CSRF     = '{{ csrf_token() }}';
const CAN_EDIT_LOAN = @json(\App\Models\MenuRoleAccess::hasModuleAccess(auth()->user(), 'loan_edit'));
const CAN_EDIT_PAYMENT = @json(\App\Models\MenuRoleAccess::hasModuleAccess(auth()->user(), 'loan_payment_edit'));

function selectEmployeeForLoans(empId) {
    if (window.jQuery && $('#employeeSelect').length) {
        $('#employeeSelect').val(empId).trigger('change');
    } else {
        const el = document.getElementById('employeeSelect');
        if (el) {
            el.value = empId;
            handleEmployeeSelectChange(empId);
        }
    }
}

function filterOutstandingLoans() {
    const q = (document.getElementById('loan_search_q')?.value || '').toLowerCase().trim();
    const type = (document.getElementById('loan_type_filter')?.value || '').toLowerCase();
    const status = (document.getElementById('loan_status_filter')?.value || 'active').toLowerCase();
    const fromDate = document.getElementById('loan_date_from')?.value || '';
    const toDate = document.getElementById('loan_date_to')?.value || '';
    const sortBy = document.getElementById('loan_sort_by')?.value || 'date_desc';

    const tbody = document.getElementById('outstanding_loans_tbody');
    if (!tbody) return;

    const rows = Array.from(tbody.querySelectorAll('.master-loan-row'));
    let visibleCount = 0;

    rows.forEach(row => {
        const empSearch = row.getAttribute('data-emp-name') || '';
        const loanType = row.getAttribute('data-loan-type') || '';
        const loanDate = row.getAttribute('data-loan-date') || '';
        const rowStatus = row.getAttribute('data-status') || '';

        const matchQ = !q || empSearch.includes(q);
        const matchType = !type || loanType.includes(type);
        const matchStatus = (status === 'all') || (status === rowStatus);
        
        let matchDate = true;
        if (fromDate && loanDate && loanDate < fromDate) matchDate = false;
        if (toDate && loanDate && loanDate > toDate) matchDate = false;

        if (matchQ && matchType && matchStatus && matchDate) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });

    // Sorting
    rows.sort((a, b) => {
        if (sortBy === 'emp_asc') {
            return (a.getAttribute('data-emp-sort') || '').localeCompare(b.getAttribute('data-emp-sort') || '');
        } else if (sortBy === 'emp_desc') {
            return (b.getAttribute('data-emp-sort') || '').localeCompare(a.getAttribute('data-emp-sort') || '');
        } else if (sortBy === 'date_asc') {
            return (a.getAttribute('data-loan-date') || '').localeCompare(b.getAttribute('data-loan-date') || '');
        } else if (sortBy === 'date_desc') {
            return (b.getAttribute('data-loan-date') || '').localeCompare(a.getAttribute('data-loan-date') || '');
        } else if (sortBy === 'balance_desc') {
            return parseFloat(b.getAttribute('data-balance') || 0) - parseFloat(a.getAttribute('data-balance') || 0);
        } else if (sortBy === 'balance_asc') {
            return parseFloat(a.getAttribute('data-balance') || 0) - parseFloat(b.getAttribute('data-balance') || 0);
        }
        return 0;
    });

    rows.forEach(row => tbody.appendChild(row));

    const badge = document.getElementById('active_loans_count_badge');
    if (badge) badge.textContent = `${visibleCount} Loans Shown`;
}

function handleEmployeeSelectChange(empId) {
    if (!empId) {
        window.location.reload();
        return;
    }
    loadEmployeeLoans(empId);
}

document.addEventListener('DOMContentLoaded', function () {
    if (window.jQuery) {
        $('#employeeSelect').on('change select2:select select2:clear', function () {
            const empId = $(this).val();
            handleEmployeeSelectChange(empId);
        });
    }

    const nativeEl = document.getElementById('employeeSelect');
    if (nativeEl) {
        nativeEl.addEventListener('change', function () {
            handleEmployeeSelectChange(this.value);
        });
    }
});

function loadEmployeeLoans(empId) {
    const container = document.getElementById('loans-container');
    container.innerHTML = `<div class="loader-wrap"><div class="spinner"></div><p>Loading loans...</p></div>`;

    fetch(`${AJAX_URL}/${empId}`, {
        headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) { container.innerHTML = `<div class="empty-state"><i class="mdi mdi-alert"></i><p>Error loading data</p></div>`; return; }
        renderLoans(data);
    })
    .catch(() => {
        container.innerHTML = `<div class="empty-state"><i class="mdi mdi-wifi-off"></i><p>Network error. Please try again.</p></div>`;
    });
}

function renderLoans(data) {
    const { employee, loans, summary } = data;
    let html = '';

    // Update Top Stat Cards dynamically
    document.getElementById('stat-total-loans').textContent = summary.total;
    document.getElementById('stat-active-loans').textContent = summary.active;
    document.getElementById('stat-total-principal').textContent = '₱' + fmt(summary.total_principal);
    document.getElementById('stat-total-paid').textContent = '₱' + fmt(summary.total_paid);
    document.getElementById('stat-total-balance').textContent = '₱' + fmt(summary.total_balance);

    // Dynamic Employee Section Header
    html += `
    <div style="margin-top: 10px; margin-bottom: 20px;">
        <h4 style="color: var(--dark); font-weight: 700; display: inline-flex; align-items: center; gap: 8px;">
            <i class="mdi mdi-account-circle" style="color: var(--accent); font-size: 1.35rem;"></i>
            ${employee}'s Loans
        </h4>
    </div>`;

    if (loans.length === 0) {
        html += `<div class="empty-state"><i class="mdi mdi-emoticon-happy-outline"></i><p>No loans found for this employee</p></div>`;
    } else {
        loans.forEach(loan => { html += renderLoanCard(loan); });
    }

    document.getElementById('loans-container').innerHTML = html;

    // Animate progress bars
    setTimeout(() => {
        document.querySelectorAll('.prog-bar-fill').forEach(bar => {
            bar.style.width = bar.dataset.width + '%';
        });
    }, 50);
}

function renderLoanCard(loan) {
    const badgeClass  = getBadgeClass(loan.loan_type_code);
    const statusClass = 'status-' + loan.status;
    const pct         = loan.progress_percent;
    const isComplete  = loan.status === 'completed' || loan.status === 'paid' || pct >= 100;
    const editBtnHtml = CAN_EDIT_LOAN
        ? `<button class="btn-edit-loan" onclick="openEditLoanModal(${loan.id}, ${loan.principal_amount})">
                <i class="mdi mdi-pencil me-1"></i>Edit
            </button>`
        : '';

    return `
    <div class="loan-card">
        <div class="loan-actions">
            <a href="${loan.print_url || '#'}" target="_blank" class="btn-edit-loan me-1" style="text-decoration:none;">
                <i class="mdi mdi-printer me-1"></i>Print Form
            </a>
            <a href="${loan.excel_url || '#'}" class="btn-edit-loan me-1" style="text-decoration:none;">
                <i class="mdi mdi-file-excel me-1"></i>Excel
            </a>
            <button class="btn-edit-loan me-1" onclick="viewHistory('${loan.type}', ${loan.id}, '${loan.loan_type}')">
                <i class="mdi mdi-history me-1"></i>History
            </button>
            ${editBtnHtml}
        </div>

        <div class="d-flex align-items-center gap-2 flex-wrap mb-2">
            <span class="type-badge ${badgeClass}">
                <i class="mdi ${getLoanIcon(loan.loan_type_code)}"></i> ${loan.loan_type}
            </span>
            <span class="${statusClass}">${loan.status.charAt(0).toUpperCase() + loan.status.slice(1)}</span>
        </div>

        <div class="loan-title">${loan.description || loan.loan_type}</div>
        ${loan.loan_number && loan.loan_number !== '—' ? `<div class="loan-number">Ref: ${loan.loan_number}</div>` : ''}

        <div class="loan-amounts">
            <div class="amount-item">
                <div class="amount-label">Loan Amount</div>
                <div class="amount-value">₱${fmt(loan.principal_amount)}</div>
            </div>
            <div class="amount-item">
                <div class="amount-label">Amount Paid</div>
                <div class="amount-value paid">₱${fmt(loan.amount_paid)}</div>
            </div>
            <div class="amount-item">
                <div class="amount-label">Balance</div>
                <div class="amount-value balance">₱${fmt(loan.balance)}</div>
            </div>
            <div class="amount-item">
                <div class="amount-label">Per Cutoff</div>
                <div class="amount-value">₱${fmt(loan.installment_amount)}</div>
            </div>
        </div>

        <div class="progress-wrap">
            <div class="prog-label">
                <span>Repayment Progress</span>
                <strong>${pct}%</strong>
            </div>
            <div class="prog-bar-bg">
                <div class="prog-bar-fill ${isComplete ? 'complete' : ''}" data-width="${Math.min(pct,100)}" style="width:0%"></div>
            </div>
        </div>

        <div class="installments-info">
            <div class="inst-item">
                <div class="inst-val">${loan.total_installments || '—'}</div>
                <div class="inst-lbl">Total Installments</div>
            </div>
            <div class="inst-item">
                <div class="inst-val" style="color:#10b981">${loan.paid_installments}</div>
                <div class="inst-lbl">Paid Inst.</div>
            </div>
            <div class="inst-item">
                <div class="inst-val" style="color:#f59e0b">${loan.remaining_installments}</div>
                <div class="inst-lbl">Remaining Inst.</div>
            </div>
            <div class="inst-item">
                <div class="inst-val">${loan.start_date}</div>
                <div class="inst-lbl">Start Date</div>
            </div>
            ${loan.end_date ? `
            <div class="inst-item">
                <div class="inst-val">${loan.end_date}</div>
                <div class="inst-lbl">End Date</div>
            </div>` : ''}
        </div>
    </div>`;
}

function openEditLoanModal(id, principalAmount) {
    const basePath = window.location.pathname.replace(/\/$/, '');
    const form = document.getElementById('editLoanForm');
    form.action = `${basePath}/loan/${id}/update`;
    document.getElementById('editLoanPrincipal').value = principalAmount;
    const modal = new bootstrap.Modal(document.getElementById('editLoanModal'));
    modal.show();
}

function openEditPaymentModal(id, amountPaid, paymentDate, maxAllowed) {
    const historyEl = document.getElementById('historyModal');
    const historyModal = bootstrap.Modal.getInstance(historyEl);
    if (historyModal) {
        historyModal.hide();
    }

    const basePath = window.location.pathname.replace(/\/$/, '');
    const form = document.getElementById('editPaymentForm');
    form.action = `${basePath}/payment/${id}/update`;
    const amtInput = document.getElementById('editPaymentAmount');
    amtInput.value = amountPaid;
    if (maxAllowed !== undefined && maxAllowed !== null && maxAllowed !== '') {
        amtInput.max = maxAllowed;
    }
    document.getElementById('editPaymentDate').value = paymentDate;
    const modal = new bootstrap.Modal(document.getElementById('editPaymentModal'));
    modal.show();
}

function viewHistory(type, id, title) {
    const modalTitle = document.getElementById('modalTitle');
    const modalBody  = document.getElementById('modalBody');

    modalTitle.textContent = `${title} - Payment History`;
    modalBody.innerHTML = `<div class="loader-wrap"><div class="spinner"></div><p>Loading history...</p></div>`;

    const myModal = new bootstrap.Modal(document.getElementById('historyModal'));
    myModal.show();

    const basePath = window.location.pathname.replace(/\/$/, '');
    fetch(`${basePath}/${type}/${id}/history`, {
        headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success || data.payments.length === 0) {
            modalBody.innerHTML = `<p class="text-center text-muted py-4">No payments recorded yet for this loan.</p>`;
            return;
        }

        let html = `
        <div class="table-responsive">
            <table class="table table-sm table-striped table-bordered mb-0">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Amount Paid</th>
                        <th>Balance After</th>
                        <th>Notes</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>`;

        data.payments.forEach(p => {
            let actionHtml = '';
            if (p.is_manual && CAN_EDIT_PAYMENT) {
                actionHtml = `<button class="btn btn-sm btn-outline-warning" onclick="openEditPaymentModal(${p.id}, ${p.amount_paid}, '${p.raw_date}', ${p.max_allowed})"><i class="mdi mdi-pencil me-1"></i>Edit</button>`;
            } else if (p.is_manual && !CAN_EDIT_PAYMENT) {
                actionHtml = `<span class="badge bg-soft-secondary text-secondary" title="Manual payment editing is disabled by permissions"><i class="mdi mdi-lock-outline me-1"></i>Locked</span>`;
            } else {
                actionHtml = `<span class="badge bg-soft-secondary text-secondary" title="Payroll-generated payment cannot be edited"><i class="mdi mdi-lock-outline me-1"></i>Locked</span>`;
            }

            html += `
            <tr>
                <td>${p.date}${p.payroll_period ? ` <span class="badge bg-soft-info text-info font-11 ms-1">${p.payroll_period}</span>` : ''}</td>
                <td class="text-success font-weight-bold">₱${fmt(p.amount_paid)}</td>
                <td class="text-warning">₱${fmt(p.balance_after)}</td>
                <td>${p.notes}</td>
                <td>${actionHtml}</td>
            </tr>`;
        });

        html += `</tbody></table></div>`;
        modalBody.innerHTML = html;
    })
    .catch(() => {
        modalBody.innerHTML = `<p class="text-center text-danger py-4">Error loading history.</p>`;
    });
}

function getBadgeClass(code) {
    const map = { vale:'badge-vale', sss:'badge-sss', pagibig:'badge-pagibig', goods:'badge-goods' };
    return map[code] || 'badge-other';
}
function getLoanIcon(code) {
    const map = { vale:'mdi-cash', sss:'mdi-shield-account', pagibig:'mdi-home-city', goods:'mdi-cart' };
    return map[code] || 'mdi-bank';
}
function fmt(n) { return Number(n).toLocaleString('en-PH', { minimumFractionDigits:2, maximumFractionDigits:2 }); }

function updateAddLoanPreview() {
    const pInput = document.getElementById('add_principal_amount');
    const tInput = document.getElementById('add_terms_months');
    const rInput = document.getElementById('add_monthly_interest_rate');
    if (!pInput || !tInput || !rInput) return;

    const principal = parseFloat(pInput.value) || 0;
    const terms = Math.max(1, parseInt(tInput.value) || 1);
    const rate = parseFloat(rInput.value) || 0;

    const interest = Math.round((principal * (rate / 100) * terms) * 100) / 100;
    const total = Math.round((principal + interest) * 100) / 100;
    const cutoff = Math.round((total / (terms * 2)) * 100) / 100;

    document.getElementById('add_preview_interest').textContent = '₱' + fmt(interest);
    document.getElementById('add_preview_total').textContent = '₱' + fmt(total);
    document.getElementById('add_preview_cutoff').textContent = '₱' + fmt(cutoff);
}

['add_principal_amount', 'add_terms_months', 'add_monthly_interest_rate'].forEach(id => {
    const el = document.getElementById(id);
    if (el) {
        el.addEventListener('input', updateAddLoanPreview);
    }
});

@if($errors->addLoan->any())
document.addEventListener('DOMContentLoaded', function() {
    updateAddLoanPreview();
    const modalEl = document.getElementById('addLoanModal');
    if (modalEl) {
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            new bootstrap.Modal(modalEl).show();
        } else if (typeof $ !== 'undefined' && $.fn.modal) {
            $('#addLoanModal').modal('show');
        }
    }
});
@endif
</script>
