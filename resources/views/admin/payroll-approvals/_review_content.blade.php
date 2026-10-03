<div class="container-fluid">
    <!-- Page Title -->
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <h4 class="page-title">Review Payroll Draft</h4>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ panelRoute('dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ panelRoute('payroll-approvals.index') }}">Payroll Approval</a></li>
                    <li class="breadcrumb-item active">Review</li>
                </ol>
            </div>
        </div>
    </div>

    <!-- Success/Error Messages -->
    @if(session('status'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="mdi mdi-check-circle me-2"></i>{{ session('status') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('statusDanger'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="mdi mdi-alert-circle me-2"></i>{{ session('statusDanger') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <!-- Employee & Period Info -->
    <div class="card">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h5><i class="mdi mdi-account"></i> Employee Information</h5>
                    <table class="table table-sm">
                        <tr>
                            <td><strong>Name:</strong></td>
                            <td>{{ $employee->full_name }}</td>
                        </tr>
                        <tr>
                            <td><strong>Employee #:</strong></td>
                            <td>{{ $employee->employee_code }}</td>
                        </tr>
                        <tr>
                            <td><strong>Position:</strong></td>
                            <td>{{ $employee->position ?? 'N/A' }}</td>
                        </tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <h5><i class="mdi mdi-calendar-range"></i> Payroll Period</h5>
                    <table class="table table-sm">
                        <tr>
                            <td><strong>Period:</strong></td>
                            <td>{{ $period->period_name }}</td>
                        </tr>
                        <tr>
                            <td><strong>Date Range:</strong></td>
                            <td>
                                {{ \Carbon\Carbon::parse($period->period_start)->format('M d, Y') }} -
                                {{ \Carbon\Carbon::parse($period->period_end)->format('M d, Y') }}
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Status:</strong></td>
                            <td>
                                @if($draft->status == 'pending_approval')
                                    <span class="status-badge status-pending">Pending Approval</span>
                                @elseif($draft->status == 'approved')
                                    <span class="status-badge status-approved">Approved</span>
                                @elseif($draft->status == 'rejected')
                                    <span class="status-badge status-rejected">Rejected</span>
                                @endif
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Cards Row -->
    <div class="row mt-3 mb-3">
        <div class="col-md-4">
            <div class="p-3 text-center border rounded shadow-sm" style="background-color: #f8fafc; border-top: 4px solid #3b82f6 !important;">
                <h6 class="text-muted mb-1">Gross Pay</h6>
                <h2 class="fw-bold text-dark mb-0">₱{{ number_format($draft->gross_pay ?? 0, 2) }}</h2>
            </div>
        </div>
        <div class="col-md-4">
            <div class="p-3 text-center border rounded shadow-sm" style="background-color: #fff7ed; border-top: 4px solid #f06115 !important;">
                <h6 class="text-muted mb-1">Total Deductions</h6>
                <h2 class="fw-bold text-dark mb-0">₱{{ number_format($draft->total_deductions ?? 0, 2) }}</h2>
            </div>
        </div>
        <div class="col-md-4">
            <div class="p-3 text-center border rounded shadow-sm" style="background-color: #f0fdf4; border-top: 4px solid #10b981 !important;">
                <h6 class="text-muted mb-1">Net Pay</h6>
                <h2 class="fw-bold text-dark mb-0">₱{{ number_format($draft->net_pay ?? 0, 2) }}</h2>
            </div>
        </div>
    </div>

    <!-- Attendance Section -->
    <div class="review-section border rounded p-4 shadow-sm bg-white mb-4">
        <div class="section-header border-bottom pb-2 mb-3">
            <h5 class="mb-0 text-dark font-weight-bold"><i class="mdi mdi-calendar-check me-2" style="color: #f06115;"></i> Attendance Information</h5>
        </div>
        <div class="row g-3">
            <div class="col-md-6">
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                    <span class="text-muted font-weight-semibold" style="font-size: 14px;">Days Worked:</span>
                    <span class="font-weight-bold text-dark" style="font-size: 14px;">{{ $draft->days_worked ?? 0 }} days</span>
                </div>
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                    <span class="text-muted font-weight-semibold" style="font-size: 14px;">Days Absent:</span>
                    <span class="font-weight-bold text-dark" style="font-size: 14px;">{{ $draft->days_absent ?? 0 }} days</span>
                </div>
                <div class="d-flex justify-content-between align-items-center py-2">
                    <span class="text-muted font-weight-semibold" style="font-size: 14px;">Overtime Hours:</span>
                    <span class="font-weight-bold text-dark" style="font-size: 14px;">{{ $draft->overtime_hours ?? 0 }} hours</span>
                </div>
            </div>
            <div class="col-md-6">
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                    <span class="text-muted font-weight-semibold" style="font-size: 14px;">Late Hours:</span>
                    <span class="font-weight-bold text-dark" style="font-size: 14px;">{{ $draft->late_hours ?? 0 }} hours</span>
                </div>
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                    <span class="text-muted font-weight-semibold" style="font-size: 14px;">Undertime Hours:</span>
                    <span class="font-weight-bold text-dark" style="font-size: 14px;">{{ $draft->undertime_hours ?? 0 }} hours</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Income Section -->
    <div class="review-section border rounded p-4 shadow-sm bg-white mb-4">
        <div class="section-header border-bottom pb-2 mb-3">
            <h5 class="mb-0 text-dark font-weight-bold"><i class="mdi mdi-cash-plus me-2" style="color: #f06115;"></i> Income Breakdown</h5>
        </div>

        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
            <span class="text-muted font-weight-semibold" style="font-size: 14px;">Basic Pay:</span>
            <span class="font-weight-bold text-dark" style="font-size: 14px;">₱{{ number_format($draft->basic_pay ?? 0, 2) }}</span>
        </div>
        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
            <span class="text-muted font-weight-semibold" style="font-size: 14px;">Overtime Pay:</span>
            <span class="font-weight-bold text-dark" style="font-size: 14px;">₱{{ number_format($draft->overtime_pay ?? 0, 2) }}</span>
        </div>
        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
            <span class="text-muted font-weight-semibold" style="font-size: 14px;">Total Allowances:</span>
            <span class="font-weight-bold text-dark" style="font-size: 14px;">₱{{ number_format($draft->total_allowances ?? 0, 2) }}</span>
        </div>
        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
            <span class="text-muted font-weight-semibold" style="font-size: 14px;">Total Bonuses:</span>
            <span class="font-weight-bold text-dark" style="font-size: 14px;">₱{{ number_format($draft->total_bonuses ?? 0, 2) }}</span>
        </div>
        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
            <span class="text-muted font-weight-semibold" style="font-size: 14px;">Other Income:</span>
            <span class="font-weight-bold text-dark" style="font-size: 14px;">₱{{ number_format($draft->other_income ?? 0, 2) }}</span>
        </div>

        <div class="d-flex justify-content-between align-items-center p-3 mt-3 rounded" style="background-color: #eff6ff; border-left: 4px solid #3b82f6;">
            <span class="font-weight-bold text-dark" style="font-size: 15px;">TOTAL GROSS PAY:</span>
            <span class="font-weight-bold text-primary" style="font-size: 16px;">₱{{ number_format($draft->gross_pay ?? 0, 2) }}</span>
        </div>
    </div>

    <!-- Deductions Section -->
    <div class="review-section border rounded p-4 shadow-sm bg-white mb-4">
        <div class="section-header border-bottom pb-2 mb-3">
            <h5 class="mb-0 text-dark font-weight-bold"><i class="mdi mdi-cash-minus me-2" style="color: #f06115;"></i> Deductions Breakdown</h5>
        </div>

        <!-- Government Contributions -->
        <h6 class="mt-3 mb-2 text-dark font-weight-bold" style="font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; color: #f06115 !important;">Government Contributions</h6>
        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
            <span class="text-muted font-weight-semibold" style="font-size: 14px;">SSS Contribution:</span>
            <span class="font-weight-bold text-dark" style="font-size: 14px;">₱{{ number_format($draft->sss_contribution ?? 0, 2) }}</span>
        </div>
        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
            <span class="text-muted font-weight-semibold" style="font-size: 14px;">PhilHealth Contribution:</span>
            <span class="font-weight-bold text-dark" style="font-size: 14px;">₱{{ number_format($draft->philhealth_contribution ?? 0, 2) }}</span>
        </div>
        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
            <span class="text-muted font-weight-semibold" style="font-size: 14px;">Pag-IBIG Contribution:</span>
            <span class="font-weight-bold text-dark" style="font-size: 14px;">₱{{ number_format($draft->pagibig_contribution ?? 0, 2) }}</span>
        </div>
        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
            <span class="text-muted font-weight-semibold" style="font-size: 14px;">Withholding Tax:</span>
            <span class="font-weight-bold text-dark" style="font-size: 14px;">₱{{ number_format($draft->withholding_tax ?? 0, 2) }}</span>
        </div>

        <!-- Loan Deductions -->
        <h6 class="mt-4 mb-2 text-dark font-weight-bold" style="font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; color: #f06115 !important;">Loan Deductions</h6>
        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
            <span class="text-muted font-weight-semibold" style="font-size: 14px;">SSS Loan Deduction:</span>
            <span class="font-weight-bold text-dark" style="font-size: 14px;">₱{{ number_format($draft->sss_loan_deduction ?? 0, 2) }}</span>
        </div>
        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
            <span class="text-muted font-weight-semibold" style="font-size: 14px;">Pag-IBIG Loan Deduction:</span>
            <span class="font-weight-bold text-dark" style="font-size: 14px;">₱{{ number_format($draft->pagibig_loan_deduction ?? 0, 2) }}</span>
        </div>
        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
            <span class="text-muted font-weight-semibold" style="font-size: 14px;">Vale/Loan Deduction:</span>
            <span class="font-weight-bold text-dark" style="font-size: 14px;">₱{{ number_format($draft->vale_loan_deduction ?? 0, 2) }}</span>
        </div>
        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
            <span class="text-muted font-weight-semibold" style="font-size: 14px;">Goods Purchase Deduction:</span>
            <span class="font-weight-bold text-dark" style="font-size: 14px;">₱{{ number_format($draft->goods_purchase_deduction ?? 0, 2) }}</span>
        </div>

        <!-- Other Deductions -->
        <h6 class="mt-4 mb-2 text-dark font-weight-bold" style="font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; color: #f06115 !important;">Other Deductions</h6>
        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
            <span class="text-muted font-weight-semibold" style="font-size: 14px;">Late Deduction:</span>
            <span class="font-weight-bold text-dark" style="font-size: 14px;">₱{{ number_format($draft->late_deduction ?? 0, 2) }}</span>
        </div>
        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
            <span class="text-muted font-weight-semibold" style="font-size: 14px;">Undertime Deduction:</span>
            <span class="font-weight-bold text-dark" style="font-size: 14px;">₱{{ number_format($draft->undertime_deduction ?? 0, 2) }}</span>
        </div>
        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
            <span class="text-muted font-weight-semibold" style="font-size: 14px;">Other Deductions:</span>
            <span class="font-weight-bold text-dark" style="font-size: 14px;">₱{{ number_format($draft->other_deductions ?? 0, 2) }}</span>
        </div>
        @if(!empty($draft->custom_deductions) && is_array($draft->custom_deductions))
            @foreach($draft->custom_deductions as $typeId => $amount)
                @if($amount > 0)
                    @php
                        $dedType = \App\Models\DeductionType::find($typeId);
                        $name = $dedType ? $dedType->name : 'Other Deduction';
                    @endphp
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom ps-4" style="font-size: 13px;">
                        <span class="text-muted">&bull; {{ $name }}:</span>
                        <span class="font-weight-bold text-dark">₱{{ number_format($amount, 2) }}</span>
                    </div>
                @endif
            @endforeach
        @endif

        <div class="d-flex justify-content-between align-items-center p-3 mt-3 rounded" style="background-color: #fff7ed; border-left: 4px solid #f06115;">
            <span class="font-weight-bold text-dark" style="font-size: 15px;">TOTAL DEDUCTIONS:</span>
            <span class="font-weight-bold" style="font-size: 16px; color: #f06115;">₱{{ number_format($draft->total_deductions ?? 0, 2) }}</span>
        </div>
    </div>

    <!-- Final Summary -->
    <div class="review-section border rounded p-4 shadow-sm bg-white">
        <div class="section-header border-bottom pb-2 mb-3">
            <h5 class="mb-0 text-dark font-weight-bold"><i class="mdi mdi-calculator me-2" style="color: #f06115;"></i> Final Summary</h5>
        </div>

        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
            <span class="text-muted font-weight-semibold" style="font-size: 15px;">Gross Pay:</span>
            <span class="font-weight-bold text-dark" style="font-size: 16px;">₱{{ number_format($draft->gross_pay ?? 0, 2) }}</span>
        </div>

        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
            <span class="text-muted font-weight-semibold" style="font-size: 15px;">Total Deductions:</span>
            <span class="font-weight-bold text-danger" style="font-size: 16px;">- ₱{{ number_format($draft->total_deductions ?? 0, 2) }}</span>
        </div>

        <div class="d-flex justify-content-between align-items-center p-3 mt-3 rounded shadow-sm" style="background-color: #f0fdf4; border-left: 5px solid #10b981;">
            <span class="font-weight-bold text-dark" style="font-size: 18px; text-transform: uppercase; letter-spacing: 0.5px;">NET PAY:</span>
            <span class="font-weight-bold text-success" style="font-size: 24px;">₱{{ number_format($draft->net_pay ?? 0, 2) }}</span>
        </div>
    </div>

    <!-- Notes -->
    @if($draft->notes)
    <div class="review-section">
        <h6 class="text-muted mb-2">HR Notes:</h6>
        <p class="mb-0">{{ $draft->notes }}</p>
    </div>
    @endif

    <!-- Rejection Reason -->
    @if($draft->status == 'rejected' && $draft->rejection_reason)
    <div class="alert alert-danger">
        <strong><i class="mdi mdi-alert-circle"></i> Rejection Reason:</strong><br>
        {{ $draft->rejection_reason }}
    </div>
    @endif

    <!-- Approval Notes -->
    @if($draft->status == 'approved' && $draft->approval_notes)
    <div class="alert alert-success">
        <strong><i class="mdi mdi-check-circle"></i> Approval Notes:</strong><br>
        {{ $draft->approval_notes }}
    </div>
    @endif

    <!-- Audit Trail -->
    <div class="review-section">
        <h6 class="text-muted mb-2">Audit Trail</h6>
        <table class="table table-sm">
            <tr>
                <td><strong>Prepared By:</strong></td>
                <td>{{ $draft->preparer->name ?? 'N/A' }}</td>
                <td><strong>Created:</strong></td>
                <td>{{ \Carbon\Carbon::parse($draft->created_at)->format('M d, Y h:i A') }}</td>
            </tr>
            @if($draft->submitted_at)
            <tr>
                <td><strong>Submitted:</strong></td>
                <td colspan="3">{{ \Carbon\Carbon::parse($draft->submitted_at)->format('M d, Y h:i A') }}</td>
            </tr>
            @endif
            @if($draft->approved_at && $draft->approver)
            <tr>
                <td><strong>{{ $draft->status == 'approved' ? 'Approved' : 'Rejected' }} By:</strong></td>
                <td>{{ $draft->approver->name }}</td>
                <td><strong>Date:</strong></td>
                <td>{{ \Carbon\Carbon::parse($draft->approved_at)->format('M d, Y h:i A') }}</td>
            </tr>
            @endif
        </table>
    </div>

    <!-- Action Bar -->
    @if($draft->status == 'pending_approval')
    <div class="action-bar">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h5 class="mb-0">Ready to approve or reject this payroll draft?</h5>
            </div>
            <div class="col-md-6 text-end">
                @if(\App\Models\MenuRoleAccess::hasModuleAccess(auth()->user(), 'payroll_locked_edit_override'))
                    <a href="{{ route('payroll-drafts.edit', $draft->id) }}" class="btn btn-warning me-2 text-dark font-weight-bold">
                        <i class="mdi mdi-square-edit-outline me-1"></i> Edit / Correct Draft
                    </a>
                @endif
                <button type="button" class="btn btn-danger me-2" onclick="showRejectModal()">
                    <i class="mdi mdi-close-circle"></i> Reject
                </button>
                <button type="button" class="btn btn-success" onclick="showApproveModal()">
                    <i class="mdi mdi-check-circle"></i> Approve
                </button>
            </div>
        </div>
    </div>
    @elseif($draft->status == 'approved' && ($canReopen ?? false))
    <div class="action-bar bg-light border p-3 rounded mt-3">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h5 class="mb-1 text-dark"><i class="mdi mdi-lock-open-variant text-warning me-1"></i> Reopen for Correction</h5>
                <small class="text-muted">This payroll is Approved. Reopening resets status to Pending Approval and clears approval metadata so corrections can be made.</small>
            </div>
            <div class="col-md-4 text-end">
                <button type="button" class="btn btn-warning font-weight-bold text-dark shadow-sm" onclick="showReopenModal()">
                    <i class="mdi mdi-lock-open-variant me-1"></i> Reopen for Correction
                </button>
            </div>
        </div>
    </div>
    @endif

    <!-- Back Button -->
    <div class="text-center mt-3 mb-3">
        <a href="{{ route('payroll-approvals.index') }}" class="btn btn-secondary">
            <i class="mdi mdi-arrow-left"></i> Back to List
        </a>
    </div>
</div>
