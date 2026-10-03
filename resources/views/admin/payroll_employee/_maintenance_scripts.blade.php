<script>
let currentActiveEmployeeId = null;
let globalIncomeTypes = @json($incomeTypes ?? []);
let globalDeductionTypes = @json($deductionTypes ?? []);

const DAYS_PER_MONTH = 26;
const HOURS_PER_DAY = 8;

document.addEventListener('DOMContentLoaded', function () {
    const firstEmpItem = document.querySelector('#employee_directory_list .employee-item');
    if (firstEmpItem) {
        const empId = firstEmpItem.getAttribute('data-emp-id');
        loadEmployeeRecordDetails(empId, firstEmpItem);
    }
});

function filterEmployeeDirectory() {
    const searchInput = document.getElementById('employee_quick_search') || document.getElementById('maint_employee_search');
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
            item.style.display = '';
            visibleCount++;
        } else {
            item.style.display = 'none';
        }
    });

    const badge = document.getElementById('emp_count_badge');
    if (badge) badge.textContent = visibleCount;
}

function loadEmployeeRecordDetails(empId, element) {
    if (!empId) return;

    currentActiveEmployeeId = empId;

    document.querySelectorAll('#employee_directory_list .employee-item').forEach(el => el.classList.remove('active'));
    if (element) {
        element.classList.add('active');
    }

    document.getElementById('pane_loading_spinner').style.display = 'block';
    document.getElementById('pane_content_area').style.display = 'none';

    const url = "{{ panelRoute('payroll-employees.full-details', ['id' => '__ID__']) }}".replace('__ID__', empId);

    fetch(url, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(res => res.json())
    .then(data => {
        document.getElementById('pane_loading_spinner').style.display = 'none';
        document.getElementById('pane_content_area').style.display = 'block';

        if (data.status === 'success' && data.employee) {
            const emp = data.employee;

            document.getElementById('active_emp_name').textContent = `${emp.first_name || ''} ${emp.last_name || ''}`;
            const deptName = emp.department ? emp.department.name : (emp.dept_id ? 'Dept #' + emp.dept_id : 'No Department');
            document.getElementById('active_emp_subtitle').textContent = `Code: ${emp.employee_code || '-'} | Position: ${emp.position || 'Staff'} | Department: ${deptName}`;

            document.getElementById('rec_employee_code').value = emp.employee_code || '';
            document.getElementById('rec_first_name').value = emp.first_name || '';
            document.getElementById('rec_middle_name').value = emp.middle_name || '';
            document.getElementById('rec_last_name').value = emp.last_name || '';
            document.getElementById('rec_email').value = emp.email || '';
            document.getElementById('rec_phone').value = emp.phone || '';
            document.getElementById('rec_date_of_birth').value = emp.date_of_birth || '';
            document.getElementById('rec_date_hired').value = emp.date_hired || '';
            document.getElementById('rec_position').value = emp.position || '';
            if (document.getElementById('rec_status')) {
                document.getElementById('rec_status').value = emp.status || 'active';
            }
            document.getElementById('rec_dept_id').value = emp.dept_id || '';

            loadSubDepartmentsForSelectedDept(emp.sub_dept_id);

            document.getElementById('rec_acct_num').value = emp.acct_num || '';
            document.getElementById('rec_atm_num').value = emp.atm_num || '';
            document.getElementById('rec_biometric_id').value = emp.biometric_id || '';
            document.getElementById('rec_sss_number').value = emp.sss_number || '';
            document.getElementById('rec_philhealth_number').value = emp.philhealth_number || '';
            document.getElementById('rec_pagibig_number').value = emp.pagibig_number || '';
            document.getElementById('rec_tin_number').value = emp.tin_number || '';
            document.getElementById('rec_tax_code').value = emp.tax_code || 'SINGLE';
            document.getElementById('rec_remarks').value = emp.remarks || '';

            // Update read-only preview cards
            updateDerivedRatesDisplay(emp.monthly_rate || 0, emp.daily_rate || 0, emp.hourly_rate || 0);

            // Populate Recurring Incomes & Deductions
            const hasDraft = data.has_payroll_draft;
            populateMaintIncomeGrid(data.incomes || [], hasDraft);
            populateMaintDeductionGrid(data.deductions || [], hasDraft);
            window.PayrollEmployeeUnsavedChanges?.markSaved?.();
        }
    })
    .catch(err => {
        document.getElementById('pane_loading_spinner').style.display = 'none';
        document.getElementById('pane_content_area').style.display = 'block';
        alert('Failed to load employee record: ' + err.message);
    });
}

function loadSubDepartmentsForSelectedDept(selectedSubDeptId = null) {
    const deptId = document.getElementById('rec_dept_id').value;
    const subDeptSelect = document.getElementById('rec_sub_dept_id');
    subDeptSelect.innerHTML = '<option value="">-- Select Sub-Department --</option>';
    if (!deptId) return;

    const url = "{{ panelRoute('payroll-employees.get-sub-departments') }}";
    fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json'
        },
        body: JSON.stringify({ department_id: deptId })
    })
    .then(res => res.json())
    .then(data => {
        if (data.sub_departments) {
            data.sub_departments.forEach(sub => {
                const opt = document.createElement('option');
                opt.value = sub.id;
                opt.textContent = sub.name;
                if (selectedSubDeptId && sub.id == selectedSubDeptId) {
                    opt.selected = true;
                }
                subDeptSelect.appendChild(opt);
            });
        }
    });
}

