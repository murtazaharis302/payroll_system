@extends('admin.layouts.Master')
@section('title') Edit Payroll Period @endsection
@section('content')
@php
    $isSuperAdmin = (auth()->user()->role === 'admin' || auth()->user()->role_id === \App\Models\Role::SUPER_ADMIN);
@endphp

<div class="col-12">
    <div class="row">
        <div class="col-md-12">
            <div class="card mt-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">Edit Payroll Period</h4>
                    <a href="{{ panelRoute('payroll-periods.index') }}" class="btn btn-secondary" style="background-color: #6c757d; border: none; padding: 8px 18px; font-weight: bold; color: #fff;">
                        <i class="mdi mdi-arrow-left"></i> Back to List
                    </a>
                </div>
                <div class="card-body">
                    <form action="{{ panelRoute('payroll-periods.update', $period->id) }}" method="POST">
                        @csrf
                        @method('PUT')
                        
                        @if($isSuperAdmin)
                            <div class="form-group mb-3">
                                <label class="form-label" style="font-size: 13px; font-weight: bold; color: #555;">Company <span class="text-danger">*</span></label>
                                <select name="company_id" class="form-control @error('company_id') is-invalid @enderror" required>
                                    <option value="">Select Company</option>
                                    @foreach($companies as $company)
                                        <option value="{{ $company->id }}" {{ $period->company_id == $company->id ? 'selected' : '' }}>
                                            {{ $company->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('company_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        @else
                            <input type="hidden" name="company_id" value="{{ $period->company_id }}">
                        @endif

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="form-group">
                                    <label class="form-label" style="font-size: 13px; font-weight: bold; color: #555;">Start Date <span class="text-danger">*</span></label>
                                    <input type="date" id="period_start" name="period_start" class="form-control @error('period_start') is-invalid @enderror" 
                                           value="{{ old('period_start', $period->period_start->format('Y-m-d')) }}" required>
                                    @error('period_start')
                                        <div class="invalid-feedback text-danger">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-group">
                                    <label class="form-label" style="font-size: 13px; font-weight: bold; color: #555;">End Date <span class="text-danger">*</span></label>
                                    <input type="date" id="period_end" name="period_end" class="form-control @error('period_end') is-invalid @enderror" 
                                           value="{{ old('period_end', $period->period_end->format('Y-m-d')) }}" required>
                                    @error('period_end')
                                        <div class="invalid-feedback text-danger">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="form-group mb-3">
                            <label class="form-label" style="font-size: 13px; font-weight: bold; color: #555;">Period Name <span class="text-danger">*</span></label>
                            <input type="text" id="period_name" name="period_name" class="form-control @error('period_name') is-invalid @enderror" 
                                   value="{{ old('period_name', $period->period_name) }}" required>
                            <small class="form-text text-muted">Auto-formatted as MM/DD/YYYY to MM/DD/YYYY based on selected dates</small>
                            @error('period_name')
                                <div class="invalid-feedback text-danger">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group mb-3">
                            <label class="form-label" style="font-size: 13px; font-weight: bold; color: #555;">Pay Date <span class="text-danger">*</span></label>
                            <input type="date" id="pay_date" name="pay_date" class="form-control @error('pay_date') is-invalid @enderror" 
                                   value="{{ old('pay_date', $period->pay_date->format('Y-m-d')) }}" required>
                            @error('pay_date')
                                <div class="invalid-feedback text-danger">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group mb-4">
                            <label class="form-label" style="font-size: 13px; font-weight: bold; color: #555;">Status <span class="text-danger">*</span></label>
                            <select name="status" class="form-control @error('status') is-invalid @enderror" required>
                                <option value="open" {{ $period->status == 'open' ? 'selected' : '' }}>Open</option>
                                <option value="closed" {{ $period->status == 'closed' ? 'selected' : '' }}>Closed</option>
                                <option value="completed" {{ $period->status == 'completed' ? 'selected' : '' }}>Completed</option>
                            </select>
                            @error('status')
                                <div class="invalid-feedback text-danger">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="d-flex gap-2 mt-4">
                            <button type="submit" class="btn btn-primary" style="background: linear-gradient(135deg, #1f618d, #2980b9); border: none; padding: 8px 24px; font-weight: bold; color: #fff;">
                                <i class="mdi mdi-check"></i> Update Period
                            </button>
                            <a href="{{ route('admin.payroll-periods.index') }}" class="btn btn-secondary" style="background-color: #6c757d; border: none; padding: 8px 24px; font-weight: bold; color: #fff; text-decoration: none;">
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const startDateInput = document.getElementById('period_start');
            const endDateInput = document.getElementById('period_end');
            const periodNameInput = document.getElementById('period_name');
            const payDateInput = document.getElementById('pay_date');

            let lastAutoFilledEndDate = endDateInput ? endDateInput.value : '';
            let isPayDateManuallyEdited = false;

            function formatDate(dateString) {
                if (!dateString) return '';
                const parts = dateString.split('-');
                if (parts.length !== 3) return '';
                const [year, month, day] = parts;
                return `${month}/${day}/${year}`;
            }

            function updatePeriodName() {
                if (!startDateInput || !endDateInput || !periodNameInput) return;
                const startVal = startDateInput.value;
                const endVal = endDateInput.value;

                if (startVal && endVal) {
                    const formattedStart = formatDate(startVal);
                    const formattedEnd = formatDate(endVal);
                    periodNameInput.value = `${formattedStart} to ${formattedEnd}`;
                }
            }

            function updatePayDate() {
                if (!endDateInput || !payDateInput) return;
                const endVal = endDateInput.value;
                const currentPayDate = payDateInput.value;

                if (!isPayDateManuallyEdited || !currentPayDate || currentPayDate === lastAutoFilledEndDate) {
                    payDateInput.value = endVal;
                    lastAutoFilledEndDate = endVal;
                }
            }

            if (payDateInput) {
                payDateInput.addEventListener('input', function() {
                    if (payDateInput.value !== lastAutoFilledEndDate) {
                        isPayDateManuallyEdited = true;
                    }
                });
                payDateInput.addEventListener('change', function() {
                    if (payDateInput.value !== lastAutoFilledEndDate) {
                        isPayDateManuallyEdited = true;
                    }
                });
            }

            if (startDateInput) {
                startDateInput.addEventListener('change', updatePeriodName);
                startDateInput.addEventListener('input', updatePeriodName);
            }

            if (endDateInput) {
                endDateInput.addEventListener('change', function() {
                    updatePeriodName();
                    updatePayDate();
                });
                endDateInput.addEventListener('input', function() {
                    updatePeriodName();
                    updatePayDate();
                });
            }
        });
    </script>
@endsection
