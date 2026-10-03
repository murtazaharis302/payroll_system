@extends('admin.layouts.Master')

@section('title') Loan Requests @endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card shadow-sm border-0 mt-3">
            <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center py-3">
                <h4 class="card-title text-dark fw-bold mb-0">
                    <i class="fe-trending-down text-primary me-2"></i> Employee Loan Requests
                </h4>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#requestLoanModal">
                    <i class="fe-plus-circle me-1"></i> Request Loan
                </button>
            </div>
            <div class="card-body">
                @if (session('status'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fe-check-circle me-1"></i> {{ session('status') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif
                @if (session('statusDanger'))
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fe-alert-triangle me-1"></i> {{ session('statusDanger') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                <div class="table-responsive">
                    <table class="table table-hover table-centered mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Employee</th>
                                <th>Loan Type</th>
                                <th>Requested Amount</th>
                                <th>Status</th>
                                <th>Date Requested</th>
                                <th>Notes / Reason</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($requests as $idx => $req)
                                <tr>
                                    <td>{{ $idx + 1 }}</td>
                                    <td>
                                        <div class="fw-bold text-dark">{{ $req->employee->full_name ?? '—' }}</div>
                                        <small class="text-muted">{{ $req->employee->employee_code ?? '' }}</small>
                                    </td>
                                    <td>
                                        <span class="badge bg-soft-primary text-primary px-2 py-1">
                                            {{ $req->loanType->name ?? 'Loan' }}
                                        </span>
                                    </td>
                                    <td class="fw-bold text-dark">₱{{ number_format($req->requested_amount, 2) }}</td>
                                    <td>
                                        @if($req->status === 'pending')
                                            <span class="badge bg-warning text-dark"><i class="fe-clock me-1"></i> Pending</span>
                                        @elseif($req->status === 'approved')
                                            <span class="badge bg-success"><i class="fe-check-circle me-1"></i> Approved</span>
                                        @else
                                            <span class="badge bg-danger"><i class="fe-x-circle me-1"></i> Rejected</span>
                                        @endif
                                    </td>
                                    <td>{{ $req->created_at->format('M d, Y h:i A') }}</td>
                                    <td>
                                        @if($req->status === 'rejected' && $req->rejection_reason)
                                            <span class="text-danger small" title="{{ $req->rejection_reason }}">
                                                <i class="fe-info me-1"></i> {{ \Illuminate\Support\Str::limit($req->rejection_reason, 40) }}
                                            </span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">
                                        <i class="fe-file-text display-4 d-block mb-2 text-muted"></i>
                                        No loan requests found. Click <strong>Request Loan</strong> to submit a new application.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Request Loan Modal --}}
<div class="modal fade" id="requestLoanModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-dark fw-bold">New Loan Application</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ panelRoute('loan-requests.store') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label font-weight-bold">Select Employee <span class="text-danger">*</span></label>
                        @if($employee)
                            <input type="hidden" name="employee_id" value="{{ $employee->id }}">
                            <input type="text" class="form-control" value="{{ $employee->full_name }} ({{ $employee->employee_code }})" readonly>
                        @else
                            <select name="employee_id" class="form-control" required>
                                <option value="">— Select Employee —</option>
                                @foreach($employeesList as $emp)
                                    <option value="{{ $emp->id }}">{{ $emp->full_name }} ({{ $emp->employee_code }})</option>
                                @endforeach
                            </select>
                        @endif
                    </div>

                    <div class="mb-3">
                        <label class="form-label font-weight-bold">Loan Type <span class="text-danger">*</span></label>
                        <select name="loan_type_id" class="form-control" required>
                            <option value="">— Select Loan Type —</option>
                            @foreach($loanTypes as $lt)
                                <option value="{{ $lt->id }}">{{ $lt->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label font-weight-bold">Requested Amount (₱) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="100" name="requested_amount" class="form-control" placeholder="e.g. 5000.00" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4">Submit Application</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
