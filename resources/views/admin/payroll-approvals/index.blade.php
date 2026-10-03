@extends('admin.layouts.Master')

@section('title') Payroll Approvals @endsection

@section('header-additional')
<style>
    .status-badge {
        padding: 5px 12px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .status-pending { background-color: #f1c40f; color: #fff; }
    .status-approved { background-color: #2ecc71; color: #fff; }
    .status-rejected { background-color: #e74c3c; color: #fff; }
    .summary-card {
        padding: 20px;
        margin-bottom: 20px;
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        transition: all 0.3s ease;
        border: none;
        color: #fff;
    }
    .summary-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    }
    .summary-card.pending { background: linear-gradient(135deg, #f39c12, #f1c40f); }
    .summary-card.approved { background: linear-gradient(135deg, #27ae60, #2ecc71); }
    .summary-card.rejected { background: linear-gradient(135deg, #c0392b, #e74c3c); }
    .summary-card h5 { font-size: 13px; text-transform: uppercase; letter-spacing: 1px; font-weight: bold; opacity: 0.9; margin-bottom: 8px; }
    .summary-card h2 { font-size: 32px; font-weight: 800; margin-bottom: 5px; }
    .summary-card small { opacity: 0.85; font-weight: 500; color: #fff !important; }
    
    .action-buttons .btn {
        margin-right: 5px;
        margin-bottom: 5px;
        font-weight: bold;
    }
    .filter-section {
        background: #fdfefe;
        border: 1px solid #e5e8e8;
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 20px;
    }
    #draftsTable th, #draftsTable td {
        vertical-align: middle !important;
    }
</style>
@endsection

@section('content')
<div class="container-fluid">
    <!-- Page Title -->
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <h4 class="page-title">Payroll Approval (Accounting)</h4>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ panelRoute('dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active">Payroll Approval</li>
                </ol>
            </div>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row">
        <div class="col-md-4">
            <div class="summary-card pending">
                <h5>Pending Approval</h5>
                <h2>{{ $pendingCount }}</h2>
                <small>Awaiting your review</small>
            </div>
        </div>
        <div class="col-md-4">
            <div class="summary-card approved">
                <h5>Approved</h5>
                <h2>{{ $approvedCount }}</h2>
                <small>Payroll generated</small>
            </div>
        </div>
        <div class="col-md-4">
            <div class="summary-card rejected">
                <h5>Rejected</h5>
                <h2>{{ $rejectedCount }}</h2>
                <small>Sent back to HR</small>
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

    <!-- Filters -->
    <div class="card">
        <div class="card-body filter-section">
            <form method="GET" action="{{ route('payroll-approvals.index') }}" class="row g-3">
                <div class="col-md-3">
                    <label for="payroll_period_id" class="form-label" style="font-size: 11px; font-weight: bold; color: #555;">Payroll Period</label>
                    <select name="payroll_period_id" id="payroll_period_id" class="form-select">
                        <option value="">All Periods</option>
                        @foreach($payrollPeriods as $period)
                            <option value="{{ $period->id }}" 
                                {{ request('payroll_period_id') == $period->id ? 'selected' : '' }}>
                                {{ $period->period_name }} 
                                ({{ \Carbon\Carbon::parse($period->period_start)->format('M d') }} - 
                                {{ \Carbon\Carbon::parse($period->period_end)->format('M d, Y') }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3">
                    <label for="status" class="form-label" style="font-size: 11px; font-weight: bold; color: #555;">Status</label>
                    <select name="status" id="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="pending_approval" {{ request('status') == 'pending_approval' ? 'selected' : '' }}>
                            Pending Approval
                        </option>
                        <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>
                            Approved
                        </option>
                        <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>
                            Rejected
                        </option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label for="employee_id" class="form-label" style="font-size: 11px; font-weight: bold; color: #555;">Employee</label>
                    <select name="employee_id" id="employee_id" class="form-select">
                        <option value="">All Employees</option>
                        @foreach($employees as $employee)
                            <option value="{{ $employee->id }}" 
                                {{ request('employee_id') == $employee->id ? 'selected' : '' }}>
                                {{ $employee->last_name }}, {{ $employee->first_name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100" style="background: linear-gradient(135deg, #1f618d, #2980b9); border: none; padding: 8px; font-weight: bold;">
                        <i class="mdi mdi-filter"></i> Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Batch Actions -->
    @if($drafts->where('status', 'pending_approval')->count() > 0)
    <div class="card mt-3">
        <div class="card-body">
            <form method="POST" action="{{ route('payroll-approvals.batch-approve') }}" id="batchApproveForm">
                @csrf
                <div class="row align-items-end">
                    <div class="col-md-7">
                        <label class="form-label" style="font-size: 11px; font-weight: bold; color: #555;">Batch Approval Notes (Optional)</label>
                        <input type="text" name="batch_approval_notes" class="form-control" 
                            placeholder="Enter notes for all selected drafts">
                    </div>
                    <div class="col-md-5 d-flex gap-2">
                        <button type="submit" class="btn btn-success w-50" id="batchApproveBtn" style="background: linear-gradient(135deg, #27ae60, #2ecc71); border: none; font-weight: bold;" disabled>
                            <i class="mdi mdi-check-all"></i> Approve Selected
                        </button>
                        <button type="button" class="btn btn-danger w-50" id="batchRejectBtn" style="background: linear-gradient(135deg, #c0392b, #e74c3c); border: none; font-weight: bold;" disabled>
                            <i class="mdi mdi-close-circle"></i> Reject Selected
                        </button>
                    </div>
                </div>
            </form>

            <form method="POST" action="{{ route('payroll-approvals.batch-reject') }}" id="batchRejectForm" style="display:none;">
                @csrf
                <input type="hidden" name="rejection_reason" id="batchRejectionReason">
            </form>
        </div>
    </div>
    @endif

    <!-- Drafts List -->
    <div class="card mt-3">
        <div class="card-body">
            <h5 class="card-title mb-4">Payroll Drafts Pending Review</h5>
            
            @if($drafts->count() > 0)
            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle" id="draftsTable">
                    <thead>
                        <tr>
                            @if($drafts->where('status', 'pending_approval')->count() > 0)
                            <th width="40">
                                <input type="checkbox" id="selectAll">
                            </th>
                            @endif
                            <th>Employee</th>
                            <th>Period</th>
                            <th>Days Worked</th>
                            <th>Gross Pay</th>
                            <th>Deductions</th>
                            <th>Net Pay</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($drafts as $draft)
                        <tr>
                            @if($drafts->where('status', 'pending_approval')->count() > 0)
                            <td>
                                @if($draft->status == 'pending_approval')
                                <input type="checkbox" class="draft-checkbox" value="{{ $draft->id }}">
                                @endif
                            </td>
                            @endif
                            <td>
                                <strong>{{ $draft->employee->full_name }}</strong><br>
                                <small class="text-muted">Code: {{ $draft->employee->employee_code }}</small>
                            </td>
                            <td>
                                <strong>{{ $draft->payrollPeriod->period_name }}</strong><br>
                                <small class="text-muted">{{ \Carbon\Carbon::parse($draft->payrollPeriod->period_start)->format('M d') }} - 
                                {{ \Carbon\Carbon::parse($draft->payrollPeriod->period_end)->format('M d, Y') }}</small>
                            </td>
                            <td class="text-center">{{ number_format($draft->days_worked ?? 0, 1) }}</td>
                            <td>₱{{ number_format($draft->gross_pay ?? 0, 2) }}</td>
                            <td class="text-danger">₱{{ number_format($draft->total_deductions ?? 0, 2) }}</td>
                            <td>
                                <strong>₱{{ number_format($draft->net_pay ?? 0, 2) }}</strong>
                            </td>
                            <td>
                                @if($draft->status == 'pending_approval')
                                    <span class="status-badge status-pending">Pending</span>
                                @elseif($draft->status == 'approved')
                                    <span class="status-badge status-approved">Approved</span>
                                @elseif($draft->status == 'rejected')
                                    <span class="status-badge status-rejected">Rejected</span>
                                @endif
                            </td>
                            <td>
                                @if($draft->submitted_at)
                                    {{ \Carbon\Carbon::parse($draft->submitted_at)->format('M d, Y') }}<br>
                                    <small class="text-muted">{{ \Carbon\Carbon::parse($draft->submitted_at)->format('h:i A') }}</small>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                <div class="d-flex align-items-center" style="gap: 12px; min-width: 90px;">
                                    <a href="{{ route('payroll-approvals.review', $draft->id) }}" class="text-primary" title="Review" style="text-decoration: none;">
                                        <i class="fa-solid fa-eye" style="font-size: 14px;"></i>
                                    </a>

                                    <a href="{{ route('payroll-drafts.edit', $draft->id) }}" class="text-warning" title="Edit Draft" style="text-decoration: none;">
                                        <i class="fa-solid fa-pen-to-square" style="font-size: 14px;"></i>
                                    </a>

                                    @if($draft->status == 'pending_approval')
                                    <form method="POST" action="{{ route('payroll-approvals.approve', $draft->id) }}" 
                                        style="display:inline; margin:0;" onsubmit="return confirm('Approve this payroll draft?');">
                                        @csrf
                                        <button type="submit" class="btn btn-link p-0 text-success" title="Approve" style="border: none; background: none; line-height: 1;">
                                            <i class="fa-solid fa-circle-check" style="font-size: 14px;"></i>
                                        </button>
                                    </form>

                                    <button type="button" class="btn btn-link p-0 text-danger" style="border: none; background: none; line-height: 1;"
                                        onclick="showRejectModal({{ $draft->id }})" title="Reject">
                                        <i class="fa-solid fa-circle-xmark" style="font-size: 14px;"></i>
                                    </button>
                                    @elseif($draft->status === 'approved' && \App\Models\MenuRoleAccess::hasModuleAccess(auth()->user(), 'payroll_locked_edit_override'))
                                    <button type="button" class="btn btn-link p-0 text-warning" style="border: none; background: none; line-height: 1;"
                                        onclick="showIndexReopenModal({{ $draft->id }})" title="Reopen for Correction">
                                        <i class="fa-solid fa-lock-open" style="font-size: 14px;"></i>
                                    </button>
                                    @endif
                                </div>

                                @if($draft->status == 'approved' && $draft->approver)
                                <small class="d-block text-muted mt-1" style="font-size: 10px;">
                                    By: {{ $draft->approver->name }}
                                </small>
                                @endif

                                @if($draft->status == 'rejected' && $draft->rejection_reason)
                                <small class="d-block text-danger mt-1" style="font-size: 10px;">
                                    Reason: {{ Str::limit($draft->rejection_reason, 30) }}
                                </small>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="d-flex align-items-center justify-content-between flex-wrap mt-3">
                <div>
                    {{ $drafts->links() }}
                </div>
                <x-per-page-selector :perPage="request('per_page', 50)" />
            </div>
            @else
            <div class="alert alert-info">
                <i class="mdi mdi-information"></i> No payroll drafts found matching the filters.
            </div>
            @endif
        </div>
    </div>
</div>

@include('admin.payroll-approvals._index_modals')
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    // Select All Checkbox
    const selectAll = document.getElementById('selectAll');
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            $('.draft-checkbox').prop('checked', $(this).prop('checked'));
            updateBatchButtons();
        });
    }

    $(document).on('change', '.draft-checkbox', function() {
        updateBatchButtons();
    });

    function updateBatchButtons() {
        const checkedCount = $('.draft-checkbox:checked').length;
        $('#batchApproveBtn, #batchRejectBtn').prop('disabled', checkedCount === 0);
    }

    // Batch Approve
    $('#batchApproveForm').on('submit', function(e) {
        const selectedIds = [];
        $('.draft-checkbox:checked').each(function() {
            selectedIds.push($(this).val());
        });

        if (selectedIds.length === 0) {
            e.preventDefault();
            alert('Please select at least one draft to approve.');
            return false;
        }

        if (!confirm(`Approve ${selectedIds.length} payroll draft(s)?`)) {
            e.preventDefault();
            return false;
        }

        // Add hidden inputs
        selectedIds.forEach(id => {
            $(this).append(`<input type="hidden" name="draft_ids[]" value="${id}">`);
        });
    });

    // Batch Reject
    $('#batchRejectBtn').on('click', function() {
        const selectedIds = [];
        $('.draft-checkbox:checked').each(function() {
            selectedIds.push($(this).val());
        });

        if (selectedIds.length === 0) {
            alert('Please select at least one draft to reject.');
            return;
        }

        const reason = prompt(`Enter rejection reason for ${selectedIds.length} draft(s):`);
        if (!reason) return;

        const form = $('#batchRejectForm');
        form.find('input[name="draft_ids[]"]').remove();
        selectedIds.forEach(id => {
            form.append(`<input type="hidden" name="draft_ids[]" value="${id}">`);
        });
        $('#batchRejectionReason').val(reason);
        form.submit();
    });
});

function showRejectModal(draftId) {
    const modal = new bootstrap.Modal(document.getElementById('rejectModal'));
    const form = document.getElementById('rejectForm');
    const rejectUrlTemplate = @json(panelRoute('payroll-approvals.reject', ['id' => '__draft_id__']));
    form.action = rejectUrlTemplate.replace('__draft_id__', draftId);
    modal.show();
}

function showIndexReopenModal(draftId) {
    const modal = new bootstrap.Modal(document.getElementById('indexReopenModal'));
    const form = document.getElementById('indexReopenForm');
    const reopenUrlTemplate = @json(panelRoute('payroll-approvals.reopen', ['id' => '__draft_id__']));
    form.action = reopenUrlTemplate.replace('__draft_id__', draftId);
    modal.show();
}
</script>
@endsection
