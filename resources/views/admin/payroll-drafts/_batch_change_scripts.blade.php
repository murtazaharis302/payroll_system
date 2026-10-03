<script>
    function syncIncomeConfigInputs() {
        document.querySelectorAll('#card_income_config tr.income-type-config-row').forEach(row => {
            const chk = row.querySelector('.income-type-chk');
            const isChecked = chk && chk.checked;
            row.querySelectorAll('.income-type-config-input').forEach(input => {
                input.disabled = !isChecked;
            });
        });
    }

    function syncDeductionConfigInputs() {
        document.querySelectorAll('#card_deduction_config tr.deduction-type-config-row').forEach(row => {
            const chk = row.querySelector('.deduction-type-chk');
            const isChecked = chk && chk.checked;
            row.querySelectorAll('.deduction-type-config-input').forEach(input => {
                input.disabled = !isChecked;
            });
        });
    }

    function switchBatchConfigTab(tab) {
        const incomeCard = document.getElementById('card_income_config');
        const deductionCard = document.getElementById('card_deduction_config');
        const btnIncomes = document.getElementById('tab_btn_incomes');
        const btnDeductions = document.getElementById('tab_btn_deductions');

        if (tab === 'incomes') {
            if (incomeCard) incomeCard.style.display = 'block';
            if (deductionCard) deductionCard.style.display = 'none';

            if (btnIncomes) {
                btnIncomes.style.backgroundColor = '#10b981';
                btnIncomes.style.color = '#ffffff';
                btnIncomes.style.borderColor = '#10b981';
            }
            if (btnDeductions) {
                btnDeductions.style.backgroundColor = '#ffffff';
                btnDeductions.style.color = '#475569';
                btnDeductions.style.borderColor = '#cbd5e1';
            }
        } else {
            if (incomeCard) incomeCard.style.display = 'none';
            if (deductionCard) deductionCard.style.display = 'block';

            if (btnIncomes) {
                btnIncomes.style.backgroundColor = '#ffffff';
                btnIncomes.style.color = '#475569';
                btnIncomes.style.borderColor = '#cbd5e1';
            }
            if (btnDeductions) {
                btnDeductions.style.backgroundColor = '#f06115';
                btnDeductions.style.color = '#ffffff';
                btnDeductions.style.borderColor = '#f06115';
            }
        }
    }

    function changeBatchPeriod(periodId) {
        if (!periodId) return;
        const companyId = document.getElementById('company_id_select')?.value || '{{ $selectedCompanyId ?? "" }}';
        let url = '{{ panelRoute("payroll-drafts.batch-change.create") }}?payroll_period_id=' + encodeURIComponent(periodId);
        if (companyId) {
            url += '&company_id=' + encodeURIComponent(companyId);
        }
        window.location.href = url;
    }

    function toggleSelectAllDrafts(master) {
        const rows = document.querySelectorAll('.draft-row');
        rows.forEach(row => {
            if (row.style.display !== 'none') {
                const chk = row.querySelector('.draft-item-chk');
                if (chk) chk.checked = master.checked;
            }
        });
        updateSelectedDraftsCount();
    }

    function updateSelectedDraftsCount() {
        const checkedCount = document.querySelectorAll('.draft-item-chk:checked').length;
        const totalVisible = document.querySelectorAll('.draft-row:not([style*="display: none"])').length;
        const badge = document.getElementById('selected_count_badge');
        if (badge) {
            badge.textContent = checkedCount + ' selected';
        }
        const masterChk = document.getElementById('select_all_drafts');
        if (masterChk) {
            masterChk.checked = (totalVisible > 0 && checkedCount === totalVisible);
        }
    }

    function filterDraftEmployees(query) {
        const normalized = (query || '').toLowerCase().trim();
        const rows = document.querySelectorAll('.draft-row');
        rows.forEach(row => {
            const name = row.getAttribute('data-emp-name') || '';
            const code = row.getAttribute('data-emp-code') || '';
            const match = !normalized || name.includes(normalized) || code.includes(normalized);
            row.style.display = match ? '' : 'none';
        });
        updateSelectedDraftsCount();
    }

    document.addEventListener('DOMContentLoaded', function() {
        syncIncomeConfigInputs();
        syncDeductionConfigInputs();
        updateSelectedDraftsCount();

        const form = document.getElementById('batch_change_form');
        if (form) {
            form.addEventListener('submit', function(e) {
                const selectedIncomes = document.querySelectorAll('.income-type-chk:checked').length;
                const selectedDeductions = document.querySelectorAll('.deduction-type-chk:checked').length;
                const selectedDrafts = document.querySelectorAll('.draft-item-chk:checked').length;

                if (selectedIncomes === 0 && selectedDeductions === 0) {
                    e.preventDefault();
                    alert('Please select at least one Income or Deduction type to apply.');
                    return false;
                }

                if (selectedDrafts === 0) {
                    e.preventDefault();
                    alert('Please select at least one target employee draft.');
                    return false;
                }

                // Sync disabled states before submission so unchecked inputs are omitted from payload
                syncIncomeConfigInputs();
                syncDeductionConfigInputs();
            });
        }
    });
</script>
