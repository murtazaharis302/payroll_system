@extends('admin.layouts.Master')

@section('title', 'HR Adjustments & Attendance Corrections')

@section('content')
<div class="container-fluid py-3">
    <!-- Page Header & Action Triggers -->
    <div class="row align-items-center mb-4">
        <div class="col-md-6">
            <h3 class="page-title text-dark fw-bold mb-1">
                <i class="fe-sliders text-primary me-2"></i> HR Adjustments & Corrections
            </h3>
            <p class="text-muted font-13 mb-0">Manage employee attendance, income, and deduction adjustments prior to payroll generation.</p>
        </div>
        <div class="col-md-6 text-md-end mt-3 mt-md-0">
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-primary btn-sm fw-semibold shadow-sm" data-bs-toggle="modal" data-bs-target="#newAttendanceModal">
                    <i class="fe-clock me-1"></i> + Attendance Correction
                </button>
                <button type="button" class="btn btn-success btn-sm fw-semibold shadow-sm ms-1" data-bs-toggle="modal" data-bs-target="#newIncomeModal">
                    <i class="fe-plus-circle me-1"></i> + Income Adjustment
                </button>
                <button type="button" class="btn btn-warning btn-sm fw-semibold text-white shadow-sm ms-1" data-bs-toggle="modal" data-bs-target="#newDeductionModal">
                    <i class="fe-minus-circle me-1"></i> + Deduction Adjustment
                </button>
            </div>
        </div>
    </div>

    <!-- Status Metric KPI Cards -->
    <div class="row mb-4">
        <div class="col-md-4 mb-3 mb-md-0">
            <div class="card border-0 shadow-sm border-start border-4 border-warning bg-white h-100">
                <div class="card-body py-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted font-12 text-uppercase fw-semibold">Pending Review</span>
                            <h2 class="mb-0 text-dark fw-bold">{{ $pendingCount }}</h2>
                        </div>
                        <div class="avatar-md rounded-circle bg-soft-warning text-warning d-flex align-items-center justify-content-center font-22" style="width:46px; height:46px;">
                            <i class="fe-clock"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3 mb-md-0">
            <div class="card border-0 shadow-sm border-start border-4 border-success bg-white h-100">
                <div class="card-body py-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted font-12 text-uppercase fw-semibold">Approved Adjustments</span>
                            <h2 class="mb-0 text-dark fw-bold">{{ $approvedCount }}</h2>
                        </div>
                        <div class="avatar-md rounded-circle bg-soft-success text-success d-flex align-items-center justify-content-center font-22" style="width:46px; height:46px;">
                            <i class="fe-check-circle"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm border-start border-4 border-danger bg-white h-100">
                <div class="card-body py-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted font-12 text-uppercase fw-semibold">Rejected Adjustments</span>
                            <h2 class="mb-0 text-dark fw-bold">{{ $rejectedCount }}</h2>
                        </div>
                        <div class="avatar-md rounded-circle bg-soft-danger text-danger d-flex align-items-center justify-content-center font-22" style="width:46px; height:46px;">
                            <i class="fe-x-circle"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Data Table & Navigation Tabs -->
    <div class="card border-0 shadow-sm bg-white">
        <div class="card-header bg-white border-bottom py-3">
            <ul class="nav nav-tabs card-header-tabs" id="adjustmentTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active fw-bold text-dark font-13" id="tab-all-btn" data-bs-toggle="tab" data-bs-target="#tab-all" type="button" role="tab">
                        <i class="fe-list me-1"></i> All Adjustments ({{ $allAdjustments->count() }})
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-bold text-warning font-13" id="tab-pending-btn" data-bs-toggle="tab" data-bs-target="#tab-pending" type="button" role="tab">
                        <i class="fe-clock me-1"></i> Pending ({{ $pendingCount }})
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-bold text-success font-13" id="tab-approved-btn" data-bs-toggle="tab" data-bs-target="#tab-approved" type="button" role="tab">
                        <i class="fe-check-circle me-1"></i> Approved ({{ $approvedCount }})
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-bold text-danger font-13" id="tab-rejected-btn" data-bs-toggle="tab" data-bs-target="#tab-rejected" type="button" role="tab">
                        <i class="fe-x-circle me-1"></i> Rejected ({{ $rejectedCount }})
                    </button>
                </li>
            </ul>
        </div>
        <div class="card-body p-0">
            <div class="tab-content" id="adjustmentTabContent">
                
                <!-- ALL TAB -->
                <div class="tab-pane fade show active" id="tab-all" role="tabpanel">
                    @include('company.hr-adjustments.partials.table', ['items' => $allAdjustments])
                </div>

                <!-- PENDING TAB -->
                <div class="tab-pane fade" id="tab-pending" role="tabpanel">
                    @include('company.hr-adjustments.partials.table', ['items' => $allAdjustments->where('status', 'pending')])
                </div>

                <!-- APPROVED TAB -->
                <div class="tab-pane fade" id="tab-approved" role="tabpanel">
                    @include('company.hr-adjustments.partials.table', ['items' => $allAdjustments->where('status', 'approved')])
                </div>

                <!-- REJECTED TAB -->
                <div class="tab-pane fade" id="tab-rejected" role="tabpanel">
                    @include('company.hr-adjustments.partials.table', ['items' => $allAdjustments->where('status', 'rejected')])
                </div>

            </div>
        </div>
    </div>
</div>

