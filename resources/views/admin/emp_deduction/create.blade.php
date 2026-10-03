@extends('admin.layouts.Master')
@section('title') Assign Deduction to Employee @endsection
@section('content')
    <div class="col-12">
        <div class="card mt-4">
            <div class="card-header">
                <h4>Assign Deduction to Employee</h4>
                <a href="{{ route('emp-deductions.index') }}" class="btn float-end" style="background-color: #f06115; color: white">Back to List</a>
            </div>
            <div class="card-body">
                <form action="{{ route('emp-deductions.store') }}" method="POST">
                    @csrf
                    
                    <div class="mb-3">
                        <label for="employee_id" class="form-label">Employee <span style="color: red;">*</span></label>
                        <select class="form-control" id="employee_id" name="employee_id" required>
                            <option value="">Select Employee</option>
                            @foreach($employees as $employee)
                                <option value="{{ $employee->id }}" {{ old('employee_id') == $employee->id ? 'selected' : '' }}>
                                    {{ $employee->full_name }} ({{ $employee->employee_code }})
                                </option>
                            @endforeach
                        </select>
                        @error('employee_id')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="deduction_type_id" class="form-label">Deduction Type <span style="color: red;">*</span></label>
                        <select class="form-control" id="deduction_type_id" name="deduction_type_id" required>
                            <option value="">Select Deduction Type</option>
                            @foreach($deductionTypes as $deductionType)
                                <option value="{{ $deductionType->id }}" {{ old('deduction_type_id') == $deductionType->id ? 'selected' : '' }}>
                                    {{ $deductionType->name }} ({{ $deductionType->code }})
                                </option>
                            @endforeach
                        </select>
                        @error('deduction_type_id')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="amount" class="form-label">Amount <span style="color: red;">*</span></label>
                        <input type="number" step="0.01" class="form-control" id="amount" name="amount" value="{{ old('amount') }}" required>
                        @error('amount')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="frequency" class="form-label">Frequency <span style="color: red;">*</span></label>
                        <select class="form-control" id="frequency" name="frequency" required>
                            <option value="monthly" {{ old('frequency') == 'monthly' ? 'selected' : '' }}>Monthly</option>
                            <option value="semi-monthly" {{ old('frequency') == 'semi-monthly' ? 'selected' : '' }}>Semi-Monthly (Twice a month)</option>
                            <option value="weekly" {{ old('frequency') == 'weekly' ? 'selected' : '' }}>Weekly</option>
                            <option value="one-time" {{ old('frequency') == 'one-time' ? 'selected' : '' }}>One-Time</option>
                        </select>
                        @error('frequency')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="start_date" class="form-label">Start Date</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" value="{{ old('start_date') }}">
                        <small class="text-muted">Optional: Date when deduction starts</small>
                        @error('start_date')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="end_date" class="form-label">End Date</label>
                        <input type="date" class="form-control" id="end_date" name="end_date" value="{{ old('end_date') }}">
                        <small class="text-muted">Optional: Date when deduction ends</small>
                        @error('end_date')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="is_active" class="form-label">Status <span style="color: red;">*</span></label>
                        <select class="form-control" id="is_active" name="is_active" required>
                            <option value="1" {{ old('is_active', '1') == '1' ? 'selected' : '' }}>Active</option>
                            <option value="0" {{ old('is_active') == '0' ? 'selected' : '' }}>Inactive</option>
                        </select>
                        @error('is_active')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="text-center mt-4">
                        <button type="submit" class="btn" style="background-color: #f06115; color: white">Assign Deduction</button>
                        <a href="{{ route('emp-deductions.index') }}" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
