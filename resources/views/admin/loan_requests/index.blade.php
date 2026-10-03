@extends('admin.layouts.Master')

@section('title') Loan Request Approval Queue @endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card shadow-sm border-0 mt-3">
            <div class="card-header bg-white border-bottom py-3">
                <h4 class="card-title text-dark fw-bold mb-0">
                    <i class="fe-check-square text-primary me-2"></i> HR Loan Request Approval Queue
                </h4>
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
                                <th>Submitted Date</th>
                                <th class="text-end">Actions</th>
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
                                        <span class="badge bg-soft-info text-info px-2 py-1">
                                            {{ $req->loanType->name ?? 'Loan' }}
                                        </span>
                                    </td>
                                    <td class="fw-bold text-dark">₱{{ number_format($req->requested_amount, 2) }}</td>
                                    <td>
                                        @if($req->status === 'pending')
                                            <span class="badge bg-warning text-dark"><i class="fe-clock me-1"></i> Pending Review</span>
                                        @elseif($req->status === 'approved')
                                            <span class="badge bg-success"><i class="fe-check-circle me-1"></i> Approved</span>
                                        @else
                                            <span class="badge bg-danger"><i class="fe-x-circle me-1"></i> Rejected</span>
                                        @endif
                                    </td>
                                    <td>{{ $req->created_at->format('M d, Y h:i A') }}</td>
                                    <td class="text-end">
                                        @if($req->status === 'pending')
                                            <button type="button" class="btn btn-sm btn-success me-1" data-bs-toggle="modal" data-bs-target="#approveModal_{{ $req->id }}">
                                                <i class="fe-check me-1"></i> Approve
                                            </button>
                                            <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal_{{ $req->id }}">
                                                <i class="fe-x me-1"></i> Reject
                                            </button>
                                        @else
                                            <span class="text-muted small">Processed</span>
                                        @endif
                                    </td>
                                </tr>

                                {{-- Approve Modal with Amount Range Slider --}}
                                <div class="modal fade" id="approveModal_{{ $req->id }}" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header bg-light">
                                                <h5 class="modal-title text-success font-weight-bold">
                                                    <i class="fe-check-circle me-1"></i> Approve Loan Request
                                                </h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <form action="{{ panelRoute('loan-requests.update-status', $req->id) }}" method="POST">
                                                @csrf
                                                <input type="hidden" name="status" value="approved">
                                                <div class="modal-body">
                                                    <div class="p-3 bg-light rounded mb-3">
                                                        <p class="mb-1"><strong>Employee:</strong> {{ $req->employee->full_name ?? 'N/A' }}</p>
                                                        <p class="mb-1"><strong>Loan Type:</strong> {{ $req->loanType->name ?? 'Loan' }}</p>
                                                        <p class="mb-0"><strong>Originally Requested:</strong> <span class="text-primary fw-bold">₱{{ number_format($req->requested_amount, 2) }}</span></p>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label font-weight-bold d-flex justify-content-between">
                                                            <span>Adjust Approved Amount (₱)</span>
                                                            <span class="text-muted small">Use slider or input to tweak</span>
                                                        </label>

                                                        {{-- Numerical Input --}}
                                                        <input type="number" step="0.01" min="1" max="500000" id="num_input_{{ $req->id }}" name="requested_amount" class="form-control form-control-lg text-success fw-bold mb-2" value="{{ $req->requested_amount }}" oninput="syncSlider_{{ $req->id }}(this.value)">

                                                        {{-- Interactive Range Slider --}}
                                                        <input type="range" class="form-range" id="range_slider_{{ $req->id }}" min="500" max="{{ max(50000, (float)$req->requested_amount * 2) }}" step="100" value="{{ $req->requested_amount }}" oninput="syncInput_{{ $req->id }}(this.value)">
                                                        <div class="d-flex justify-content-between text-muted small mt-1">
                                                            <span>₱500</span>
                                                            <span>₱{{ number_format(max(50000, (float)$req->requested_amount * 2)) }}</span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-success px-4">Confirm Approval</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <script>
                                    function syncInput_{{ $req->id }}(val) {
                                        document.getElementById('num_input_{{ $req->id }}').value = parseFloat(val).toFixed(2);
                                    }
                                    function syncSlider_{{ $req->id }}(val) {
                                        document.getElementById('range_slider_{{ $req->id }}').value = val;
                                    }
                                </script>

                                {{-- Reject Modal with Textarea --}}
                                <div class="modal fade" id="rejectModal_{{ $req->id }}" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header bg-light">
                                                <h5 class="modal-title text-danger font-weight-bold">
                                                    <i class="fe-x-circle me-1"></i> Reject Loan Request
                                                </h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <form action="{{ panelRoute('loan-requests.update-status', $req->id) }}" method="POST">
                                                @csrf
                                                <input type="hidden" name="status" value="rejected">
                                                <div class="modal-body">
                                                    <p>Are you sure you want to reject the loan request for <strong>{{ $req->employee->full_name ?? 'this employee' }}</strong>?</p>

                                                    <div class="mb-3">
                                                        <label for="rejection_reason_{{ $req->id }}" class="form-label font-weight-bold">Rejection Reason <span class="text-danger">*</span></label>
                                                        <textarea name="rejection_reason" id="rejection_reason_{{ $req->id }}" class="form-control" rows="3" placeholder="Specify why this loan request is being rejected..." required></textarea>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-danger px-4">Confirm Rejection</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">
                                        <i class="fe-check-circle display-4 d-block mb-2 text-muted"></i>
                                        No loan requests pending in queue.
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
@endsection
