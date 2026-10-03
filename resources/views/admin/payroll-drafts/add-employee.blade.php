@extends('admin.layouts.Master')

@section('title', 'Add Employee to Payroll')

@section('content')
    <div class="col-12">
        <div class="card mt-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1">Add Employee to Payroll</h4>
                    <span class="text-muted">{{ $period->period_name }} ({{ $period->period_start?->format('M d, Y') }} - {{ $period->period_end?->format('M d, Y') }})</span>
                </div>
                <a href="{{ panelRoute('payroll-drafts.index', ['payroll_period_id' => $period->id]) }}" class="btn btn-secondary">Back to Drafts</a>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    This creates a draft only for the selected employee. Existing payroll drafts and their manual inputs will not be changed.
                </div>

                @if($employees->isEmpty())
                    <div class="alert alert-warning mb-0">There are no eligible employees left to add to this payroll period.</div>
                @else
                    <form method="POST" action="{{ panelRoute('payroll-drafts.add-employee.store') }}">
                        @csrf
                        <input type="hidden" name="payroll_period_id" value="{{ $period->id }}">

                        <div class="mb-3">
                            <label for="employee_id" class="form-label fw-bold">Employee <span class="text-danger">*</span></label>
                            <select id="employee_id" name="employee_id" class="form-select @error('employee_id') is-invalid @enderror" required>
                                <option value="">Select employee</option>
                                @foreach($employees as $employee)
                                    <option value="{{ $employee->id }}" @selected((int) old('employee_id') === $employee->id)>
                                        {{ $employee->employee_code }} - {{ $employee->full_name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('employee_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <button type="submit" class="btn btn-success"><i class="mdi mdi-account-plus"></i> Add Employee Draft</button>
                    </form>
                @endif
            </div>
        </div>
    </div>
@endsection