function updateDerivedRatesDisplay(monthlyVal, dailyVal, hourlyVal) {
    const mEl = document.getElementById('rec_preview_monthly_rate');
    const dEl = document.getElementById('rec_preview_daily_rate');
    const hEl = document.getElementById('rec_preview_hourly_rate');

    if (mEl) mEl.value = parseFloat(monthlyVal || 0).toFixed(2);
    if (dEl) dEl.value = parseFloat(dailyVal || 0).toFixed(2);
    if (hEl) hEl.value = parseFloat(hourlyVal || 0).toFixed(2);

    updateOvertimeMultipliers(hourlyVal || 0);
}

function updateOvertimeMultipliers(hourlyVal) {
    const regOt = document.getElementById('rec_ot_regular_rate');
    const restOt = document.getElementById('rec_ot_restday_rate');
    const specOt = document.getElementById('rec_ot_special_rate');
    const regHolOt = document.getElementById('rec_ot_regular_holiday_rate');

    const h = parseFloat(hourlyVal || 0);
    if (h > 0) {
        if (regOt) regOt.value = (h * 1.25).toFixed(2);
        if (restOt) restOt.value = (h * 1.30).toFixed(2);
        if (specOt) specOt.value = (h * 1.30).toFixed(2);
        if (regHolOt) regHolOt.value = (h * 2.00).toFixed(2);
    } else {
        if (regOt) regOt.value = '0.00';
        if (restOt) restOt.value = '0.00';
        if (specOt) specOt.value = '0.00';
        if (regHolOt) regHolOt.value = '0.00';
    }
}

function recalculateLivePreviewFromIncomeRows() {
    let basicMonthly = 0;
    document.querySelectorAll('#maint_income_table_body tr').forEach(row => {
        const typeSelect = row.querySelector('.maint-inc-type');
        const amtInput = row.querySelector('.maint-inc-amount');
        if (!typeSelect || !amtInput) return;

        const typeId = parseInt(typeSelect.value, 10);
        const typeObj = globalIncomeTypes.find(t => t.id === typeId);
        if (typeObj && (typeObj.system_key === 'basic' || (typeObj.code && typeObj.code.toUpperCase() === 'BASIC'))) {
            basicMonthly = parseFloat(amtInput.value || 0);
        }
    });

    const daily = basicMonthly > 0 ? (basicMonthly / DAYS_PER_MONTH) : 0;
    const hourly = daily > 0 ? (daily / HOURS_PER_DAY) : 0;
    updateDerivedRatesDisplay(basicMonthly, daily, hourly);
}

function populateMaintIncomeGrid(incomes, hasDraft = true) {
    const tbody = document.getElementById('maint_income_table_body');
    if (!tbody) return;
    tbody.innerHTML = '';

    if (incomes.length === 0) {
        tbody.innerHTML = `<tr><td colspan="3" class="text-center text-muted py-3">No recurring income records configured.</td></tr>`;
        return;
    }

    incomes.forEach(inc => {
        addMaintIncomeRow(inc.income_type_id, inc.amount, inc.id);
    });
    recalculateLivePreviewFromIncomeRows();
}

function addMaintIncomeRow(typeId = '', amount = 0, assignmentId = '') {
    const tbody = document.getElementById('maint_income_table_body');
    if (!tbody) return;
    const tr = document.createElement('tr');

    let typeOptions = '<option value="">Select Income Type</option>';
    globalIncomeTypes.forEach(t => {
        const selected = (t.id == typeId) ? 'selected' : '';
        typeOptions += `<option value="${t.id}" ${selected}>${t.name} (${t.code})</option>`;
    });

    tr.innerHTML = `
        <td>
            <input type="hidden" class="maint-inc-id" value="${assignmentId || ''}">
            <select class="form-select form-select-sm maint-inc-type" onchange="recalculateLivePreviewFromIncomeRows()">${typeOptions}</select>
        </td>
        <td>
            <input type="number" step="0.01" class="form-control form-control-sm maint-inc-amount" value="${amount}" oninput="recalculateLivePreviewFromIncomeRows()">
        </td>
        <td>
            <button type="button" class="btn btn-link text-danger p-0" onclick="this.closest('tr').remove(); recalculateLivePreviewFromIncomeRows();">
                <i class="mdi mdi-close-circle font-18"></i>
            </button>
        </td>
    `;
    tbody.appendChild(tr);
}

function populateMaintDeductionGrid(deductions, hasDraft = true) {
    const tbody = document.getElementById('maint_deduction_table_body');
    if (!tbody) return;
    tbody.innerHTML = '';

    if (deductions.length === 0) {
        tbody.innerHTML = `<tr><td colspan="3" class="text-center text-muted py-3">No recurring deduction records configured.</td></tr>`;
        return;
    }

    deductions.forEach(dec => {
        addMaintDeductionRow(dec.deduction_type_id, dec.amount, dec.id);
    });
}

