<script>
let currentActiveEmployeeId = null;
let globalIncomeTypes = [];
let globalDeductionTypes = [];

document.addEventListener('DOMContentLoaded', function() {
    const firstEmpBtn = document.querySelector('#employee_directory_list .employee-item');
    if (firstEmpBtn) {
        const empId = firstEmpBtn.getAttribute('data-emp-id');
        loadEmployeeQuickDetails(empId, firstEmpBtn);
    }
});

function filterEmployeeDirectory() {
    const searchInput = document.getElementById('employee_quick_search');
    const deptSelect = document.getElementById('filter_department_id');
    const query = (searchInput?.value || '').toLowerCase().trim();
    const selectedDeptId = deptSelect?.value || '';
    const items = document.querySelectorAll('#employee_directory_list .employee-item');
    let visibleCount = 0;

    items.forEach(item => {
        const term = (item.getAttribute('data-search-term') || '').toLowerCase();
        const empDeptId = item.getAttribute('data-dept-id') || '';

        const matchesQuery = !query || term.includes(query);
        const matchesDept = !selectedDeptId || String(empDeptId) === String(selectedDeptId);

        if (matchesQuery && matchesDept) {
            item.style.display = 'block';
            visibleCount++;
        } else {
            item.style.display = 'none';
        }
    });

    const badge = document.getElementById('emp_count_badge');
    if (badge) badge.textContent = visibleCount;
}

function updatePeriodFromDates() {
    const fromVal = document.getElementById('period_from_date')?.value;
    const toVal = document.getElementById('period_to_date')?.value;
    if (!fromVal || !toVal) return;

    const select = document.getElementById('pay_period_selector');
    if (!select) return;

    for (let i = 0; i < select.options.length; i++) {
        const opt = select.options[i];
        if (opt.getAttribute('data-start') === fromVal && opt.getAttribute('data-end') === toVal) {
            select.selectedIndex = i;
            return;
        }
    }
}

function applySelectedPeriod(selectElement) {
    const selectedOption = selectElement.options[selectElement.selectedIndex];
    if (selectedOption && selectedOption.value) {
        const start = selectedOption.getAttribute('data-start');
        const end = selectedOption.getAttribute('data-end');
        if (start) document.getElementById('period_from_date').value = start;
        if (end) document.getElementById('period_to_date').value = end;
    }
}

function loadEmployeeQuickDetails(empId, elementBtn) {
    currentActiveEmployeeId = empId;

    document.querySelectorAll('#employee_directory_list .employee-item').forEach(btn => btn.classList.remove('active'));
    if (elementBtn) elementBtn.classList.add('active');

    document.getElementById('pane_content_area').style.display = 'none';
    document.getElementById('pane_loading_spinner').style.display = 'block';

    const routeUrl = "{{ url('/' . panelPrefix() . '/payroll-employees/company-master') }}/" + empId + "/details";

    fetch(routeUrl, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
        }
    })
    .then(res => res.json())
    .then(data => {
        document.getElementById('pane_loading_spinner').style.display = 'none';
        document.getElementById('pane_content_area').style.display = 'block';

        if (data.status === 'success') {
            const emp = data.employee;
            globalIncomeTypes = data.income_types || [];
            globalDeductionTypes = data.deduction_types || [];

            document.getElementById('active_emp_name').textContent = emp.full_name;
            document.getElementById('active_emp_subtitle').textContent = `Code: ${emp.employee_code} | Position: ${emp.position} | Department: ${emp.department}`;

            const hasDraft = data.has_payroll_draft;
            populateIncomeGrid(data.incomes || [], hasDraft);
            populateDeductionGrid(data.deductions || [], hasDraft);
            recalculateMasterSummary();
        }
    })
    .catch(err => {
        document.getElementById('pane_loading_spinner').style.display = 'none';
        document.getElementById('pane_content_area').style.display = 'block';
        alert('Failed to load employee details: ' + err.message);
    });
}

