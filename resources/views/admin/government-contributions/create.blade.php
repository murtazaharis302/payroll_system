@extends('admin.layouts.Master')
@section('title') Add Government Contribution @endsection
@section('content')
    <div class="col-12">
        <div class="card mt-4">
            <div class="card-header">
                <h4>Add Government Contribution</h4>
                <a href="{{ route('government-contributions.index') }}" class="btn btn-secondary float-end">Back</a>
            </div>
            <div class="card-body">
                <form action="{{ route('government-contributions.store') }}" method="POST">
                    @csrf
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="employee_id" class="form-label">Employee <span class="text-danger">*</span></label>
                            <select name="employee_id" id="employee_id" class="form-control @error('employee_id') is-invalid @enderror" required>
                                <option value="">Select Employee</option>
                                @foreach($employees as $employee)
                                    <option value="{{ $employee->id }}" {{ old('employee_id') == $employee->id ? 'selected' : '' }}>
                                        {{ $employee->employee_code }} - {{ $employee->full_name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('employee_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="type" class="form-label">Contribution Type <span class="text-danger">*</span></label>
                            <select name="type" id="type" class="form-control @error('type') is-invalid @enderror" required>
                                <option value="">Select Type</option>
                                <option value="sss" {{ old('type') == 'sss' ? 'selected' : '' }}>SSS</option>
                                <option value="philhealth" {{ old('type') == 'philhealth' ? 'selected' : '' }}>PhilHealth</option>
                                <option value="pagibig" {{ old('type') == 'pagibig' ? 'selected' : '' }}>Pag-IBIG</option>
                            </select>
                            @error('type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="employee_share" class="form-label">Employee Share <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" name="employee_share" id="employee_share" 
                                   class="form-control @error('employee_share') is-invalid @enderror" 
                                   value="{{ old('employee_share') }}" required>
                            @error('employee_share')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="employer_share" class="form-label">Employer Share <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" name="employer_share" id="employer_share" 
                                   class="form-control @error('employer_share') is-invalid @enderror" 
                                   value="{{ old('employer_share') }}" required>
                            @error('employer_share')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="period_start" class="form-label">Period Start <span class="text-danger">*</span></label>
                            <input type="date" name="period_start" id="period_start" 
                                   class="form-control @error('period_start') is-invalid @enderror" 
                                   value="{{ old('period_start') }}" required>
                            @error('period_start')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="period_end" class="form-label">Period End <span class="text-danger">*</span></label>
                            <input type="date" name="period_end" id="period_end" 
                                   class="form-control @error('period_end') is-invalid @enderror" 
                                   value="{{ old('period_end') }}" required>
                            @error('period_end')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="payment_date" class="form-label">Payment Date</label>
                            <input type="date" name="payment_date" id="payment_date" 
                                   class="form-control @error('payment_date') is-invalid @enderror" 
                                   value="{{ old('payment_date') }}">
                            @error('payment_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                            <select name="status" id="status" class="form-control @error('status') is-invalid @enderror" required>
                                <option value="pending" {{ old('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                                <option value="paid" {{ old('status') == 'paid' ? 'selected' : '' }}>Paid</option>
                                <option value="submitted" {{ old('status') == 'submitted' ? 'selected' : '' }}>Submitted</option>
                            </select>
                            @error('status')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea name="notes" id="notes" rows="3" class="form-control @error('notes') is-invalid @enderror">{{ old('notes') }}</textarea>
                        @error('notes')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="text-end">
                        <button type="submit" class="btn" style="background-color: #f06115; color: white">Save Contribution</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
