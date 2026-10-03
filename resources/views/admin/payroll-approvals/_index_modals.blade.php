<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="rejectForm">
                @csrf
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="mdi mdi-close-circle"></i> Reject Payroll Draft</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Rejection Reason <span class="text-danger">*</span></label>
                        <textarea name="rejection_reason" class="form-control" rows="4" 
                            placeholder="Explain why this draft is being rejected..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="mdi mdi-close-circle"></i> Reject Draft
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reopen Modal -->
<div class="modal fade" id="indexReopenModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="indexReopenForm">
                @csrf
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title font-weight-bold"><i class="mdi mdi-lock-open-variant"></i> Reopen Approved Payroll</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">
                        Reopening will move this payroll back to <strong>Pending Approval</strong> and clear approval metadata for corrections.
                    </p>
                    <div class="mb-3">
                        <label class="form-label font-weight-semibold">Reason for Reopening <span class="text-danger">*</span></label>
                        <textarea name="reopen_reason" class="form-control" rows="3" 
                            placeholder="Explain why this draft is being reopened for correction..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning text-dark font-weight-bold">
                        <i class="mdi mdi-check-circle"></i> Confirm &amp; Reopen
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
