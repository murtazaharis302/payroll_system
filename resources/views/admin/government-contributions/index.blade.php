@extends('admin.layouts.Master')
@section('title') Government Contributions @endsection
@section('content')
    <div class="col-12">
        <div class="card mt-4">
            <div class="card-header">
                <h4>Government Contributions (SSS, PhilHealth, Pag-IBIG)</h4>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <strong>Auto-tracked from Payroll.</strong>
                    Contribution records are created automatically when you enter SSS, PhilHealth, and Pag-IBIG amounts in
                    <a href="{{ route('payroll-drafts.index') }}">Payroll Input</a>.
                    Manual entry is not required.
                </div>

                <form method="GET" action="{{ route('government-contributions.index') }}" class="mb-3">
                    <div class="row">
                        @if($isSuperAdmin && isset($companies) && $companies->count() > 0)
                            <div class="col-md-3 mb-3">
                                <label class="form-label" style="font-size: 11px; font-weight: bold; color: #555;">Company</label>
                                <select name="company_id" class="form-control">
                                    <option value="">All Companies</option>
                                    @foreach($companies as $comp)
                                        <option value="{{ $comp->id }}" {{ (string)$selectedCompanyId === (string)$comp->id ? 'selected' : '' }}>
                                            {{ $comp->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                        <div class="{{ $isSuperAdmin ? 'col-md-3' : 'col-md-3' }} mb-3">
                            <label class="form-label" style="font-size: 11px; font-weight: bold; color: #555;">Contribution Type</label>
                            <select name="type" class="form-control">
                                <option value="">All Types</option>
                                <option value="sss" {{ request('type') == 'sss' ? 'selected' : '' }}>SSS</option>
                                <option value="philhealth" {{ request('type') == 'philhealth' ? 'selected' : '' }}>PhilHealth</option>
                                <option value="pagibig" {{ request('type') == 'pagibig' ? 'selected' : '' }}>Pag-IBIG</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label" style="font-size: 11px; font-weight: bold; color: #555;">Employee Name</label>
                            <select name="employee_id" class="form-control">
                                <option value="">All Employees</option>
                                @foreach($employees as $emp)
                                    <option value="{{ $emp->id }}" {{ request('employee_id') == $emp->id ? 'selected' : '' }}>
                                        {{ $emp->full_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label" style="font-size: 11px; font-weight: bold; color: #555;">Start Date</label>
                            <input type="date" name="period_start" class="form-control" value="{{ request('period_start') }}">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label" style="font-size: 11px; font-weight: bold; color: #555;">End Date</label>
                            <input type="date" name="period_end" class="form-control" value="{{ request('period_end') }}">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12 d-flex justify-content-end mb-3" style="gap: 8px;">
                            <button type="submit" class="btn" style="background-color: #f06115; border: none; padding: 8px 24px; font-size: 12px; font-weight: bold; color: #fff;">
                                <i class="mdi mdi-filter"></i> Filter
                            </button>
                            <a href="{{ route('government-contributions.index') }}" class="btn btn-secondary" style="background-color: #6c757d; border: none; padding: 8px 24px; font-size: 12px; font-weight: bold; color: #fff; text-decoration: none; display: inline-flex; align-items: center; justify-content: center;">
                                <i class="mdi mdi-filter-remove"></i> Clear
                            </a>
                        </div>
                    </div>
                </form>

                <table class="table table-striped" id="myTable">
                    <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Type</th>
                        <th>Period</th>
                        <th>Employee Share</th>
                        <th>Employer Share</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                    </thead>
                    <tbody>
                        @forelse ($contributions as $contribution)
                            <tr>
                                <td>{{ $contribution->employee->full_name ?? 'N/A' }}</td>
                                <td>
                                    @if($contribution->type == 'sss')
                                        <span class="badge bg-primary">SSS</span>
                                    @elseif($contribution->type == 'philhealth')
                                        <span class="badge bg-success">PhilHealth</span>
                                    @else
                                        <span class="badge bg-info">Pag-IBIG</span>
                                    @endif
                                </td>
                                <td>{{ date('M d, Y', strtotime($contribution->period_start)) }} - {{ date('M d, Y', strtotime($contribution->period_end)) }}</td>
                                <td>₱ {{ number_format($contribution->employee_share, 2) }}</td>
                                <td>₱ {{ number_format($contribution->employer_share, 2) }}</td>
                                <td><strong>₱ {{ number_format($contribution->total_contribution, 2) }}</strong></td>
                                <td>
                                    @if($contribution->status == 'paid')
                                        <span class="badge bg-success">Paid</span>
                                    @elseif($contribution->status == 'submitted')
                                        <span class="badge bg-info">Submitted</span>
                                    @else
                                        <span class="badge bg-warning">Pending</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('government-contributions.show', $contribution->id) }}" title="View"><i class="fa-solid fa-eye"></i></a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted">
                                    No contributions yet. Enter SSS / PhilHealth / Pag-IBIG deductions in Payroll Input to track them here.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3">
                    <div>
                        {{ $contributions->links() }}
                    </div>
                    <x-per-page-selector :paginator="$contributions" />
                </div>
            </div>
        </div>
    </div>
@endsection
