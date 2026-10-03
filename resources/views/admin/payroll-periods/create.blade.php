@extends('admin.layouts.Master')
@section('title') Create Payroll Period @endsection
@section('content')
@php
    $isSuperAdmin = (auth()->user()->role === 'admin' || auth()->user()->role_id === \App\Models\Role::SUPER_ADMIN);
@endphp

<div class="col-12">
    <div class="row">
        <div class="col-md-8">
            <div class="card mt-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">Create Payroll Period</h4>
                    <a href="{{ panelRoute('payroll-periods.index') }}" class="btn btn-secondary" style="background-color: #6c757d; border: none; padding: 8px 18px; font-weight: bold; color: #fff;">
                        <i class="mdi mdi-arrow-left"></i> Back to List
                    </a>
                </div>
                <div class="card-body">
                    <form action="{{ panelRoute('payroll-periods.store') }}" method="POST">
                        @csrf
                        
                        @if($isSuperAdmin)
                            <div class="form-group mb-3">
                                <label class="form-label" style="font-size: 13px; font-weight: bold; color: #555;">Company <span class="text-danger">*</span></label>
                                <select name="company_id" id="company_id" class="form-control @error('company_id') is-invalid @enderror" onchange="onCompanyChange(this.value)" required>
                                    <option value="">Select Company</option>
                                    @foreach($companies as $company)
                                        <option value="{{ $company->id }}" {{ old('company_id', $companyId) == $company->id ? 'selected' : '' }}>
                                            {{ $company->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('company_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        @else
                            <input type="hidden" name="company_id" id="company_id" value="{{ getActiveCompanyId() ?? auth()->user()->company_id }}">
                        @endif

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="form-group">
                                    <label class="form-label" style="font-size: 13px; font-weight: bold; color: #555;">Start Date <span class="text-danger">*</span></label>
                                    <input type="date" id="period_start" name="period_start" class="form-control @error('period_start') is-invalid @enderror" 
                                           value="{{ old('period_start', $initialDefaults['start'] ?? '') }}" required>
                                    @error('period_start')
                                        <div class="invalid-feedback text-danger">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-group">
                                    <label class="form-label" style="font-size: 13px; font-weight: bold; color: #555;">End Date <span class="text-danger">*</span></label>
                                    <input type="date" id="period_end" name="period_end" class="form-control @error('period_end') is-invalid @enderror" 
                                           value="{{ old('period_end', $initialDefaults['end'] ?? '') }}" required>
                                    @error('period_end')
                                        <div class="invalid-feedback text-danger">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="form-group mb-3">
                            <label class="form-label" style="font-size: 13px; font-weight: bold; color: #555;">Period Name <span class="text-danger">*</span></label>
                            <input type="text" id="period_name" name="period_name" class="form-control @error('period_name') is-invalid @enderror" 
                                   placeholder="Auto-generated from date range (e.g. 07/15/2026 to 07/31/2026)" value="{{ old('period_name', $initialDefaults['name'] ?? '') }}" required>
                            <small class="form-text text-muted">Auto-formatted as MM/DD/YYYY to MM/DD/YYYY based on selected dates</small>
                            @error('period_name')
                                <div class="invalid-feedback text-danger">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group mb-3">
                            <label class="form-label" style="font-size: 13px; font-weight: bold; color: #555;">Pay Date <span class="text-danger">*</span></label>
                            <input type="date" id="pay_date" name="pay_date" class="form-control @error('pay_date') is-invalid @enderror" 
                                   value="{{ old('pay_date', $initialDefaults['pay_date'] ?? '') }}" required>
                            <small class="form-text text-muted">Date when employees will receive payment (auto-synced with End Date, editable)</small>
                            @error('pay_date')
                                <div class="invalid-feedback text-danger">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group mb-4">
                            <label class="form-label" style="font-size: 13px; font-weight: bold; color: #555;">Status <span class="text-danger">*</span></label>
                            <select name="status" class="form-control @error('status') is-invalid @enderror" required>
                                <option value="open" {{ old('status') == 'open' ? 'selected' : '' }}>Open</option>
                                <option value="closed" {{ old('status') == 'closed' ? 'selected' : '' }}>Closed</option>
                                <option value="completed" {{ old('status') == 'completed' ? 'selected' : '' }}>Completed</option>
                            </select>
                            @error('status')
                                <div class="invalid-feedback text-danger">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="d-flex gap-2 mt-4">
                            <button type="submit" class="btn btn-primary" style="background: linear-gradient(135deg, #1f618d, #2980b9); border: none; padding: 8px 24px; font-weight: bold; color: #fff;">
                                <i class="mdi mdi-check"></i> Create Period
                            </button>
                            <a href="{{ panelRoute('payroll-periods.index') }}" class="btn btn-secondary" style="background-color: #6c757d; border: none; padding: 8px 24px; font-weight: bold; color: #fff; text-decoration: none;">
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
            const companyLastPeriods = @json($companyLastPeriods ?? []);

            function onCompanyChange(companyId) {
                if (!companyId || !companyLastPeriods[companyId]) return;
                const defaults = companyLastPeriods[companyId];
                const startDateInput = document.getElementById('period_start');
                const endDateInput = document.getElementById('period_end');
                const periodNameInput = document.getElementById('period_name');
                const payDateInput = document.getElementById('pay_date');

                if (startDateInput) startDateInput.value = defaults.start;
                if (endDateInput) endDateInput.value = defaults.end;
                if (periodNameInput) periodNameInput.value = defaults.name;
                if (payDateInput) payDateInput.value = defaults.pay_date;
            }

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

                function addDaysToDate(dateString, days) {
                    if (!dateString) return '';
                    const parts = dateString.split('-');
                    if (parts.length !== 3) return '';
                    const year = parseInt(parts[0], 10);
                    const month = parseInt(parts[1], 10) - 1;
                    const day = parseInt(parts[2], 10);
                    const d = new Date(year, month, day);
                    d.setDate(d.getDate() + days);
                    const yyyy = d.getFullYear();
                    const mm = String(d.getMonth() + 1).padStart(2, '0');
                    const dd = String(d.getDate()).padStart(2, '0');
                    return `${yyyy}-${mm}-${dd}`;
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

                if (startDateInput) {
                    startDateInput.addEventListener('change', function() {
                        if (startDateInput.value && (!endDateInput.value || endDateInput.dataset.autoFilled === 'true')) {
                            const calculatedEnd = addDaysToDate(startDateInput.value, 15);
                            if (calculatedEnd) {
                                endDateInput.value = calculatedEnd;
                                endDateInput.dataset.autoFilled = 'true';
                            }
                        }
                        updatePeriodName();
                        updatePayDate();
                    });
                    startDateInput.addEventListener('input', updatePeriodName);
                }

                if (endDateInput) {
                    endDateInput.addEventListener('change', function() {
                        endDateInput.dataset.autoFilled = 'false';
                        updatePeriodName();
                        updatePayDate();
                    });
                    endDateInput.addEventListener('input', function() {
                        updatePeriodName();
                        updatePayDate();
                    });
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
            });
        </script>

        <div class="col-md-4">
            <div class="card mt-4">
                <div class="card-header">
                    <h4 class="mb-0">Period Guidelines</h4>
                </div>
                <div class="card-body">
                    <p class="text-muted small">Common payroll period setups:</p>
                    
                    <div class="mb-3">
                        <strong>Bi-Monthly (Semi-Monthly):</strong>
                        <ul class="small mt-1 text-muted">
                            <li>1st-15th of month</li>
                            <li>16th-End of month</li>
                        </ul>
                    </div>

                    <div class="mb-3">
                        <strong>Monthly:</strong>
                        <ul class="small mt-1 text-muted">
                            <li>1st to last day of month</li>
                        </ul>
                    </div>

                    <div class="mb-3">
                        <strong>Status Meaning:</strong>
                        <ul class="list-unstyled mt-2 small">
                            <li class="mb-2">
                                <span class="badge bg-success me-1">Open</span> - Accept new payroll entries
                            </li>
                            <li class="mb-2">
                                <span class="badge bg-warning text-dark me-1">Closed</span> - No new entries, can edit
                            </li>
                            <li>
                                <span class="badge bg-info me-1">Completed</span> - Finalized, no changes
                            </li>
                        </ul>
                    </div>

                    <div class="alert alert-info mt-4" style="border: none;">
                        <strong>Tip:</strong> Use "Quick Create" button on list page to create multiple periods at once!
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