function addMaintDeductionRow(typeId = '', amount = 0, assignmentId = '') {
    const tbody = document.getElementById('maint_deduction_table_body');
    if (!tbody) return;
    const tr = document.createElement('tr');

    let typeOptions = '<option value="">Select Deduction Type</option>';
    globalDeductionTypes.forEach(t => {
        const selected = (t.id == typeId) ? 'selected' : '';
        typeOptions += `<option value="${t.id}" ${selected}>${t.name} (${t.code})</option>`;
    });

    tr.innerHTML = `
        <td>
            <input type="hidden" class="maint-dec-id" value="${assignmentId || ''}">
            <select class="form-select form-select-sm maint-dec-type">${typeOptions}</select>
        </td>
        <td>
            <input type="number" step="0.01" class="form-control form-control-sm maint-dec-amount" value="${amount}">
        </td>
        <td>
            <button type="button" class="btn btn-link text-danger p-0" onclick="this.closest('tr').remove();">
                <i class="mdi mdi-close-circle font-18"></i>
            </button>
        </td>
    `;
    tbody.appendChild(tr);
}

function saveEmployeeRecord(options = {}) {
    if (!currentActiveEmployeeId) {
        alert('Please select an employee profile first.');
        return Promise.resolve(false);
    }

    const payload = {
        employee_code: document.getElementById('rec_employee_code').value.trim(),
        first_name: document.getElementById('rec_first_name').value.trim(),
        middle_name: document.getElementById('rec_middle_name').value.trim(),
        last_name: document.getElementById('rec_last_name').value.trim(),
        email: document.getElementById('rec_email').value.trim(),
        phone: document.getElementById('rec_phone').value.trim(),
        date_of_birth: document.getElementById('rec_date_of_birth').value,
        date_hired: document.getElementById('rec_date_hired').value,
        position: document.getElementById('rec_position').value.trim(),
        status: document.getElementById('rec_status') ? document.getElementById('rec_status').value : 'active',
        dept_id: document.getElementById('rec_dept_id').value,
        sub_dept_id: document.getElementById('rec_sub_dept_id').value,
        acct_num: document.getElementById('rec_acct_num').value.trim(),
        atm_num: document.getElementById('rec_atm_num').value.trim(),
        biometric_id: document.getElementById('rec_biometric_id').value.trim(),
        sss_number: document.getElementById('rec_sss_number').value.trim(),
        philhealth_number: document.getElementById('rec_philhealth_number').value.trim(),
        pagibig_number: document.getElementById('rec_pagibig_number').value.trim(),
        tin_number: document.getElementById('rec_tin_number').value.trim(),
        tax_code: document.getElementById('rec_tax_code').value,
        remarks: document.getElementById('rec_remarks').value.trim(),
        incomes: [],
        deductions: [],
    };

    document.querySelectorAll('#maint_income_table_body tr').forEach(row => {
        const idVal = row.querySelector('.maint-inc-id')?.value;
        const typeId = row.querySelector('.maint-inc-type')?.value;
        const amt = parseFloat(row.querySelector('.maint-inc-amount')?.value || 0);
        if (typeId && amt >= 0) {
            const item = { income_type_id: parseInt(typeId, 10), amount: amt };
            if (idVal) item.id = parseInt(idVal, 10);
            payload.incomes.push(item);
        }
    });

    document.querySelectorAll('#maint_deduction_table_body tr').forEach(row => {
        const idVal = row.querySelector('.maint-dec-id')?.value;
        const typeId = row.querySelector('.maint-dec-type')?.value;
        const amt = parseFloat(row.querySelector('.maint-dec-amount')?.value || 0);
        if (typeId && amt >= 0) {
            const item = { deduction_type_id: parseInt(typeId, 10), amount: amt };
            if (idVal) item.id = parseInt(idVal, 10);
            payload.deductions.push(item);
        }
    });

    const updateUrl = "{{ panelRoute('payroll-employees.update-record', ['id' => '__ID__']) }}".replace('__ID__', currentActiveEmployeeId);

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
            throw new Error(data.message || 'Failed to save employee record.');
        }
        return data;
    })
    .then(data => {
        window.PayrollEmployeeUnsavedChanges?.markSaved();
        if (!options.silent) {
            alert(data.message || 'Employee record saved successfully.');
            const nameEl = document.getElementById('emp_list_name_' + currentActiveEmployeeId);
            const codeEl = document.getElementById('emp_list_code_' + currentActiveEmployeeId);
            const posEl = document.getElementById('emp_list_pos_' + currentActiveEmployeeId);
            if (nameEl) nameEl.textContent = `${payload.first_name} ${payload.last_name}`;
            if (codeEl) codeEl.textContent = payload.employee_code;
            if (posEl) posEl.textContent = payload.position;

            const activeBtn = document.querySelector('#employee_directory_list .employee-item[data-emp-id="' + currentActiveEmployeeId + '"]');
            loadEmployeeRecordDetails(currentActiveEmployeeId, activeBtn);
        }
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
