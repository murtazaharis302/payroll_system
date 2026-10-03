@extends('admin.layouts.Master')

@section('title', 'HR Manager Operations Dashboard')

@section('content')
<div class="container-fluid">
    <!-- Page Title & Header Actions -->
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-flex align-items-center justify-content-between py-3">
                <div>
                    <h4 class="page-title mb-0">HR Manager Operations Dashboard</h4>
                    <p class="text-muted font-13 mb-0">{{ $companyData->name ?? 'Company Payroll & HR Hub' }}</p>
                </div>
                <div class="page-title-right d-flex align-items-center gap-2">
                    <a href="{{ panelRoute('payroll-drafts.create') }}" class="btn btn-primary btn-sm shadow-sm align-self-center">
                        <i class="fe-plus-circle me-1"></i> Generate Draft Payroll
                    </a>
                    <a href="{{ panelRoute('hr-adjustments.index') }}" class="btn btn-outline-secondary btn-sm shadow-sm align-self-center">
                        <i class="fe-edit me-1"></i> Add HR Adjustment
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Stat Cards Row -->
    <div class="row">
        <!-- Card 1: Active Employees -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <span class="text-muted fw-semibold font-13 text-uppercase">Active Employees</span>
                        <div class="avatar-sm rounded bg-soft-primary text-primary d-flex align-items-center justify-content-center">
                            <i class="fe-users font-18"></i>
                        </div>
                    </div>
                    <h2 class="fw-bold mb-1">{{ $totalEmployees ?? 0 }}</h2>
                    <p class="text-muted font-12 mb-0">
                        <a href="{{ panelRoute('employee') }}" class="text-primary text-decoration-none fw-semibold">
                            View Roster <i class="fe-arrow-right font-11 ms-1"></i>
                        </a>
                    </p>
                </div>
            </div>
        </div>

        <!-- Card 2: Draft Payrolls Widget -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <span class="text-muted fw-semibold font-13 text-uppercase">Draft Payrolls</span>
                        <div class="avatar-sm rounded bg-soft-info text-info d-flex align-items-center justify-content-center">
                            <i class="fe-file-minus font-18"></i>
                        </div>
                    </div>
                    <h2 class="fw-bold mb-1">{{ $draftPayrolls ?? 0 }}</h2>
                    <p class="text-muted font-12 mb-0">
                        <a href="{{ panelRoute('payroll-drafts.index') }}" class="text-info text-decoration-none fw-semibold">
                            In-progress drafts <i class="fe-arrow-right font-11 ms-1"></i>
                        </a>
                    </p>
                </div>
            </div>
        </div>

        <!-- Card 3: Pending Admin Approvals -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <span class="text-muted fw-semibold font-13 text-uppercase">Pending Admin Review</span>
                        <div class="avatar-sm rounded bg-soft-warning text-warning d-flex align-items-center justify-content-center">
                            <i class="fe-clock font-18"></i>
                        </div>
                    </div>
                    <h2 class="fw-bold mb-1">{{ $pendingPayrolls ?? 0 }}</h2>
                    <p class="text-muted font-12 mb-0">
                        Submitted for final approval
                    </p>
                </div>
            </div>
        </div>

        <!-- Card 4: Pending HR Adjustments -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <span class="text-muted fw-semibold font-13 text-uppercase">HR Adjustments Counter</span>
                        <div class="avatar-sm rounded bg-soft-danger text-danger d-flex align-items-center justify-content-center">
                            <i class="fe-sliders font-18"></i>
                        </div>
                    </div>
                    <h2 class="fw-bold mb-1">{{ $pendingAdjustments ?? 0 }}</h2>
                    <p class="text-muted font-12 mb-0">
                        <a href="{{ panelRoute('hr-adjustments.index') }}" class="text-danger text-decoration-none fw-semibold">
                            Pending adjustments <i class="fe-arrow-right font-11 ms-1"></i>
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Section -->
    <div class="row">
        <!-- Recent Draft Payrolls Table -->
        <div class="col-lg-8 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent border-0 d-flex align-items-center justify-content-between pt-3 pb-0">
                    <h5 class="card-title fw-bold mb-0 text-dark">
                        <i class="fe-file-text me-2 text-primary"></i> Recent Payroll Draft Operations
                    </h5>
                    <a href="{{ panelRoute('payroll-drafts.index') }}" class="btn btn-sm btn-light">View All</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-centered mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Employee</th>
                                    <th>Period</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentDrafts as $draft)
                                <tr>
                                    <td>
                                        <span class="fw-semibold text-dark d-block">
                                            {{ $draft->employee->first_name ?? 'N/A' }} {{ $draft->employee->last_name ?? '' }}
                                        </span>
                                        <small class="text-muted">Code: {{ $draft->employee->employee_code ?? 'N/A' }}</small>
                                    </td>
                                    <td>{{ $draft->payrollPeriod->period_name ?? 'N/A' }}</td>
                                    <td class="text-center">
                                        <span class="badge bg-soft-warning text-warning text-capitalize px-2 py-1">
                                            {{ str_replace('_', ' ', $draft->status) }}
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ panelRoute('payroll-drafts.index') }}" class="btn btn-xs btn-outline-primary">
                                            <i class="fe-eye"></i> Details
                                        </a>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">
                                        <i class="fe-inbox font-22 d-block mb-1"></i> No active payroll drafts found.
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                </div>
            </div>
        </div>

        <!-- HR Quick Action Hub & Recent Adjustments -->
        <div class="col-lg-4 mb-4">
            <!-- Action Hub Card -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent border-0 pt-3 pb-0">
                    <h5 class="card-title fw-bold mb-0 text-dark">
                        <i class="fe-zap me-2 text-warning"></i> HR Quick Actions
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="{{ panelRoute('payroll-drafts.create') }}" class="btn btn-outline-primary text-start d-flex align-items-center justify-content-between p-2">
                            <span><i class="fe-file-plus me-2"></i> Generate Draft Payroll</span>
                            <i class="fe-chevron-right"></i>
                        </a>
                        <a href="{{ panelRoute('attendance.index') }}" class="btn btn-outline-info text-start d-flex align-items-center justify-content-between p-2">
                            <span><i class="fe-clock me-2"></i> Attendance Verification</span>
                            <i class="fe-chevron-right"></i>
                        </a>
                        <a href="{{ panelRoute('hr-adjustments.index') }}" class="btn btn-outline-success text-start d-flex align-items-center justify-content-between p-2">
                            <span><i class="fe-sliders me-2"></i> HR Adjustments & Allowances</span>
                            <i class="fe-chevron-right"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Recent HR Adjustments Table Card -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent border-0 d-flex align-items-center justify-content-between pt-3 pb-0">
                    <h5 class="card-title fw-bold mb-0 text-dark">
                        <i class="fe-layers me-2 text-danger"></i> HR Adjustments Log
                    </h5>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        @forelse($recentAdjustments as $adj)
                        <li class="list-group-item px-0 d-flex align-items-center justify-content-between">
                            <div>
                                <span class="fw-semibold text-dark font-13 d-block">
                                    {{ $adj->employee->first_name ?? 'N/A' }} {{ $adj->employee->last_name ?? '' }}
                                </span>
                                <small class="text-muted">{{ ucfirst($adj->adjustment_type ?? 'Adjustment') }} - ₱{{ number_format($adj->amount, 2) }}</small>
                            </div>
                            <span class="badge bg-soft-{{ $adj->status === 'approved' ? 'success' : 'warning' }} text-{{ $adj->status === 'approved' ? 'success' : 'warning' }} font-11">
                                {{ ucfirst($adj->status ?? 'pending') }}
                            </span>
                        </li>
                        @empty
                        <li class="list-group-item px-0 text-center py-3 text-muted font-13">
                            No recent HR adjustments logged.
                        </li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