function populateIncomeGrid(incomes, hasDraft = true) {
    const tbody = document.getElementById('income_table_body');
    if (!tbody) return;
    tbody.innerHTML = '';

    if (incomes.length === 0) {
        if (!hasDraft) {
            tbody.innerHTML = `<tr><td colspan="5" class="text-center py-3"><span class="badge bg-warning text-dark font-weight-bold p-2"><i class="mdi mdi-alert-circle me-1"></i> Payroll draft not created</span></td></tr>`;
        } else {
            tbody.innerHTML = `<tr><td colspan="5" class="text-center text-muted py-3">No income records available.</td></tr>`;
        }
        return;
    }

    incomes.forEach(inc => {
        addIncomeRow(inc.income_type_id, 1, inc.amount);
    });
}

function addIncomeRow(typeId = '', hours = 1, amount = 0) {
    const tbody = document.getElementById('income_table_body');
    if (!tbody) return;
    if (!tbody.querySelector('.inc-type-select')) {
        tbody.innerHTML = '';
    }
    const tr = document.createElement('tr');

    let typeOptions = '<option value="">Select Income Type</option>';
    globalIncomeTypes.forEach(t => {
        const selected = (t.id == typeId) ? 'selected' : '';
        typeOptions += `<option value="${t.id}" ${selected}>${t.name} (${t.code})</option>`;
    });

    tr.innerHTML = `
        <td>
            <select class="form-select form-select-sm inc-type-select" onchange="recalculateMasterSummary()">
                ${typeOptions}
            </select>
        </td>
        <td>
            <input type="number" step="0.5" class="form-control form-control-sm inc-hours-input" value="${hours}" oninput="recalculateMasterSummary()">
        </td>
        <td>
            <input type="number" step="0.01" class="form-control form-control-sm inc-rate-input" value="${amount}" oninput="recalculateMasterSummary()">
        </td>
        <td>
            <input type="text" class="form-control form-control-sm inc-total-input" readonly value="${(hours * amount).toFixed(2)}">
        </td>
        <td>
            <button type="button" class="btn btn-link text-danger p-0" onclick="this.closest('tr').remove(); recalculateMasterSummary();">
                <i class="mdi mdi-close-circle font-18"></i>
            </button>
        </td>
    `;
    tbody.appendChild(tr);
    recalculateMasterSummary();
}

function deleteUnusedIncomes() {
    const tbody = document.getElementById('income_table_body');
    if (!tbody) return;
    const rows = tbody.querySelectorAll('tr');
    rows.forEach(row => {
        const amount = parseFloat(row.querySelector('.inc-rate-input')?.value || 0);
        if (amount === 0) row.remove();
    });
    if (tbody.querySelectorAll('tr').length === 0) addIncomeRow();
    recalculateMasterSummary();
}

function populateDeductionGrid(deductions, hasDraft = true) {
    const tbody = document.getElementById('deduction_table_body');
    if (!tbody) return;
    tbody.innerHTML = '';

    if (deductions.length === 0) {
        if (!hasDraft) {
            tbody.innerHTML = `<tr><td colspan="3" class="text-center py-3"><span class="badge bg-warning text-dark font-weight-bold p-2"><i class="mdi mdi-alert-circle me-1"></i> Payroll draft not created</span></td></tr>`;
        } else {
            tbody.innerHTML = `<tr><td colspan="3" class="text-center text-muted py-3">No deduction records available.</td></tr>`;
        }
        return;
    }

    deductions.forEach(dec => {
        addDeductionRow(dec.deduction_type_id, dec.amount);
    });
}

function addDeductionRow(typeId = '', amount = 0) {
    const tbody = document.getElementById('deduction_table_body');
    if (!tbody) return;
    if (!tbody.querySelector('.dec-type-select')) {
        tbody.innerHTML = '';
    }
    const tr = document.createElement('tr');

    let typeOptions = '<option value="">Select Deduction Type</option>';
    globalDeductionTypes.forEach(t => {
        const selected = (t.id == typeId) ? 'selected' : '';
        typeOptions += `<option value="${t.id}" ${selected}>${t.name} (${t.code})</option>`;
    });

    tr.innerHTML = `
        <td>
            <select class="form-select form-select-sm dec-type-select" onchange="recalculateMasterSummary()">
                ${typeOptions}
            </select>
        </td>
        <td>
            <input type="number" step="0.01" class="form-control form-control-sm dec-amount-input" value="${amount}" oninput="recalculateMasterSummary()">
        </td>
        <td>
            <button type="button" class="btn btn-link text-danger p-0" onclick="this.closest('tr').remove(); recalculateMasterSummary();">
                <i class="mdi mdi-close-circle font-18"></i>
            </button>
        </td>
    `;
    tbody.appendChild(tr);
    recalculateMasterSummary();
}

