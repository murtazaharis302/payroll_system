@extends('admin.layouts.Master')
@section('title') Payroll Drafts (HR Input) @endsection
@section('content')
    <div class="col-12">
        <div class="card mt-4">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h4 class="mb-0">Payroll Drafts - HR Input</h4>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    @php
                        $hasBatchInputAccess = \App\Models\MenuRoleAccess::hasModuleAccess(auth()->user(), 'payroll_income_inputs')
                            || \App\Models\MenuRoleAccess::hasModuleAccess(auth()->user(), 'payroll_deduction_inputs');
                    @endphp
                    @if($hasBatchInputAccess)
                        <a href="{{ panelRoute('payroll-drafts.batch-change.create', array_filter(['payroll_period_id' => request('payroll_period_id') ?? ($selectedPayrollPeriod?->id ?? null)])) }}" class="btn btn-warning flex-shrink-0" style="background: linear-gradient(135deg, #d35400, #e67e22); border: none; padding: 8px 18px; font-weight: bold; color: #fff; width: auto; white-space: nowrap;">
                            <i class="mdi mdi-playlist-edit"></i> Make Batch Changes
                        </a>
                    @endif
                    <a href="{{ route('payroll-drafts.create') }}" class="btn btn-primary flex-shrink-0" style="background: linear-gradient(135deg, #1f618d, #2980b9); border: none; padding: 8px 18px; font-weight: bold; color: #fff; width: auto; white-space: nowrap;">
                        <i class="mdi mdi-flash"></i> Generate Drafts
                    </a>
                    @if(isset($selectedPayrollPeriod) && $selectedPayrollPeriod?->status === 'open')
                        <a href="{{ panelRoute('payroll-drafts.add-employee.create', ['payroll_period_id' => $selectedPayrollPeriod->id]) }}" class="btn btn-success flex-shrink-0" style="padding: 8px 18px; font-weight: bold; color: #fff; width: auto; white-space: nowrap;">
                            <i class="mdi mdi-account-plus"></i> Add Employee
                        </a>
                    @endif
                </div>
            </div>
            <div class="card-body">
                @if(session('generation_errors') && is_array(session('generation_errors')) && count(session('generation_errors')) > 0)
                    <div class="alert alert-danger mb-4 shadow-sm" style="border-left: 4px solid #dc3545;">
                        <h5 class="font-weight-bold mb-2">
                            <i class="fa fa-exclamation-circle me-1"></i> Payroll Changes Blocked ({{ count(session('generation_errors')) }} {{ count(session('generation_errors')) === 1 ? 'issue' : 'issues' }} found)
                        </h5>
                        <p class="mb-2 small">The following setup issues must be resolved before payroll changes can be applied:</p>
                        <ul class="mb-0 ps-3">
                            @foreach(session('generation_errors') as $genError)
                                <li class="small font-weight-semibold">{{ $genError }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                <!-- Filters -->
                <form method="GET" action="{{ route('payroll-drafts.index') }}" class="mb-4">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label class="form-label" style="font-size: 11px; font-weight: bold; color: #555;">Payroll Period</label>
                            <select name="payroll_period_id" class="form-control">
                                <option value="">All Payroll Periods</option>
                                @foreach($payrollPeriods as $period)
                                    <option value="{{ $period->id }}" {{ request('payroll_period_id') == $period->id ? 'selected' : '' }}>
                                        {{ $period->period_name }} ({{ date('M d', strtotime($period->period_start)) }} - {{ date('M d, Y', strtotime($period->period_end)) }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label" style="font-size: 11px; font-weight: bold; color: #555;">Employee Name</label>
                            <select name="employee_id" id="employee_id" class="form-control select2-employee-search" data-placeholder="All Employees" data-company-id="{{ $selectedCompanyId ?? '' }}">
                                <option value="">All Employees</option>
                                @if(isset($employees) && $employees->count() > 0)
                                    @foreach($employees as $emp)
                                        @php
                                            $empCode = $emp->employee_code ?? ('EMP-' . $emp->id);
                                            $fullName = $emp->last_name . ', ' . $emp->first_name;
                                        @endphp
                                        <option value="{{ $emp->id }}" {{ (string)($selectedEmployeeId ?? request('employee_id')) === (string)$emp->id ? 'selected' : '' }}>
                                            [{{ $empCode }}] {{ $fullName }}
                                        </option>
                                    @endforeach
                                @endif
                            </select>
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label" style="font-size: 11px; font-weight: bold; color: #555;">Department</label>
                            <select name="dept_id" class="form-control">
                                <option value="">All Departments</option>
                                @if(isset($departments) && $departments->count() > 0)
                                    @foreach($departments as $dept)
                                        <option value="{{ $dept->id }}" {{ (string)($selectedDeptId ?? request('dept_id')) === (string)$dept->id ? 'selected' : '' }}>
                                            {{ $dept->name }} ({{ $dept->code }})
                                        </option>
                                    @endforeach
                                @endif
                            </select>
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label" style="font-size: 11px; font-weight: bold; color: #555;">Status</label>
                            <select name="status" class="form-control">
                                <option value="">All Status</option>
                                <option value="draft" {{ request('status') == 'draft' ? 'selected' : '' }}>Draft</option>
                                <option value="pending_approval" {{ request('status') == 'pending_approval' ? 'selected' : '' }}>Pending Approval</option>
                                <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Approved</option>
                                <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Rejected</option>
                            </select>
                        </div>
                        <div class="col-md-2 mb-3 d-flex align-items-end">
                            <div class="d-flex gap-2 w-100">
                                <button type="submit" class="btn btn-primary w-50" style="background: linear-gradient(135deg, #1f618d, #2980b9); border: none; padding: 8px 18px; font-weight: bold; color: #fff;">
                                    Filter
                                </button>
                                <a href="{{ route('payroll-drafts.index') }}" class="btn btn-secondary w-50" style="background-color: #6c757d; border: none; padding: 8px 18px; font-weight: bold; color: #fff; text-decoration: none; text-align: center;">
                                    Clear
                                </a>
                            </div>
                        </div>
                    </div>
                </form>

                @php
                    $isHr = $isHr ?? (auth()->user()->role === 'hr' || auth()->user()->role_id === \App\Models\Role::HR);
                    $canViewIncomeColumn = $canViewIncomeColumn ?? \App\Models\MenuRoleAccess::hasModuleAccess(auth()->user(), 'payroll_income_column_visibility');
                    $canViewNetPay = !$isHr;
                    $negativeCount = (isset($drafts) && $drafts->count() > 0) ? $drafts->filter(fn($d) => (float)$d->net_pay < 0)->count() : 0;
                @endphp
                @if($negativeCount > 0)
                    <div class="alert alert-danger font-13 fw-bold mb-3 shadow-sm" style="border-left: 4px solid #dc3545;">
                        <i class="mdi mdi-alert-circle font-18 me-1 align-middle"></i>
                        @if($isHr)
                            Warning: {{ $negativeCount }} employee payroll draft(s) have negative net income requiring attendance or deduction adjustments.
                        @else
                            Warning: {{ $negativeCount }} employee payroll draft(s) have a negative net income (Deductions > Gross Pay). Please adjust income or deductions before submitting or approving.
                        @endif
                    </div>
                @endif

                <!-- Batch Actions -->
                @if($drafts->whereIn('status', ['draft', 'rejected'])->count() > 0)
                <div class="d-flex gap-2 mb-4">
                    <form id="batchSubmitForm" action="{{ panelRoute('payroll-drafts.batch-submit') }}" method="POST" class="d-inline" onsubmit="return validateBatchSubmit(this)">
                        @csrf
                        <button type="submit" class="btn btn-success" style="padding: 8px 20px; font-weight: bold;">
                            <i class="fa fa-check-circle"></i> Batch Submit for Approval
                        </button>
                    </form>
                    <form id="batchDestroyForm" action="{{ panelRoute('payroll-drafts.batch-destroy') }}" method="POST" class="d-inline" onsubmit="return validateBatchDestroy(this)">
                        @csrf
                        <button type="submit" class="btn btn-danger" style="padding: 8px 20px; font-weight: bold;">
                            <i class="fa fa-trash"></i> Delete Selected Drafts
                        </button>
                    </form>
                </div>
                @endif

                <style>
                    #myTable th, #myTable td {
                        vertical-align: middle !important;
                        white-space: nowrap !important;
                    }
                </style>
                <div class="table-responsive">
                    <table class="table table-striped align-middle" id="myTable">
                        <thead>
                        <tr>
                            <th width="30"><input type="checkbox" id="select-all"></th>
                            <th scope="col">Employee</th>
                            <th scope="col">Period</th>
                            <th scope="col">Days</th>
                            @if(!empty($canViewIncomeColumn))
                                <th scope="col">Income</th>
                            @endif
                            <th scope="col">Deductions</th>
                            @if(!empty($canViewNetPay))
                                <th scope="col">Net Pay</th>
                            @endif
                            <th scope="col">Status</th>
                            <th scope="col">Action</th>
                        </tr>
                        </thead>
                        <tbody>
                            @if(isset($drafts) && $drafts->count() > 0)
                                @foreach ($drafts as $draft)
                                    <tr class="{{ (float)$draft->net_pay < 0 ? 'table-danger' : '' }}">
                                        <td>
                                            @if(in_array($draft->status, ['draft', 'rejected']))
                                                <input type="checkbox" name="draft_ids[]" value="{{ $draft->id }}" class="draft-checkbox" form="batchSubmitForm">
                                            @endif
                                        </td>
                                        <td>{{ $draft->employee->full_name ?? 'N/A' }}</td>
                                        <td>{{ $draft->payrollPeriod->period_name ?? 'N/A' }}</td>
                                        <td>{{ number_format($draft->days_worked, 1) }}</td>
                                        @if(!empty($canViewIncomeColumn))
                                            <td>₱ {{ number_format($draft->total_gross_pay, 2) }}</td>
                                        @endif

                                        <td>₱ {{ number_format($draft->total_deductions, 2) }}</td>
                                        @if(!empty($canViewNetPay))
                                            <td>
                                                <strong class="{{ (float)$draft->net_pay < 0 ? 'text-danger' : '' }}">
                                                    ₱ {{ number_format($draft->net_pay, 2) }}
                                                    @if((float)$draft->net_pay < 0)
                                                        <span class="badge bg-danger ms-1" title="Negative Net Income"><i class="mdi mdi-alert me-1"></i>Negative</span>
                                                    @endif
                                                </strong>
                                            </td>
                                        @endif
                                        <td>
                                            @if($draft->status == 'draft')
                                                <span class="badge bg-secondary text-white">Draft</span>
                                            @elseif($draft->status == 'pending_approval')
                                                <span class="badge bg-warning text-dark">Pending Approval</span>
                                            @elseif($draft->status == 'approved')
                                                <span class="badge bg-success text-white">Approved</span>
                                            @else
                                                <span class="badge bg-danger text-white">Rejected</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center" style="gap: 12px; min-width: 100px;">
                                                <a href="{{ route('payroll-drafts.edit', $draft->id) }}" class="text-warning" title="Edit/View" style="text-decoration: none;">
                                                    <i class="fa-solid fa-pen-to-square" style="font-size: 14px;"></i>
                                                </a>
                                                
                                                @if($draft->status == 'draft')
                                                    <form action="{{ route('payroll-drafts.submit', $draft->id) }}" method="POST" style="display:inline; margin:0;" onsubmit="return confirm('Submit this draft for approval?');">
                                                        @csrf
                                                        <button type="submit" class="btn btn-link p-0 text-success" title="Submit for Approval" style="border: none; background: none; line-height: 1;">
                                                            <i class="fa-solid fa-circle-check" style="font-size: 14px;"></i>
                                                        </button>
                                                    </form>
                                                @endif

                                                @if(in_array($draft->status, ['draft', 'rejected']))
                                                    <form action="{{ panelRoute('payroll-drafts.destroy', $draft->id) }}" method="POST" style="display:inline; margin:0;" onsubmit="return confirm('Are you sure you want to delete this payroll draft?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-link p-0 text-danger" title="Delete Draft" style="border: none; background: none; line-height: 1;">
                                                            <i class="fa-solid fa-trash-can" style="font-size: 14px;"></i>
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        </td>    
                                    </tr>
                                @endforeach
                            @else
                                @php
                                    $emptyColspan = 7 + (!empty($canViewIncomeColumn) ? 1 : 0) + (!empty($canViewNetPay) ? 1 : 0);
                                @endphp
                                <tr>
                                    <td colspan="{{ $emptyColspan }}" class="text-center">No payroll drafts found</td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                <div class="d-flex align-items-center justify-content-between flex-wrap mt-3">
                    <div>
                        {{ $drafts->links() }}
                    </div>
                    <x-per-page-selector :perPage="request('per_page', 50)" />
                </div>
        </div> 
    </div>
    <script>
        function validateBatchSubmit(form) {
            const checked = document.querySelectorAll('.draft-checkbox:checked');
            if (checked.length === 0) {
                alert('Please select at least one payroll draft using the checkboxes before submitting.');
                return false;
            }
            return confirm(`Submit ${checked.length} selected payroll draft(s) for approval?`);
        }

        function validateBatchDestroy(form) {
            const checked = document.querySelectorAll('.draft-checkbox:checked');
            if (checked.length === 0) {
                alert('Please select at least one payroll draft using the checkboxes before deleting.');
                return false;
            }
            if (!confirm(`Are you sure you want to permanently delete ${checked.length} selected payroll draft(s)?`)) {
                return false;
            }
            
            // Append selected draft_ids into batchDestroyForm dynamically
            form.querySelectorAll('input[name="draft_ids[]"]').forEach(el => el.remove());
            checked.forEach(cb => {
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'draft_ids[]';
                hiddenInput.value = cb.value;
                form.appendChild(hiddenInput);
            });
            return true;
        }

        // Select all checkboxes
        const selectAll = document.getElementById('select-all');
        if (selectAll) {
            selectAll.addEventListener('change', function() {
                const checkboxes = document.querySelectorAll('.draft-checkbox');
                checkboxes.forEach(cb => cb.checked = this.checked);
            });
        }

        var translations = {
            search: "{{ __('messages.search') }}"
        };
    </script>
@endsection
