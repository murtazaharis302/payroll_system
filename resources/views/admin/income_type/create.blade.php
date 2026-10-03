@extends('admin.layouts.Master')
@section('title') Create Income Type @endsection
@section('content')
    <div class="col-12">
        <div class="card mt-4 shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center" style="border-bottom: 2px solid #10b981;">
                <h4 class="mb-0 text-dark font-weight-bold"><i class="mdi mdi-cash-plus me-2 text-success"></i> Create Custom Income Type</h4>
                <a href="{{ panelRoute('income-types.index') }}" class="btn btn-secondary btn-sm" style="font-weight: bold;">
                    <i class="mdi mdi-arrow-left"></i> Back to List
                </a>
            </div>
            <div class="card-body p-4">
                <form action="{{ panelRoute('income-types.store') }}" method="POST">
                    @csrf
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="name" class="form-label font-weight-bold text-dark">Income Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" value="{{ old('name') }}" placeholder="e.g. Project Milestone Bonus" required>
                            @error('name')
                                <span class="text-danger small">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="code" class="form-label font-weight-bold text-dark">Income Code <small class="text-muted font-weight-normal">(Optional)</small></label>
                            <input type="text" class="form-control" id="code" name="code" value="{{ old('code') }}" placeholder="Auto-generated if left blank (e.g. PROJ_BONUS)">
                            <small class="text-muted">Unique code. Automatically formatted from name if left blank.</small>
                            @error('code')
                                <span class="text-danger small">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="is_taxable" class="form-label font-weight-bold text-dark">Taxability Status <span class="text-danger">*</span></label>
                            <select class="form-select" id="is_taxable" name="is_taxable" required>
                                <option value="1" {{ old('is_taxable', '1') == '1' ? 'selected' : '' }}>Yes (Taxable Earnings)</option>
                                <option value="0" {{ old('is_taxable') == '0' ? 'selected' : '' }}>No (Non-Taxable Allowance / Benefit)</option>
                            </select>
                            @error('is_taxable')
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
                        <textarea class="form-control" id="description" name="description" rows="3" placeholder="Optional notes regarding calculation rules or eligibility criteria">{{ old('description') }}</textarea>
                        @error('description')
                            <span class="text-danger small">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" class="btn text-white font-weight-bold px-4" style="background-color: #10b981; border: none;">
                            <i class="mdi mdi-check me-1"></i> Save Income Type
                        </button>
                        <a href="{{ panelRoute('income-types.index') }}" class="btn btn-secondary px-4">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
