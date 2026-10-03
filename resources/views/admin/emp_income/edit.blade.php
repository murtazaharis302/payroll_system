@extends('admin.layouts.Master')
@section('title') Edit Income Assignment @endsection
@section('content')
    <div class="col-12">
        <div class="card mt-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Edit Income Assignment</h4>
                <a href="{{ route('emp-incomes.index') }}" class="btn btn-secondary" style="background-color: #6c757d; border: none; padding: 8px 18px; font-weight: bold; color: #fff;">
                    <i class="mdi mdi-arrow-left"></i> Back to List
                </a>
            </div>
            <div class="card-body">
                <form action="{{ route('emp-incomes.update', $empIncome->id) }}" method="POST">
                    @csrf
                    @method('PUT')
                    
                    <div class="row">
                        {{-- Employee Selection --}}
                        <div class="col-md-6 mb-3">
                            <label for="employee_id" class="form-label" style="font-size: 13px; font-weight: bold; color: #555;">Employee <span style="color: red;">*</span></label>
                            <select class="form-control" id="employee_id" name="employee_id" required>
                                <option value="">Select Employee</option>
                                @foreach($employees as $employee)
                                    <option value="{{ $employee->id }}" {{ old('employee_id', $empIncome->employee_id) == $employee->id ? 'selected' : '' }}>
                                        {{ $employee->full_name }} ({{ $employee->employee_code }})
                                    </option>
                                @endforeach
                            </select>
                            @error('employee_id')
                                <span class="text-danger small">{{ $message }}</span>
                            @enderror
                        </div>

                        {{-- Income Type Selection --}}
                        <div class="col-md-6 mb-3">
                            <label for="income_type_id" class="form-label" style="font-size: 13px; font-weight: bold; color: #555;">Income Type <span style="color: red;">*</span></label>
                            <select class="form-control" id="income_type_id" name="income_type_id" required>
                                <option value="">Select Income Type</option>
                                @foreach($incomeTypes as $incomeType)
                                    @if(strtoupper($incomeType->code) === 'BASIC') @continue @endif
                                    <option value="{{ $incomeType->id }}" {{ old('income_type_id', $empIncome->income_type_id) == $incomeType->id ? 'selected' : '' }}>
                                        {{ $incomeType->name }} ({{ $incomeType->code }})
                                    </option>
                                @endforeach
                            </select>
                            @error('income_type_id')
                                <span class="text-danger small">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>

                    <div class="row">
                        {{-- Amount --}}
                        <div class="col-md-6 mb-3">
                            <label for="amount" class="form-label" style="font-size: 13px; font-weight: bold; color: #555;">Amount <span style="color: red;">*</span></label>
                            <input type="number" step="0.01" class="form-control" id="amount" name="amount" value="{{ old('amount', $empIncome->amount) }}" required>
                            @error('amount')
                                <span class="text-danger small">{{ $message }}</span>
                            @enderror
                        </div>

                        {{-- Frequency --}}
                        <div class="col-md-6 mb-3">
                            <label for="frequency" class="form-label" style="font-size: 13px; font-weight: bold; color: #555;">Frequency <span style="color: red;">*</span></label>
                            <select class="form-control" id="frequency" name="frequency" required>
                                <option value="monthly" {{ old('frequency', $empIncome->frequency) == 'monthly' ? 'selected' : '' }}>Monthly</option>
                                <option value="semi-monthly" {{ old('frequency', $empIncome->frequency) == 'semi-monthly' ? 'selected' : '' }}>Semi-Monthly (Twice a month)</option>
                                <option value="daily" {{ old('frequency', $empIncome->frequency) == 'daily' ? 'selected' : '' }}>Daily</option>
                                <option value="hourly" {{ old('frequency', $empIncome->frequency) == 'hourly' ? 'selected' : '' }}>Hourly</option>
                            </select>
                            @error('frequency')
                                <span class="text-danger small">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>

                    <div class="row">
                        {{-- Status --}}
                        <div class="col-md-6 mb-3">
                            <label for="is_active" class="form-label" style="font-size: 13px; font-weight: bold; color: #555;">Status <span style="color: red;">*</span></label>
                            <select class="form-control" id="is_active" name="is_active" required>
                                <option value="1" {{ old('is_active', $empIncome->is_active) == '1' ? 'selected' : '' }}>Active</option>
                                <option value="0" {{ old('is_active', $empIncome->is_active) == '0' ? 'selected' : '' }}>Inactive</option>
                            </select>
                            @error('is_active')
                                <span class="text-danger small">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>

                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" class="btn btn-primary" style="background: linear-gradient(135deg, #1f618d, #2980b9); border: none; padding: 8px 24px; font-weight: bold; color: #fff;">
                            <i class="mdi mdi-check"></i> Update Income Assignment
                        </button>
                        <a href="{{ route('emp-incomes.index') }}" class="btn btn-secondary" style="background-color: #6c757d; border: none; padding: 8px 24px; font-weight: bold; color: #fff; text-decoration: none;">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
