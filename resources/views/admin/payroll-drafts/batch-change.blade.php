@extends('admin.layouts.Master')
@section('title') Make Batch Changes @endsection
@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card mt-4">
                <div class="card-header d-flex justify-content-between align-items-center py-2">
                    <h4 class="mb-0 font-weight-bold">
                        <i class="mdi mdi-layers-plus me-1 text-primary"></i> Make Batch Changes
                    </h4>
                    <a href="{{ panelRoute('payroll-drafts.index') }}" class="btn btn-secondary btn-sm" style="background-color: #6c757d; border: none; padding: 6px 14px; font-weight: bold; color: #fff;">
                        <i class="mdi mdi-arrow-left me-1"></i> Back to Payroll Drafts
                    </a>
                </div>
                <div class="card-body p-3">
                    <div class="alert alert-info py-2 px-3 mb-3">
                        <h6 class="font-weight-bold mb-1"><i class="mdi mdi-information me-1"></i> Batch Income & Deduction Additions</h6>
                        <p class="small mb-0">Add one or multiple Income and/or Deduction types to selected employees with existing drafts in this period. Changes are applied atomically in a single transaction.</p>
                    </div>

                    @if(session('generation_errors') && is_array(session('generation_errors')) && count(session('generation_errors')) > 0)
                        <div class="alert alert-danger mb-3 shadow-sm py-2 px-3" style="border-left: 4px solid #dc3545;">
                            <h6 class="font-weight-bold mb-1 text-danger">
                                <i class="mdi mdi-alert-circle me-1"></i> Batch Changes Blocked ({{ count(session('generation_errors')) }} {{ count(session('generation_errors')) === 1 ? 'issue' : 'issues' }} found)
                            </h6>
                            <ul class="mb-0 ps-3">
                                @foreach(session('generation_errors') as $err)
                                    <li class="small font-weight-semibold">{{ $err }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if(session('statusDanger'))
                        <div class="alert alert-danger mb-3 py-2 px-3">
                            <i class="mdi mdi-alert-circle me-1"></i> {{ session('statusDanger') }}
                        </div>
                    @endif

                    @if(session('status'))
                        <div class="alert alert-success mb-3 py-2 px-3">
                            <i class="mdi mdi-check-circle me-1"></i> {{ session('status') }}
                        </div>
                    @endif

                    @if(auth()->user()->role === 'admin' && isset($companies) && $companies->count() > 0)
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="company_id_select" class="form-label font-weight-bold">Select Company <span class="text-danger">*</span></label>
                                <select name="company_id" id="company_id_select" class="form-control" onchange="window.location.href='{{ panelRoute('payroll-drafts.batch-change.create') }}?company_id=' + this.value">
                                    @foreach($companies as $company)
                                        <option value="{{ $company->id }}" {{ $selectedCompanyId == $company->id ? 'selected' : '' }}>
                                            {{ $company->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    @endif

                    <form action="{{ panelRoute('payroll-drafts.batch-change.store') }}" method="POST" id="batch_change_form">
                        @csrf
                        <input type="hidden" name="company_id" value="{{ $selectedCompanyId }}">

                        {{-- Payroll Period --}}
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="payroll_period_id" class="form-label font-weight-bold">Payroll Period <span class="text-danger">*</span></label>
                                <select name="payroll_period_id" id="payroll_period_id"
                                        class="form-control @error('payroll_period_id') is-invalid @enderror"
                                        required
                                        onchange="changeBatchPeriod(this.value)"
                                        {{ $payrollPeriods->isEmpty() ? 'disabled' : '' }}>
                                    <option value="">Select Payroll Period</option>
                                    @foreach($payrollPeriods as $period)
                                        <option value="{{ $period->id }}" {{ (string) old('payroll_period_id', $selectedPeriodId) === (string) $period->id ? 'selected' : '' }}>
                                            {{ $period->period_name }} ({{ date('M d', strtotime($period->period_start)) }} to {{ date('M d, Y', strtotime($period->period_end)) }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('payroll_period_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        {{-- Configuration Tabs --}}
                        @if($hasIncomeInputPermission && $hasDeductionInputPermission)
                            <div class="d-flex align-items-center gap-2 mb-3">
                                <button type="button" id="tab_btn_incomes" onclick="switchBatchConfigTab('incomes')"
                                        class="btn btn-md font-weight-bold shadow-sm"
                                        style="border-radius: 8px; border: 2px solid {{ $activeTab === 'incomes' ? '#10b981' : '#cbd5e1' }}; background-color: {{ $activeTab === 'incomes' ? '#10b981' : '#ffffff' }}; color: {{ $activeTab === 'incomes' ? '#ffffff' : '#475569' }}; min-width: 190px;">
                                    <i class="mdi mdi-cash-plus me-1"></i> Income Configuration
                                </button>
                                <button type="button" id="tab_btn_deductions" onclick="switchBatchConfigTab('deductions')"
                                        class="btn btn-md font-weight-bold shadow-sm"
                                        style="border-radius: 8px; border: 2px solid {{ $activeTab === 'deductions' ? '#f06115' : '#cbd5e1' }}; background-color: {{ $activeTab === 'deductions' ? '#f06115' : '#ffffff' }}; color: {{ $activeTab === 'deductions' ? '#ffffff' : '#475569' }}; min-width: 190px;">
                                    <i class="mdi mdi-tune me-1"></i> Deduction Configuration
                                </button>
                            </div>
                        @endif

                        {{-- Income Configuration Card --}}
                        @if($hasIncomeInputPermission)
                            <div id="card_income_config" class="card border mb-3 shadow-sm bg-white"
                                 style="border: 1px solid #e9ecef !important; border-top: 3px solid #10b981 !important; display: {{ $activeTab === 'incomes' ? 'block' : 'none' }};">
                                <div class="card-body p-3">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <h6 class="font-weight-bold text-dark mb-0">
                                            <i class="mdi mdi-cash-plus text-success me-1"></i> Select & Configure Incomes
                                        </h6>
                                    </div>
                                    <p class="text-muted small mb-2">Check the income types to add. Configure units and rates if needed.</p>

                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered align-middle mb-0" style="max-width: {{ $hasRateVisibilityPermission ? '720px' : '540px' }};">
                                            <thead class="table-light">
                                                <tr>
                                                    <th style="width: 50px;" class="text-center">Select</th>
                                                    <th style="width: {{ $hasRateVisibilityPermission ? '280px' : '340px' }};">Income Type</th>
                                                    <th style="width: 150px;">Number Input</th>
                                                    @if($hasRateVisibilityPermission)
                                                        <th style="width: 180px;">Rate (₱)</th>
                                                    @endif
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse($allCompanyIncomeTypes as $type)
                                                    @php
                                                        $isSelected = in_array((int)$type->id, array_map('intval', old('income_type_ids', [])));
                                                        $oldCfg = old("income_type_configs.{$type->id}", []);
                                                    @endphp
                                                    <tr class="income-type-config-row">
                                                        <td class="text-center">
                                                            <input type="checkbox" name="income_type_ids[]" value="{{ $type->id }}"
                                                                   id="income_type_chk_{{ $type->id }}"
                                                                   class="form-check-input income-type-chk"
                                                                   {{ $isSelected ? 'checked' : '' }}
                                                                   onchange="syncIncomeConfigInputs()">
                                                        </td>
                                                        <td>
                                                            <label for="income_type_chk_{{ $type->id }}" class="mb-0 font-weight-semibold cursor-pointer">
                                                                {{ $type->name }} ({{ $type->code }})
                                                            </label>
                                                        </td>
                                                        <td>
                                                            <input type="number" step="0.01" min="0"
                                                                   name="income_type_configs[{{ $type->id }}][number_input]"
                                                                   id="income_type_qty_{{ $type->id }}"
                                                                   class="form-control form-control-sm income-type-config-input"
                                                                   value="{{ $oldCfg['number_input'] ?? '0.00' }}"
                                                                   {{ $isSelected ? '' : 'disabled' }}
                                                                   placeholder="0.00">
                                                        </td>
                                                        @if($hasRateVisibilityPermission)
                                                            <td>
                                                                <input type="number" step="0.01" min="0"
                                                                       name="income_type_configs[{{ $type->id }}][rate]"
                                                                       id="income_type_rate_{{ $type->id }}"
                                                                       class="form-control form-control-sm income-type-config-input"
                                                                       value="{{ $oldCfg['rate'] ?? '' }}"
                                                                       {{ $isSelected ? '' : 'disabled' }}
                                                                       placeholder="Leave blank for default">
                                                            </td>
                                                        @endif
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="{{ $hasRateVisibilityPermission ? 4 : 3 }}" class="text-center text-muted py-2 small">
                                                            No active income types available.
                                                        </td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        @endif

                        {{-- Deduction Configuration Card --}}
                        @if($hasDeductionInputPermission)
                            <div id="card_deduction_config" class="card border mb-3 shadow-sm bg-white"
                                 style="border: 1px solid #e9ecef !important; border-top: 3px solid #f06115 !important; display: {{ $activeTab === 'deductions' ? 'block' : 'none' }};">
                                <div class="card-body p-3">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <h6 class="font-weight-bold text-dark mb-0">
                                            <i class="mdi mdi-tune text-primary me-1" style="color: #f06115 !important;"></i> Select & Configure Deductions
                                        </h6>
                                    </div>
                                    <p class="text-muted small mb-2">Check the deduction types to add. Configure quantity and amounts if needed.</p>

                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered align-middle mb-0" style="max-width: 760px;">
                                            <thead class="table-light">
                                                <tr>
                                                    <th style="width: 50px;" class="text-center">Select</th>
                                                    <th style="width: 320px;">Deduction Type</th>
                                                    <th style="width: 150px;">Quantity</th>
                                                    <th style="width: 180px;">Rate / Amount (₱)</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse($allCompanyDeductionTypes as $type)
                                                    @php
                                                        $isSelected = in_array((int)$type->id, array_map('intval', old('deduction_type_ids', [])));
                                                        $oldCfg = old("deduction_type_configs.{$type->id}", []);
                                                        $isLoan = $type->isLoanBacked();
                                                    @endphp
                                                    <tr class="deduction-type-config-row">
                                                        <td class="text-center">
                                                            <input type="checkbox" name="deduction_type_ids[]" value="{{ $type->id }}"
                                                                   id="deduction_type_chk_{{ $type->id }}"
                                                                   class="form-check-input deduction-type-chk"
                                                                   {{ $isSelected ? 'checked' : '' }}
                                                                   onchange="syncDeductionConfigInputs()">
                                                        </td>
                                                        <td>
                                                            <label for="deduction_type_chk_{{ $type->id }}" class="mb-0 font-weight-semibold cursor-pointer">
                                                                {{ $type->name }} ({{ $type->code }})
                                                            </label>
                                                            @if($isLoan)
                                                                <span class="badge bg-warning text-dark ms-1" style="font-size: 10px;">Loan</span>
                                                            @endif
                                                        </td>
                                                        <td>
                                                            @if($isLoan)
                                                                <input type="number" step="1" min="1" max="1"
                                                                       class="form-control form-control-sm"
                                                                       value="1" readonly disabled title="Loan quantity is fixed to 1">
                                                            @else
                                                                <input type="number" step="0.01" min="0"
                                                                       name="deduction_type_configs[{{ $type->id }}][number_input]"
                                                                       id="deduction_type_qty_{{ $type->id }}"
                                                                       class="form-control form-control-sm deduction-type-config-input"
                                                                       value="{{ $oldCfg['number_input'] ?? '0.00' }}"
                                                                       {{ $isSelected ? '' : 'disabled' }}
                                                                       placeholder="0.00">
                                                            @endif
                                                        </td>
                                                        <td>
                                                            @if($isLoan)
                                                                <input type="text"
                                                                       class="form-control form-control-sm text-muted bg-light"
                                                                       value="Auto (Active Loan / Cutoff)"
                                                                       readonly
                                                                       disabled
                                                                       title="Amount is calculated automatically from active loan schedules">
                                                            @else
                                                                <input type="number" step="0.01" min="0"
                                                                       name="deduction_type_configs[{{ $type->id }}][rate]"
                                                                       id="deduction_type_rate_{{ $type->id }}"
                                                                       class="form-control form-control-sm deduction-type-config-input"
                                                                       value="{{ $oldCfg['rate'] ?? '' }}"
                                                                       {{ $isSelected ? '' : 'disabled' }}
                                                                       placeholder="Leave blank for default">
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="4" class="text-center text-muted py-2 small">
                                                            No active deduction types available.
                                                        </td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        @endif

                        {{-- Target Employees --}}
                        <div class="card border mb-3 shadow-sm bg-white" style="border: 1px solid #e9ecef !important;">
                            <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                                <h6 class="font-weight-bold text-dark mb-0">
                                    <i class="mdi mdi-account-multiple-check me-1 text-primary"></i> Select Target Employees
                                    <span class="badge bg-primary text-white ms-2" id="selected_count_badge">0 selected</span>
                                </h6>
                                <div class="col-md-4 col-sm-6">
                                    <input type="text" id="employee_search_input" class="form-control form-control-sm"
                                           placeholder="Search employee name or code..." onkeyup="filterDraftEmployees(this.value)">
                                </div>
                            </div>
                            <div class="card-body p-3">
                                @if($eligibleDrafts->isEmpty())
                                    <div class="alert alert-warning mb-0 py-2">
                                        <i class="mdi mdi-alert-circle me-1"></i> No unfinalized payroll drafts found for this period.
                                    </div>
                                @else
                                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                                        <table class="table table-sm table-hover table-bordered align-middle mb-0" id="drafts_table">
                                            <thead class="table-light sticky-top">
                                                <tr>
                                                    <th style="width: 45px;" class="text-center">
                                                        <input type="checkbox" id="select_all_drafts" class="form-check-input" onchange="toggleSelectAllDrafts(this)">
                                                    </th>
                                                    <th style="width: 140px;">Employee Code</th>
                                                    <th>Employee Name</th>
                                                    <th style="width: 180px;">Department</th>
                                                    <th style="width: 130px;" class="text-center">Draft Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($eligibleDrafts as $draft)
                                                    @php
                                                        $emp = $draft->employee;
                                                        $isDraftChecked = in_array((int)$draft->id, array_map('intval', old('draft_ids', [])));
                                                        $statusClass = match($draft->status) {
                                                            'draft' => 'bg-secondary text-white',
                                                            'rejected' => 'bg-danger text-white',
                                                            'pending_approval' => 'bg-warning text-dark',
                                                            'approved' => 'bg-success text-white',
                                                            default => 'bg-secondary text-white',
                                                        };
                                                    @endphp
                                                    <tr class="draft-row" data-emp-name="{{ strtolower($emp?->full_name ?? '') }}" data-emp-code="{{ strtolower($emp?->employee_code ?? '') }}">
                                                        <td class="text-center">
                                                            <input type="checkbox" name="draft_ids[]" value="{{ $draft->id }}"
                                                                   id="draft_chk_{{ $draft->id }}"
                                                                   class="form-check-input draft-item-chk"
                                                                   {{ $isDraftChecked ? 'checked' : '' }}
                                                                   onchange="updateSelectedDraftsCount()">
                                                        </td>
                                                        <td>
                                                            <label for="draft_chk_{{ $draft->id }}" class="mb-0 font-weight-bold cursor-pointer">
                                                                {{ $emp?->employee_code ?? 'EMP-' . $draft->employee_id }}
                                                            </label>
                                                        </td>
                                                        <td>
                                                            <label for="draft_chk_{{ $draft->id }}" class="mb-0 cursor-pointer">
                                                                {{ $emp?->full_name ?? 'N/A' }}
                                                            </label>
                                                        </td>
                                                        <td>{{ $emp?->dept?->name ?? 'N/A' }}</td>
                                                        <td class="text-center">
                                                            <span class="badge {{ $statusClass }}">
                                                                {{ ucwords(str_replace('_', ' ', $draft->status)) }}
                                                            </span>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- Action Buttons --}}
                        <div class="d-flex align-items-center justify-content-between mt-3">
                            <a href="{{ panelRoute('payroll-drafts.index', ['payroll_period_id' => $selectedPeriodId]) }}" class="btn btn-outline-secondary">
                                Cancel
                            </a>
                            <button type="submit" class="btn btn-primary font-weight-bold px-4" id="submit_batch_btn" {{ $eligibleDrafts->isEmpty() ? 'disabled' : '' }}>
                                <i class="mdi mdi-check-all me-1"></i> Apply Batch Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

@include('admin.payroll-drafts._batch_change_scripts')
@endsection
