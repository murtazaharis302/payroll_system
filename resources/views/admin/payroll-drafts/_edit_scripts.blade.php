    @php
        $isHr = auth()->user()->role === 'hr' || auth()->user()->role_id === \App\Models\Role::HR;
    @endphp
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            let isFormSubmitting = false;
            let isDraftFormDirty = false;
            let pendingNavigationUrl = null;
            let unsavedModalInstance = null;

            function getUnsavedModal() {
                if (!unsavedModalInstance) {
                    const modalEl = document.getElementById('unsavedChangesModal');
                    if (modalEl) {
                        unsavedModalInstance = new bootstrap.Modal(modalEl, { backdrop: 'static', keyboard: false });
                    }
                }
                return unsavedModalInstance;
            }

            function markDraftFormDirty() {
                isDraftFormDirty = true;
            }

            function resetDraftFormDirty() {
                isDraftFormDirty = false;
            }

            ['input', 'change', 'keyup', 'paste'].forEach(function(evtName) {
                document.addEventListener(evtName, function(e) {
                    if (e.target && e.target.closest('#payrollForm') && e.target.matches('input, select, textarea')) {
                        markDraftFormDirty();
                    }
                }, true);
            });

            const pForm = document.getElementById('payrollForm');
            if (pForm) {
                pForm.addEventListener('submit', function() {
                    resetDraftFormDirty();
                });
            }

            const clearPendingState = function () {
                pendingNavigationUrl = null;
            };

            const btnCancel = document.getElementById('btnUnsavedCancel');
            if (btnCancel) {
                btnCancel.addEventListener('click', clearPendingState);
            }

            const btnClose = document.getElementById('btnCloseUnsavedModal');
            if (btnClose) {
                btnClose.addEventListener('click', clearPendingState);
            }

            const btnDiscard = document.getElementById('btnUnsavedDiscard');
            if (btnDiscard) {
                btnDiscard.addEventListener('click', function() {
                    resetDraftFormDirty();
                    const modal = getUnsavedModal();
                    if (modal) modal.hide();
                    if (pendingNavigationUrl) {
                        const targetUrl = pendingNavigationUrl;
                        clearPendingState();
                        window.location.href = targetUrl;
                    }
                });
            }

            const btnSave = document.getElementById('btnUnsavedSave');
            if (btnSave) {
                btnSave.addEventListener('click', async function () {
                    if (!pForm || !pendingNavigationUrl) {
                        return;
                    }

                    const targetUrl = pendingNavigationUrl;
                    const submitAfterSave = document.getElementById('submit_after_save');

                    // This modal action is SAVE ONLY, never submit for approval.
                    if (submitAfterSave) {
                        submitAfterSave.value = '0';
                    }

                    // Run normal browser validation first.
                    if (!pForm.checkValidity()) {
                        pForm.reportValidity();
                        return;
                    }

                    btnSave.disabled = true;

                    try {
                        const response = await fetch(pForm.action, {
                            method: 'POST',
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json'
                            },
                            body: new FormData(pForm)
                        });

                        const data = await response.json();

                        if (!response.ok || data.status !== 'success') {
                            throw new Error(
                                data.message ||
                                (data.errors
                                    ? Object.values(data.errors).flat().join('\n')
                                    : 'Failed to save payroll draft.')
                            );
                        }

                        // Only clear dirty state AFTER Laravel confirms DB save.
                        resetDraftFormDirty();
                        pendingNavigationUrl = null;

                        const modal = getUnsavedModal();
                        if (modal) {
                            modal.hide();
                        }

                        // Only now switch to the employee originally clicked.
                        window.location.href = targetUrl;

                    } catch (error) {
                        // Stay on current employee with edits untouched.
                        alert('Failed to save payroll draft: ' + error.message);
                    } finally {
                        btnSave.disabled = false;
                    }
                });
            }

            document.addEventListener('click', function(e) {
                if (!isDraftFormDirty) return;
                if (e.target.closest('#unsavedChangesModal')) return;

                const link = e.target.closest('a');
                if (!link) return;

                const href = link.getAttribute('href');

                // Ignore links that do not actually navigate away.
                if (!href || href.startsWith('#') || href.startsWith('javascript:')) return;

                if (link.href === window.location.href) return;

                e.preventDefault();
                e.stopPropagation();

                clearPendingState();
                pendingNavigationUrl = link.href;

                const modal = getUnsavedModal();
                if (modal) modal.show();
            }, true);

            window.addEventListener('beforeunload', function (e) {
                if (isDraftFormDirty && !isFormSubmitting) {
                    e.preventDefault();
                    e.returnValue = '';
                }
            });

            function submitForApproval(btn) {
                if (isFormSubmitting) return false;
                isFormSubmitting = true;

                const form = document.getElementById('payrollForm');
                const submitHidden = document.getElementById('submit_after_save');
                if (submitHidden) submitHidden.value = '1';

                const submitBtns = document.querySelectorAll('.btn-submit-approval');
                submitBtns.forEach(b => {
                    b.disabled = true;
                    b.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Submitting...';
                });

                if (form) form.submit();
            }

            document.querySelectorAll('.btn-submit-approval').forEach(btn => {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    submitForApproval(this);
                });
            });

            const deductionTypeSelect = document.getElementById('add_deduction_type_id');
            const deductionLoanInput = document.getElementById('add_deduction_loan_id');
            if (deductionTypeSelect && deductionLoanInput) {
                deductionTypeSelect.addEventListener('change', function () {
                    deductionLoanInput.value = this.options[this.selectedIndex]?.dataset.loanId || '';
                });
            }

            const isDailyEmp = {{ ($employee->daily_rate > 0 && $employee->monthly_rate <= 0) ? 'true' : 'false' }};
            const periodList = document.getElementById('periodEmployeeList');
            const searchInput = document.getElementById('employeeSearchInput');

            if (searchInput && periodList) {
                searchInput.addEventListener('input', function () {
                    const filter = this.value.toLowerCase().trim();
                    const items = periodList.querySelectorAll('.employee-draft-item');
                    items.forEach(item => {
                        const term = item.getAttribute('data-search-term') || '';
                        if (term.includes(filter)) {
                            item.style.display = 'block';
                        } else {
                            item.style.display = 'none';
                        }
                    });
                });
            }

            function recalculatePayrollInputTotals() {
                let totalDeductions = 0.00;
                const payOutInputs = document.querySelectorAll('.pay-out-rate-input');
                if (payOutInputs.length > 0) {
                    payOutInputs.forEach(input => {
                        const rateVal = parseFloat(input.value || 0);
                        const qtyVal = parseFloat(input.getAttribute('data-qty') || 1.0);
                        const lineTot = rateVal * qtyVal;
                        const id = input.getAttribute('data-pay-out-id');
                        const totalSpan = document.getElementById('pay_out_total_' + id);
                        if (totalSpan) {
                            totalSpan.textContent = '₱ ' + lineTot.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
                            totalSpan.setAttribute('data-amount', lineTot);
                        }
                        totalDeductions += lineTot;
                    });
                } else {
                    const payOutSpans = document.querySelectorAll('.pay-out-total-span');
                    payOutSpans.forEach(span => {
                        totalDeductions += parseFloat(span.getAttribute('data-amount') || 0);
                    });
                }

                const formattedDeductions = '₱ ' + totalDeductions.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
                const dedSpan = document.getElementById('summary_total_deductions');
                const sectionDedSpan = document.getElementById('section_total_deductions');
                if (dedSpan) dedSpan.textContent = formattedDeductions;
                if (sectionDedSpan) sectionDedSpan.textContent = formattedDeductions;

                let totalGross = 0.00;
                let hasIncomeRates = false;
                const payInInputs = document.querySelectorAll('.pay-in-number-input');

                payInInputs.forEach(input => {
                    const id = input.getAttribute('data-pay-in-id');
                    const rateAttr = input.dataset.rate;
                    if (rateAttr !== undefined && rateAttr !== null && rateAttr !== '') {
                        hasIncomeRates = true;
                        const rate = parseFloat(rateAttr || 0);
                        const numVal = parseFloat(input.value || 0);
                        const lineTotal = rate * numVal;

                        const totalSpan = document.getElementById('pay_in_total_' + id);
                        if (totalSpan) {
                            totalSpan.textContent = '₱ ' + lineTotal.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
                            totalSpan.setAttribute('data-amount', lineTotal);
                        }

                        totalGross += lineTotal;
                    }
                });

                if (hasIncomeRates) {
                    const netPay = totalGross - totalDeductions;

                    const formattedGross = '₱ ' + totalGross.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
                    const grossSpan = document.getElementById('summary_total_gross');
                    const sectionGrossSpan = document.getElementById('section_total_gross');
                    if (grossSpan) grossSpan.textContent = formattedGross;
                    if (sectionGrossSpan) sectionGrossSpan.textContent = formattedGross;

                    const netSpan = document.getElementById('summary_net_pay');
                    const netHeaderSpan = document.getElementById('net_pay_header_display');

                    if (netSpan) {
                        netSpan.textContent = '₱ ' + netPay.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
                        if (netPay < 0) {
                            netSpan.classList.add('text-danger');
                        } else {
                            netSpan.classList.remove('text-danger');
                        }
                    }

                    if (netHeaderSpan) {
                        netHeaderSpan.textContent = '₱ ' + netPay.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
                    }

                    const alertBox = document.getElementById('negative_net_pay_alert');
                    const btnSubmitApproval = document.getElementById('btn_submit_approval');

                    if (netPay < 0) {
                        if (alertBox) {
                            alertBox.style.display = 'block';
                            const negAmtSpan = document.getElementById('negative_net_amount');
                            if (negAmtSpan) negAmtSpan.textContent = '₱ ' + netPay.toFixed(2);
                        }
                        if (btnSubmitApproval) btnSubmitApproval.disabled = true;
                    } else {
                        if (alertBox) alertBox.style.display = 'none';
                        if (btnSubmitApproval) btnSubmitApproval.disabled = false;
                    }
                }
            }

            // Sync Days Worked to BASIC number_input for Daily Employees
            const daysWorkedEl = document.getElementById('days_worked');
            if (daysWorkedEl) {
                ['input', 'change', 'keyup', 'keydown'].forEach(evt => {
                    daysWorkedEl.addEventListener(evt, function () {
                        if (isDailyEmp) {
                            const basicInput = document.querySelector('.pay-in-number-input[data-code="BASIC"]');
                            if (basicInput) {
                                basicInput.value = this.value;
                            }
                        }
                        recalculatePayrollInputTotals();
                    });
                });
            }

            // Sync Overtime Hours to OVERTIME number_input
            const otHoursEl = document.getElementById('overtime_hours');
            if (otHoursEl) {
                ['input', 'change', 'keyup', 'keydown'].forEach(evt => {
                    otHoursEl.addEventListener(evt, function () {
                        const otInput = document.querySelector('.pay-in-number-input[data-code="OVERTIME"]');
                        if (otInput) {
                            otInput.value = this.value;
                        }
                        recalculatePayrollInputTotals();
                    });
                });
            }

            // Sync Days Absent and Late Hours
            ['days_absent', 'late_hours'].forEach(id => {
                const el = document.getElementById(id);
                if (el) {
                    ['input', 'change', 'keyup', 'keydown'].forEach(evt => {
                        el.addEventListener(evt, recalculatePayrollInputTotals);
                    });
                }
            });

            document.querySelectorAll('.pay-in-number-input').forEach(input => {
                ['input', 'change', 'keyup', 'keydown'].forEach(evt => {
                    input.addEventListener(evt, recalculatePayrollInputTotals);
                });
            });

            document.querySelectorAll('.pay-out-rate-input').forEach(input => {
                ['input', 'change', 'keyup', 'keydown'].forEach(evt => {
                    input.addEventListener(evt, recalculatePayrollInputTotals);
                });
            });

            if (pForm) {
                pForm.addEventListener('submit', recalculatePayrollInputTotals);
            }

            recalculatePayrollInputTotals();
        });
    </script>
