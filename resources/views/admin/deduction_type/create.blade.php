@extends('admin.layouts.Master')
@section('title') Create Deduction Type @endsection
@section('content')
    <div class="col-12">
        <div class="card mt-4 shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center" style="border-bottom: 2px solid #ef4444;">
                <h4 class="mb-0 text-dark font-weight-bold"><i class="mdi mdi-cash-minus me-2 text-danger"></i> Create Custom Deduction Type</h4>
                <a href="{{ panelRoute('deduction-types.index') }}" class="btn btn-secondary btn-sm" style="font-weight: bold;">
                    <i class="mdi mdi-arrow-left"></i> Back to List
                </a>
            </div>
            <div class="card-body p-4">
                <form action="{{ panelRoute('deduction-types.store') }}" method="POST">
                    @csrf
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="name" class="form-label font-weight-bold text-dark">Deduction Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" value="{{ old('name') }}" placeholder="e.g. Gym Membership" required>
                            @error('name')
                                <span class="text-danger small">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="code" class="form-label font-weight-bold text-dark">Deduction Code <small class="text-muted font-weight-normal">(Optional)</small></label>
                            <input type="text" class="form-control" id="code" name="code" value="{{ old('code') }}" placeholder="Auto-generated if left blank (e.g. GYM_FEE)">
                            <small class="text-muted">Unique code. Automatically formatted from name if left blank.</small>
                            @error('code')
                                <span class="text-danger small">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="type" class="form-label font-weight-bold text-dark">Category Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="type" name="type" required>
                                <option value="other" {{ old('type', 'other') == 'other' ? 'selected' : '' }}>Other (Company Voluntary / Custom Deduction)</option>
                                <option value="loan" {{ old('type') == 'loan' ? 'selected' : '' }}>Loan (Company Loan / Repayment)</option>
                                <option value="statutory" {{ old('type') == 'statutory' ? 'selected' : '' }}>Statutory (Government Rule Based)</option>
                            </select>
                            @error('type')
                                <span class="text-danger small">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="is_active" class="form-label font-weight-bold text-dark">Status <span class="text-danger">*</span></label>
                            <select class="form-select" id="is_active" name="is_active" required>
                                <option value="1" {{ old('is_active', '1') == '1' ? 'selected' : '' }}>Active (Assignable)</option>
                                <option value="0" {{ old('is_active') == '0' ? 'selected' : '' }}>Inactive</option>
                            </select>
                            @error('is_active')
                                <span class="text-danger small">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label font-weight-bold text-dark">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3" placeholder="Optional notes regarding deduction rules or schedule">{{ old('description') }}</textarea>
                        @error('description')
                            <span class="text-danger small">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" class="btn text-white font-weight-bold px-4" style="background-color: #ef4444; border: none;">
                            <i class="mdi mdi-check me-1"></i> Save Deduction Type
                        </button>
                        <a href="{{ panelRoute('deduction-types.index') }}" class="btn btn-secondary px-4">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