<!-- Modal 1: New Attendance Correction -->
<div class="modal fade" id="newAttendanceModal" tabindex="-1" aria-labelledby="newAttendanceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white py-3">
                <h5 class="modal-title font-16 fw-bold" id="newAttendanceModalLabel">
                    <i class="fe-clock me-2"></i> Attendance Correction Adjustment
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ panelRoute('hr-adjustments.store') }}" method="POST">
                @csrf
                <input type="hidden" name="type" value="attendance">
                <input type="hidden" name="status" value="approved">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label font-12 fw-semibold">Employee <span class="text-danger">*</span></label>
                        <select name="employee_id" class="form-select form-select-sm" required>
                            <option value="">-- Select Employee --</option>
                            @foreach($employees as $emp)
                                <option value="{{ $emp->id }}">{{ $emp->employee_code ?? 'EMP'.$emp->id }} - {{ $emp->first_name }} {{ $emp->last_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label font-12 fw-semibold">Effective Date <span class="text-danger">*</span></label>
                        <input type="date" name="effective_date" class="form-control form-control-sm" value="{{ date('Y-m-d') }}" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label font-12 fw-semibold">Hours Worked</label>
                            <input type="number" step="0.25" min="0" name="hours_worked" class="form-control form-control-sm" value="0.00">
                        </div>
                        <div class="col-6">
                            <label class="form-label font-12 fw-semibold">Overtime Hours</label>
                            <input type="number" step="0.25" min="0" name="overtime_hours" class="form-control form-control-sm" value="0.00">
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label font-12 fw-semibold">Reason / Description <span class="text-danger">*</span></label>
                        <textarea name="description" class="form-control form-control-sm" rows="3" placeholder="Explain reason for attendance adjustment..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary fw-semibold">Submit Attendance Correction</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 2: New Income Adjustment -->
<div class="modal fade" id="newIncomeModal" tabindex="-1" aria-labelledby="newIncomeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-success text-white py-3">
                <h5 class="modal-title font-16 fw-bold" id="newIncomeModalLabel">
                    <i class="fe-plus-circle me-2"></i> Additional Income Adjustment
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ panelRoute('hr-adjustments.store') }}" method="POST">
                @csrf
                <input type="hidden" name="type" value="income">
                <input type="hidden" name="status" value="approved">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label font-12 fw-semibold">Employee <span class="text-danger">*</span></label>
                        <select name="employee_id" class="form-select form-select-sm" required>
                            <option value="">-- Select Employee --</option>
                            @foreach($employees as $emp)
                                <option value="{{ $emp->id }}">{{ $emp->employee_code ?? 'EMP'.$emp->id }} - {{ $emp->first_name }} {{ $emp->last_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label font-12 fw-semibold">Income Type <span class="text-danger">*</span></label>
                        <select name="income_type_id" class="form-select form-select-sm" required>
                            <option value="">-- Select Income Type --</option>
                            @foreach($incomeTypes as $inc)
                                <option value="{{ $inc->id }}">{{ $inc->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label font-12 fw-semibold">Effective Date <span class="text-danger">*</span></label>
                            <input type="date" name="effective_date" class="form-control form-control-sm" value="{{ date('Y-m-d') }}" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label font-12 fw-semibold">Amount (PHP) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0" name="amount" class="form-control form-control-sm" placeholder="0.00" required>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label font-12 fw-semibold">Reason / Description <span class="text-danger">*</span></label>
                        <textarea name="description" class="form-control form-control-sm" rows="3" placeholder="Explain reason for income adjustment..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-success fw-semibold">Submit Income Adjustment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 3: New Deduction Adjustment -->
<div class="modal fade" id="newDeductionModal" tabindex="-1" aria-labelledby="newDeductionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-warning text-white py-3">
                <h5 class="modal-title font-16 fw-bold" id="newDeductionModalLabel">
                    <i class="fe-minus-circle me-2"></i> Deduction Adjustment
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ panelRoute('hr-adjustments.store') }}" method="POST">
                @csrf
                <input type="hidden" name="type" value="deduction">
                <input type="hidden" name="status" value="approved">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label font-12 fw-semibold">Employee <span class="text-danger">*</span></label>
                        <select name="employee_id" class="form-select form-select-sm" required>
                            <option value="">-- Select Employee --</option>
                            @foreach($employees as $emp)
                                <option value="{{ $emp->id }}">{{ $emp->employee_code ?? 'EMP'.$emp->id }} - {{ $emp->first_name }} {{ $emp->last_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label font-12 fw-semibold">Deduction Type <span class="text-danger">*</span></label>
                        <select name="deduction_type_id" class="form-select form-select-sm" required>
                            <option value="">-- Select Deduction Type --</option>
                            @foreach($deductionTypes as $ded)
                                <option value="{{ $ded->id }}">{{ $ded->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label font-12 fw-semibold">Effective Date <span class="text-danger">*</span></label>
                            <input type="date" name="effective_date" class="form-control form-control-sm" value="{{ date('Y-m-d') }}" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label font-12 fw-semibold">Amount (PHP) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0" name="amount" class="form-control form-control-sm" placeholder="0.00" required>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label font-12 fw-semibold">Reason / Description <span class="text-danger">*</span></label>
                        <textarea name="description" class="form-control form-control-sm" rows="3" placeholder="Explain reason for deduction adjustment..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-warning text-white fw-semibold">Submit Deduction Adjustment</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
