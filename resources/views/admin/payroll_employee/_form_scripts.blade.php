<script>
let formIncomeTypes = @json($incomeTypes ?? []);
let existingEmpIncomes = @json(isset($employee) && $employee->empIncomes ? $employee->empIncomes : []);

const FORM_DAYS_PER_MONTH = 26;
const FORM_HOURS_PER_DAY = 8;

function switchTab(tabPaneId) {
    document.querySelectorAll('#employeeFormTabContent .tab-pane').forEach(function(pane) {
        pane.classList.remove('show', 'active');
    });

    document.querySelectorAll('#employeeFormTabs .nav-link').forEach(function(navBtn) {
        navBtn.classList.remove('active');
        navBtn.setAttribute('aria-selected', 'false');
    });

    const targetPane = document.getElementById(tabPaneId);
    if (targetPane) {
        targetPane.classList.add('show', 'active');
    }

    const targetBtn = document.querySelector(`[data-bs-target="#${tabPaneId}"], [data-target="#${tabPaneId}"]`);
    if (targetBtn) {
        targetBtn.classList.add('active');
        targetBtn.setAttribute('aria-selected', 'true');
    }
}

function updateFormOvertimeDisplays(hourlyVal) {
    const h = parseFloat(hourlyVal || 0);
    const regOt = document.getElementById('form_ot_regular_rate');
    const restOt = document.getElementById('form_ot_restday_rate');
    const specOt = document.getElementById('form_ot_special_holiday_rate');
    const regHolOt = document.getElementById('form_ot_regular_holiday_rate');

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

function recalculateFormLivePreview() {
    let basicMonthly = 0;
    document.querySelectorAll('#form_income_table_body tr').forEach(row => {
        const typeSelect = row.querySelector('.form-inc-type');
        const amtInput = row.querySelector('.form-inc-amount');
        if (!typeSelect || !amtInput) return;

        const typeId = parseInt(typeSelect.value, 10);
        const typeObj = formIncomeTypes.find(t => t.id === typeId);
        if (typeObj && (typeObj.system_key === 'basic' || (typeObj.code && typeObj.code.toUpperCase() === 'BASIC'))) {
            basicMonthly = parseFloat(amtInput.value || 0);
        }
    });

    const daily = basicMonthly > 0 ? (basicMonthly / FORM_DAYS_PER_MONTH) : 0;
    const hourly = daily > 0 ? (daily / FORM_HOURS_PER_DAY) : 0;

    const mEl = document.getElementById('form_preview_monthly');
    const dEl = document.getElementById('form_preview_daily');
    const hEl = document.getElementById('form_preview_hourly');

    if (mEl) mEl.value = basicMonthly.toFixed(2);
    if (dEl) dEl.value = daily.toFixed(2);
    if (hEl) hEl.value = hourly.toFixed(2);

    updateFormOvertimeDisplays(hourly);
}

function addFormIncomeRow(typeId = '', amount = '') {
    const tbody = document.getElementById('form_income_table_body');
    if (!tbody) return;

    const rowIndex = tbody.children.length;
    const tr = document.createElement('tr');

    let typeOptions = '<option value="">Select Income Type</option>';
    formIncomeTypes.forEach(t => {
        const selected = (t.id == typeId) ? 'selected' : '';
        typeOptions += `<option value="${t.id}" ${selected}>${t.name} (${t.code})</option>`;
    });

    tr.innerHTML = `
        <td>
            <select name="incomes[${rowIndex}][income_type_id]" class="form-select form-select-sm form-inc-type" onchange="recalculateFormLivePreview()" required>
                ${typeOptions}
            </select>
        </td>
        <td>
            <input type="number" step="0.01" name="incomes[${rowIndex}][amount]" class="form-control form-control-sm form-inc-amount" value="${amount}" placeholder="0.00" oninput="recalculateFormLivePreview()" required>
        </td>
        <td class="text-center">
            <button type="button" class="btn btn-link text-danger p-0" onclick="this.closest('tr').remove(); recalculateFormLivePreview();">
                <i class="mdi mdi-close-circle font-18"></i>
            </button>
        </td>
    `;
    tbody.appendChild(tr);
}

document.addEventListener('DOMContentLoaded', function() {
    const failedStepElement = document.getElementById('employeeFormFailedStep');
    const failedStep = Number(failedStepElement?.dataset?.failedStep || 1);

    const stepTabIds = {
        1: 'tab-basic-info',
        2: 'tab-recurring-income',
        3: 'tab-statutory'
    };

    const targetTabId = stepTabIds[failedStep] || 'tab-basic-info';
    switchTab(targetTabId);

    // Populate initial income rows
    const tbody = document.getElementById('form_income_table_body');
    if (tbody) {
        if (existingEmpIncomes && existingEmpIncomes.length > 0) {
            existingEmpIncomes.forEach(inc => {
                addFormIncomeRow(inc.income_type_id, inc.amount || inc.rate);
            });
        } else {
            // Default row: find basic income type
            const basicType = formIncomeTypes.find(t => t.system_key === 'basic' || (t.code && t.code.toUpperCase() === 'BASIC'));
            if (basicType) {
                addFormIncomeRow(basicType.id, '');
            } else {
                addFormIncomeRow();
            }
        }
    }

    recalculateFormLivePreview();
});
</script>
