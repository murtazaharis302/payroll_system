@extends('admin.layouts.Master')

@section('title', 'Employee Self-Service Dashboard')

@section('content')
<div class="container-fluid">
    <!-- Page Title -->
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-flex align-items-center justify-content-between py-3">
                <h4 class="page-title mb-0">Employee Self-Service Dashboard</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="javascript:void(0);">Home</a></li>
                        <li class="breadcrumb-item active">Dashboard</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Employee Welcome Hero Card -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card bg-primary text-white shadow-sm border-0" style="background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                        <div class="d-flex align-items-center">
                            <div class="avatar-lg rounded-circle bg-white text-primary d-flex align-items-center justify-content-center me-3 font-24 fw-bold shadow-sm" style="width:60px; height:60px;">
                                <i class="fe-user"></i>
                            </div>
                            <div>
                                <h3 class="text-white mb-1 fw-bold">
                                    Welcome back, {{ $employeeData->first_name ?? auth()->user()->name }} {{ $employeeData->last_name ?? '' }}!
                                </h3>
                                <p class="mb-0 text-white-50">
                                    <span class="me-3"><i class="fe-award me-1"></i> ID: {{ $employeeData->employee_code ?? 'EMP-'.(auth()->id()) }}</span>
                                    <span class="me-3"><i class="fe-briefcase me-1"></i> {{ $employeeData->department->department_name ?? 'General Staff' }}</span>
                                    <span><i class="fe-building me-1"></i> {{ $companyData->name ?? 'Company' }}</span>
                                </p>
                            </div>
                        </div>
                        <div>
                            <span class="badge bg-light text-primary px-3 py-2 fs-6 fw-semibold rounded-pill">
                                <i class="fe-check-circle me-1 text-success"></i> Account Active
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Metric Summary Cards -->
    <div class="row">
        <!-- Card 1: Latest Net Pay -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <span class="text-muted fw-semibold font-13 text-uppercase">Latest Net Pay</span>
                        <div class="avatar-sm rounded bg-soft-primary text-primary d-flex align-items-center justify-content-center">
                            <i class="fe-dollar-sign font-18"></i>
                        </div>
                    </div>
                    <h2 class="fw-bold mb-1">
                        ₱{{ isset($latestPayslip) ? number_format($latestPayslip->net_pay, 2) : '0.00' }}
                    </h2>
                    <p class="text-muted font-12 mb-0">
                        @if(isset($latestPayslip->payrollPeriod))
                            Period: {{ $latestPayslip->payrollPeriod->period_name ?? $latestPayslip->payrollPeriod->start_date }}
                        @else
                            No recent payslip issued
                        @endif
                    </p>
                </div>
            </div>
        </div>

        <!-- Card 2: Active Loan Balance -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <span class="text-muted fw-semibold font-13 text-uppercase">Active Loan Balance</span>
                        <div class="avatar-sm rounded bg-soft-warning text-warning d-flex align-items-center justify-content-center">
                            <i class="fe-credit-card font-18"></i>
                        </div>
                    </div>
                    <h2 class="fw-bold mb-1">
                        ₱{{ number_format($totalActiveLoanBalance ?? 0, 2) }}
                    </h2>
                    <p class="text-muted font-12 mb-0">
                        {{ count($myValeLoans) }} active {{ Str::plural('loan', count($myValeLoans)) }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Card 3: Attendance Logs This Month -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <span class="text-muted fw-semibold font-13 text-uppercase">Attendance Logs</span>
                        <div class="avatar-sm rounded bg-soft-success text-success d-flex align-items-center justify-content-center">
                            <i class="fe-clock font-18"></i>
                        </div>
                    </div>
                    <h2 class="fw-bold mb-1">
                        {{ $attendanceCountThisMonth ?? 0 }}
                    </h2>
                    <p class="text-muted font-12 mb-0">
                        Log entries for {{ date('F Y') }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Card 4: Employment Status -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <span class="text-muted fw-semibold font-13 text-uppercase">Employment Record</span>
                        <div class="avatar-sm rounded bg-soft-info text-info d-flex align-items-center justify-content-center">
                            <i class="fe-shield font-18"></i>
                        </div>
                    </div>
                    <h2 class="fw-bold mb-1 text-capitalize">
                        {{ $employeeData->employment_status ?? 'Regular' }}
                    </h2>
                    <p class="text-muted font-12 mb-0">
                        Joined: {{ isset($employeeData->date_hired) ? \Carbon\Carbon::parse($employeeData->date_hired)->format('M d, Y') : 'N/A' }}
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Content Rows -->
    <div class="row">
        <!-- Left Column: Quick Download Latest Payslip & Recent Payslips Table -->
        <div class="col-lg-7 mb-4">
            @if(isset($latestPayslip))
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent border-0 d-flex align-items-center justify-content-between pt-3 pb-0">
                    <h5 class="card-title fw-bold mb-0 text-dark">
                        <i class="fe-file-text me-2 text-primary"></i> Latest Payslip Quick Summary
                    </h5>
                    <span class="badge bg-soft-success text-success fw-semibold">
                        {{ strtoupper($latestPayslip->status) }}
                    </span>
                </div>
                <div class="card-body">
                    <div class="bg-light p-3 rounded mb-3">
                        <div class="row text-center">
                            <div class="col-4 border-end">
                                <span class="text-muted font-12 text-uppercase d-block">Gross Earnings</span>
                                <span class="fw-bold font-16 text-dark">₱{{ number_format($latestPayslip->gross_pay ?? $latestPayslip->basic_pay, 2) }}</span>
                            </div>
                            <div class="col-4 border-end">
                                <span class="text-muted font-12 text-uppercase d-block">Total Deductions</span>
                                <span class="fw-bold font-16 text-danger">₱{{ number_format($latestPayslip->total_deductions, 2) }}</span>
                            </div>
                            <div class="col-4">
                                <span class="text-muted font-12 text-uppercase d-block">Take Home (Net)</span>
                                <span class="fw-bold font-16 text-success">₱{{ number_format($latestPayslip->net_pay, 2) }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="text-muted font-13">
                            <i class="fe-calendar me-1"></i> Period: {{ $latestPayslip->payrollPeriod->period_name ?? 'Current Period' }}
                        </div>
                        <a href="{{ panelRoute('employee.my-payslip.download', $latestPayslip->id) }}" class="btn btn-sm btn-primary" target="_blank">
                            <i class="fe-download me-1"></i> Download Latest Payslip (PDF)
                        </a>
                    </div>
                </div>
            </div>
            @endif

            <!-- Recent Payslips Table Card -->
            <div id="recent-payslips-card" class="card border-0 shadow-sm">
                <div class="card-header bg-transparent border-0 d-flex align-items-center justify-content-between pt-3 pb-0">
                    <h5 class="card-title fw-bold mb-0 text-dark">
                        <i class="fe-list me-2 text-primary"></i> Recent Payslip History
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-centered mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Period</th>
                                    <th>Pay Date</th>
                                    <th class="text-end">Net Pay</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentPayslips as $payslip)
                                <tr>
                                    <td>
                                        <span class="fw-semibold text-dark">{{ $payslip->payrollPeriod->period_name ?? 'Period #'.$payslip->payroll_period_id }}</span>
                                    </td>
                                    <td>{{ $payslip->created_at->format('M d, Y') }}</td>
                                    <td class="text-end fw-bold text-success">₱{{ number_format($payslip->net_pay, 2) }}</td>
                                    <td class="text-center">
                                        <span class="badge bg-soft-success text-success text-capitalize px-2 py-1">
                                            {{ $payslip->status }}
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ panelRoute('employee.my-payslip.download', $payslip->id) }}" class="btn btn-sm btn-outline-primary fw-semibold" target="_blank">
                                            <i class="fe-download me-1"></i> Download PDF
                                        </a>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">
                                        <i class="fe-info font-20 d-block mb-1"></i> No issued payslips found.
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Active Loans & HR Adjustments Summary -->
        <div class="col-lg-5 mb-4">
            <!-- Active Loans Card -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent border-0 d-flex align-items-center justify-content-between pt-3 pb-0">
                    <h5 class="card-title fw-bold mb-0 text-dark">
                        <i class="fe-credit-card me-2 text-warning"></i> Active Loans & Advances
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Loan Reference</th>
                                    <th class="text-end">Balance</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($myValeLoans as $loan)
                                <tr>
                                    <td>
                                        <span class="fw-semibold text-dark d-block">{{ $loan->loanType->name ?? 'Vale / Cash Advance' }}</span>
                                        <small class="text-muted">Deduction: ₱{{ number_format($loan->monthly_deduction ?? 0, 2) }} / period</small>
                                    </td>
                                    <td class="text-end fw-bold text-danger">₱{{ number_format($loan->current_balance, 2) }}</td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="2" class="text-center py-3 text-muted">
                                        No active loans or cash advances.
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Personal Attendance Log Preview -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent border-0 d-flex align-items-center justify-content-between pt-3 pb-0">
                    <h5 class="card-title fw-bold mb-0 text-dark">
                        <i class="fe-clock me-2 text-info"></i> Recent Time Clock Entries
                    </h5>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        @forelse($recentAttendance as $log)
                        <li class="list-group-item px-0 d-flex align-items-center justify-content-between">
                            <div>
                                <span class="fw-semibold text-dark font-13 d-block">
                                    {{ $log->log_date ? \Carbon\Carbon::parse($log->log_date)->format('M d, Y') : 'N/A' }}
                                </span>
                                <small class="text-muted">{{ strtoupper($log->log_type ?? 'Time Log') }}</small>
                            </div>
                            <span class="badge bg-soft-primary text-primary font-12">
                                {{ $log->log_time ? \Carbon\Carbon::parse($log->log_time)->format('h:i A') : '' }}
                            </span>
                        </li>
                        @empty
                        <li class="list-group-item px-0 text-center py-3 text-muted font-13">
                            No biometric logs recorded recently.
                        </li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
