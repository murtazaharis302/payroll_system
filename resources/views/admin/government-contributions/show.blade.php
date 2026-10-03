@extends('admin.layouts.Master')
@section('title') View Government Contribution @endsection
@section('content')
    <div class="col-12">
        <div class="card mt-4">
            <div class="card-header">
                <h4>Government Contribution Details</h4>
                <div class="float-end">
                    @if($contribution->payroll_draft_id)
                        <a href="{{ route('payroll-drafts.edit', $contribution->payroll_draft_id) }}" class="btn btn-primary">View Payroll Input</a>
                    @endif
                    <a href="{{ route('government-contributions.index') }}" class="btn btn-secondary">Back</a>
                </div>
            </div>
            <div class="card-body">
                <div class="alert alert-info mb-4">
                    This record was auto-synced from payroll deductions. To change amounts, edit the payroll draft.
                </div>
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h5>Contribution Information</h5>
                        <table class="table table-borderless">
                            <tr>
                                <th width="40%">Employee:</th>
                                <td>{{ $contribution->employee->employee_code }} - {{ $contribution->employee->full_name }}</td>
                            </tr>
                            <tr>
                                <th>Contribution Type:</th>
                                <td>
                                    @if($contribution->type == 'sss')
                                        <span class="badge bg-primary">SSS</span>
                                    @elseif($contribution->type == 'philhealth')
                                        <span class="badge bg-success">PhilHealth</span>
                                    @else
                                        <span class="badge bg-info">Pag-IBIG</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th>Period:</th>
                                <td>{{ date('F d, Y', strtotime($contribution->period_start)) }} to {{ date('F d, Y', strtotime($contribution->period_end)) }}</td>
                            </tr>
                            <tr>
                                <th>Status:</th>
                                <td>
                                    @if($contribution->status == 'paid')
                                        <span class="badge bg-success">Paid</span>
                                    @elseif($contribution->status == 'submitted')
                                        <span class="badge bg-info">Submitted</span>
                                    @else
                                        <span class="badge bg-warning">Pending</span>
                                    @endif
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h5>Amount Breakdown</h5>
                        <table class="table table-borderless">
                            <tr>
                                <th width="40%">Employee Share:</th>
                                <td><strong>₱ {{ number_format($contribution->employee_share, 2) }}</strong></td>
                            </tr>
                            <tr>
                                <th>Employer Share:</th>
                                <td><strong>₱ {{ number_format($contribution->employer_share, 2) }}</strong></td>
                            </tr>
                            <tr>
                                <th>Total Contribution:</th>
                                <td><h5 class="text-success">₱ {{ number_format($contribution->total_contribution, 2) }}</h5></td>
                            </tr>
                            <tr>
                                <th>Payment Date:</th>
                                <td>{{ $contribution->payment_date ? date('F d, Y', strtotime($contribution->payment_date)) : 'Not yet paid' }}</td>
                            </tr>
                        </table>
                    </div>
                </div>

                @if($contribution->notes)
                <div class="row">
                    <div class="col-12">
                        <h5>Notes</h5>
                        <div class="alert alert-info">
                            {{ $contribution->notes }}
                        </div>
                    </div>
                </div>
                @endif

                <div class="row mt-3">
                    <div class="col-12">
                        <h5>Record Information</h5>
                        <table class="table table-borderless">
                            <tr>
                                <th width="20%">Created At:</th>
                                <td>{{ $contribution->created_at->format('F d, Y h:i A') }}</td>
                            </tr>
                            <tr>
                                <th>Updated At:</th>
                                <td>{{ $contribution->updated_at->format('F d, Y h:i A') }}</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
