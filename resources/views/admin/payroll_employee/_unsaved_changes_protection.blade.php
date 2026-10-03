<div class="modal fade" id="employeeUnsavedChangesModal" tabindex="-1" aria-labelledby="employeeUnsavedChangesModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-warning-subtle border-0">
                <h5 class="modal-title font-weight-bold" id="employeeUnsavedChangesModalLabel">
                    <i class="mdi mdi-alert-circle me-1"></i> Unsaved Changes
                </h5>
                <button type="button" class="btn-close" id="btnEmployeeUnsavedClose" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                You have unsaved employee changes. Would you like to save them before leaving?
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light" id="btnEmployeeUnsavedCancel" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-outline-danger" id="btnEmployeeUnsavedDiscard">Discard</button>
                <button type="button" class="btn text-white" id="btnEmployeeUnsavedSave" style="background-color: #f06115;">Save and Continue</button>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        let isDirty = false;
        let isSaving = false;
        let pendingAction = null;
        let modalInstance = null;
        const saveFunction = window['{{ $saveFunction }}'];
        const employeeLoadFunction = window['{{ $employeeLoadFunction }}'];
        const contentArea = document.getElementById('pane_content_area');
        const modalElement = document.getElementById('employeeUnsavedChangesModal');
        const companySelect = document.getElementById('filter_company_id');
        const initialCompanyId = companySelect?.value;

        function getModal() {
            if (!modalInstance && modalElement) {
                modalInstance = new bootstrap.Modal(modalElement, { backdrop: 'static', keyboard: false });
            }
            return modalInstance;
        }

        function clearPendingAction() {
            pendingAction = null;
        }

        function continuePendingAction() {
            const action = pendingAction;
            clearPendingAction();
            if (action) action();
        }

        function promptBeforeLeaving(action) {
            pendingAction = action;
            getModal()?.show();
        }

        function markDirty() {
            isDirty = true;
        }

        function markSaved() {
            isDirty = false;
        }

        window.PayrollEmployeeUnsavedChanges = { markSaved };

        ['input', 'change', 'paste'].forEach(function (eventName) {
            contentArea?.addEventListener(eventName, function (event) {
                if (event.target.matches('input, select, textarea')) markDirty();
            }, true);
        });

        contentArea?.addEventListener('click', function (event) {
            const button = event.target.closest('button');
            if (button && !button.matches('[onclick*="save"]')) markDirty();
        }, true);

        document.addEventListener('click', function (event) {
            if (!isDirty || event.target.closest('#employeeUnsavedChangesModal')) return;

            const employeeButton = event.target.closest('.employee-item');
            if (employeeButton) {
                event.preventDefault();
                event.stopImmediatePropagation();
                const employeeId = employeeButton.getAttribute('data-emp-id');
                promptBeforeLeaving(function () {
                    employeeLoadFunction?.(employeeId, employeeButton);
                });
                return;
            }

            const link = event.target.closest('a');
            if (!link) return;
            const href = link.getAttribute('href');
            if (!href || href.startsWith('#') || href.startsWith('javascript:') || link.href === window.location.href) return;

            event.preventDefault();
            event.stopPropagation();
            promptBeforeLeaving(function () {
                window.location.href = link.href;
            });
        }, true);

        companySelect?.addEventListener('change', function (event) {
            if (!isDirty || companySelect.value === initialCompanyId) return;
            const companyId = companySelect.value;
            companySelect.value = initialCompanyId;
            event.stopImmediatePropagation();
            promptBeforeLeaving(function () {
                window.location.href = window.location.pathname + '?company_id=' + encodeURIComponent(companyId);
            });
        }, true);

        document.getElementById('btnEmployeeUnsavedCancel')?.addEventListener('click', clearPendingAction);
        document.getElementById('btnEmployeeUnsavedClose')?.addEventListener('click', clearPendingAction);

        document.getElementById('btnEmployeeUnsavedDiscard')?.addEventListener('click', function () {
            isDirty = false;
            getModal()?.hide();
            continuePendingAction();
        });

        document.getElementById('btnEmployeeUnsavedSave')?.addEventListener('click', async function (event) {
            if (!pendingAction || isSaving || typeof saveFunction !== 'function') return;
            const saveButton = event.currentTarget;
            isSaving = true;
            saveButton.disabled = true;

            try {
                const saved = await saveFunction({ silent: true });
                if (!saved) return;

                isDirty = false;
                getModal()?.hide();
                continuePendingAction();
            } catch (error) {
                alert('Save failed: ' + error.message);
            } finally {
                isSaving = false;
                saveButton.disabled = false;
            }
        });

        window.addEventListener('beforeunload', function (event) {
            if (isDirty && !isSaving) {
                event.preventDefault();
                event.returnValue = '';
            }
        });
    });
</script>
