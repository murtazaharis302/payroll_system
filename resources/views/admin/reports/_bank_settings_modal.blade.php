<!-- Inline Bank Settings Configuration Modal -->
<div class="modal fade" id="bankSettingsModal" tabindex="-1" aria-labelledby="bankSettingsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-gradient-info text-white py-3">
                <h5 class="modal-title font-16 fw-bold" id="bankSettingsModalLabel">
                    <i class="fe-briefcase me-2"></i> Company Bank Routing Settings
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="inlineBankSettingsForm">
                @csrf
                @if(isset($selectedCompanyId))
                    <input type="hidden" name="company_id" value="{{ $selectedCompanyId }}">
                @endif
                <div class="modal-body p-4">
                    <div class="alert alert-info py-2 font-13 mb-3">
                        <i class="fe-info me-1"></i> Please configure your company bank routing data before generating Bank Advice or Direct Deposit exports.
                    </div>

                    <div id="modalAlertError" class="alert alert-danger font-12 py-2 d-none"></div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label font-12 fw-semibold text-dark">Bank Name <span class="text-danger">*</span></label>
                            <input type="text" name="bank_name" id="modal_bank_name" class="form-control form-control-sm" placeholder="e.g., BDO Unibank, Inc." value="{{ $defaultBank->bank_name ?? '' }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label font-12 fw-semibold text-dark">Company Account Number <span class="text-danger">*</span></label>
                            <input type="text" name="account_number" id="modal_account_number" class="form-control form-control-sm" placeholder="e.g., 1098-7654-3210" value="{{ $defaultBank->account_number ?? '' }}" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label font-12 fw-semibold text-dark">Branch Name (Optional)</label>
                            <input type="text" name="branch_name" id="modal_branch_name" class="form-control form-control-sm" placeholder="e.g., Main Branch" value="{{ $defaultBank->branch_name ?? '' }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label font-12 fw-semibold text-dark">Bank Manager Name (Optional)</label>
                            <input type="text" name="manager_name" id="modal_manager_name" class="form-control form-control-sm" placeholder="e.g., Mr. John Smith" value="{{ $defaultBank->manager_name ?? '' }}">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label font-12 fw-semibold text-dark">Primary Signatory Name (Optional)</label>
                            <input type="text" name="corporate_signee_1" id="modal_corporate_signee_1" class="form-control form-control-sm" placeholder="e.g., Jane Doe" value="{{ $defaultBank->corporate_signee_1 ?? Auth::user()?->name }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label font-12 fw-semibold text-dark">Primary Signatory Title (Optional)</label>
                            <input type="text" name="corporate_signee_1_title" id="modal_corporate_signee_1_title" class="form-control form-control-sm" placeholder="e.g., Chief Executive Officer" value="{{ $defaultBank->corporate_signee_1_title ?? '' }}">
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label font-12 fw-semibold text-dark">Secondary Signatory Name (Optional)</label>
                            <input type="text" name="corporate_signee_2" id="modal_corporate_signee_2" class="form-control form-control-sm" placeholder="e.g., Robert Tan" value="{{ $defaultBank->corporate_signee_2 ?? '' }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label font-12 fw-semibold text-dark">Secondary Signatory Title (Optional)</label>
                            <input type="text" name="corporate_signee_2_title" id="modal_corporate_signee_2_title" class="form-control form-control-sm" placeholder="e.g., Finance Director" value="{{ $defaultBank->corporate_signee_2_title ?? '' }}">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="saveBankSettingsBtn" class="btn btn-sm btn-primary fw-semibold">
                        <i class="fe-save me-1"></i> Save Bank Settings
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