function deleteUnusedDeductions() {
    const tbody = document.getElementById('deduction_table_body');
    if (!tbody) return;
    const rows = tbody.querySelectorAll('tr');
    rows.forEach(row => {
        const amount = parseFloat(row.querySelector('.dec-amount-input')?.value || 0);
        if (amount === 0) row.remove();
    });
    if (tbody.querySelectorAll('tr').length === 0) addDeductionRow();
    recalculateMasterSummary();
}

function recalculateMasterSummary() {
    const isHr = {{ (auth()->user()->role === 'hr' || auth()->user()->role_id === \App\Models\Role::HR) ? 'true' : 'false' }};
    let grossPay = 0;

    document.querySelectorAll('#income_table_body tr').forEach(row => {
        const hrs = parseFloat(row.querySelector('.inc-hours-input')?.value || 0);
        const rate = parseFloat(row.querySelector('.inc-rate-input')?.value || 0);
        const total = hrs * rate;
        const totalInput = row.querySelector('.inc-total-input');
        if (totalInput) totalInput.value = total.toFixed(2);
        grossPay += total;
    });

    let totalDeductions = 0;
    document.querySelectorAll('#deduction_table_body tr').forEach(row => {
        const amt = parseFloat(row.querySelector('.dec-amount-input')?.value || 0);
        totalDeductions += amt;
    });

    const netPay = grossPay - totalDeductions;

    if (document.getElementById('badge_gross_pay')) document.getElementById('badge_gross_pay').textContent = '₱ ' + grossPay.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
    if (document.getElementById('badge_net_pay')) document.getElementById('badge_net_pay').textContent = '₱ ' + netPay.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');

    if (document.getElementById('badge_total_deductions')) document.getElementById('badge_total_deductions').textContent = '₱ ' + totalDeductions.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

function saveQuickMasterData(options = {}) {
    if (!currentActiveEmployeeId) {
        alert('Please select an employee first.');
        return Promise.resolve(false);
    }

    const payload = {
        incomes: [],
        deductions: [],
    };

    document.querySelectorAll('#income_table_body tr').forEach(row => {
        const typeId = row.querySelector('.inc-type-select')?.value;
        const rateInput = row.querySelector('.inc-rate-input');
        const amt = rateInput ? parseFloat(rateInput.value || 0) : 0;
        if (typeId) {
            payload.incomes.push({ income_type_id: typeId, amount: amt });
        }
    });

    document.querySelectorAll('#deduction_table_body tr').forEach(row => {
        const typeId = row.querySelector('.dec-type-select')?.value;
        const amt = parseFloat(row.querySelector('.dec-amount-input')?.value || 0);
        if (typeId && amt >= 0) {
            payload.deductions.push({ deduction_type_id: typeId, amount: amt });
        }
    });

    const updateUrl = "{{ url('/' . panelPrefix() . '/payroll-employees/company-master') }}/" + currentActiveEmployeeId + "/update-quick";

    return fetch(updateUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
        },
        body: JSON.stringify(payload)
    })
    .then(async res => {
        const data = await res.json();
        if (!res.ok || data.status !== 'success') {
            throw new Error(data.message || 'Failed to save quick master.');
        }
        return data;
    })
    .then(data => {
        if (!options.silent) {
            alert(data.message || 'Quick master saved successfully.');
        }
        window.PayrollEmployeeUnsavedChanges?.markSaved();
        return true;
    })
    .catch(err => {
        if (!options.silent) {
            alert('Save failed: ' + err.message);
        }
        return false;
    });
}
</script>
