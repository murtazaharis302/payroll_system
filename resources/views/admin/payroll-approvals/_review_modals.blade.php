<!-- Approve Modal -->
<div class="modal fade" id="approveModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('payroll-approvals.approve', $draft->id) }}">
                @csrf
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="mdi mdi-check-circle"></i> Approve Payroll Draft</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>You are about to approve this payroll draft. This will:</p>
                    <ul>
                        <li>Create a final PayrollTransaction record</li>
                        <li>Mark the draft as approved</li>
                        <li>Make the payroll available for payment processing</li>
                    </ul>
                    <div class="mb-3">
                        <label class="form-label">Approval Notes (Optional)</label>
                        <textarea name="approval_notes" class="form-control" rows="3"
                            placeholder="Add any notes or comments..."></textarea>
                    </div>
                    <div class="alert alert-info mb-0">
                        <strong>Net Pay:</strong> ₱{{ number_format($draft->net_pay ?? 0, 2) }}
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="mdi mdi-check-circle"></i> Confirm Approval
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('payroll-approvals.reject', $draft->id) }}">
                @csrf
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="mdi mdi-close-circle"></i> Reject Payroll Draft</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>You are about to reject this payroll draft. The HR team will be able to review and resubmit.</p>
                    <div class="mb-3">
                        <label class="form-label">Rejection Reason <span class="text-danger">*</span></label>
                        <textarea name="rejection_reason" class="form-control" rows="4"
                            placeholder="Explain why this draft is being rejected..." required></textarea>
                        <small class="text-muted">Be specific so HR can correct the issues.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="mdi mdi-close-circle"></i> Confirm Rejection
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reopen Modal -->
<div class="modal fade" id="reopenModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('payroll-approvals.reopen', $draft->id) }}">
                @csrf
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title font-weight-bold"><i class="mdi mdi-lock-open-variant"></i> Reopen Approved Payroll</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>You are about to reopen this approved payroll for correction. This will:</p>
                    <ul>
                        <li>Reset status to <strong>Pending Approval</strong></li>
                        <li>Clear the previous approval timestamp and approver metadata</li>
                        <li>Allow authorized corrections to be saved</li>
                        <li>Require this payroll to be reviewed and approved again before disbursement</li>
                    </ul>
                    <div class="mb-3">
                        <label class="form-label font-weight-semibold">Reason for Reopening <span class="text-danger">*</span></label>
                        <textarea name="reopen_reason" class="form-control" rows="4"
                            placeholder="Explain why this approved payroll is being reopened..." required></textarea>
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
