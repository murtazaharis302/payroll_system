@extends('admin.layouts.Master')

@section('title', 'Company Executive Dashboard')

@section('content')
<div class="container-fluid">
    <!-- Page Title & Company Header -->
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-flex align-items-center justify-content-between py-3">
                <div>
                    <h4 class="page-title mb-0">Company Executive Dashboard</h4>
                    <p class="text-muted font-13 mb-0">
                        Financial Oversight & Approval Control Center — {{ $companyData->name ?? 'Admin' }}
                        @if(isset($companyData->company_code) && $companyData->company_code)
                            <span class="badge bg-soft-primary text-primary font-12 ms-2"><i class="fe-tag me-1"></i>Code: {{ $companyData->company_code }}</span>
                        @endif
                    </p>
                </div>
                <div class="page-title-right d-flex align-items-center gap-2">
                    <a href="{{ panelRoute('payroll-approvals.index') }}" class="btn btn-warning btn-sm shadow-sm text-dark fw-semibold align-self-center">
                        <i class="fe-check-square me-1"></i> Approval Queue ({{ $pendingPayrollDrafts ?? 0 }})
                    </a>
                    <a href="{{ panelRoute('reports.index') }}" class="btn btn-primary btn-sm shadow-sm align-self-center text-nowrap">
                        <i class="fe-bar-chart-2 me-1"></i> Reports Hub
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Executive Stat Cards -->
    <div class="row">
        <!-- Card 1: Total Payroll Expenditure -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <span class="text-muted fw-semibold font-13 text-uppercase">Total Financial Payroll</span>
                        <div class="avatar-sm rounded bg-soft-success text-success d-flex align-items-center justify-content-center">
                            <i class="fe-dollar-sign font-18"></i>
                        </div>
                    </div>
                    <h2 class="fw-bold mb-1 text-success">
                        ₱{{ number_format($totalPayrollExpenditure ?? 0, 2) }}
                    </h2>
                    <p class="text-muted font-12 mb-0">
                        Total posted/approved net pay
                    </p>
                </div>
            </div>
        </div>

        <!-- Card 2: Pending Approval Queue -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <span class="text-muted fw-semibold font-13 text-uppercase">Pending Approval Queue</span>
                        <div class="avatar-sm rounded bg-soft-warning text-warning d-flex align-items-center justify-content-center">
                            <i class="fe-clock font-18"></i>
                        </div>
                    </div>
                    <h2 class="fw-bold mb-1 text-warning">{{ $pendingPayrollDrafts ?? 0 }}</h2>
                    <p class="text-muted font-12 mb-0">
                        Drafts awaiting executive approval
                    </p>
                </div>
            </div>
        </div>

        <!-- Card 3: Active Employees -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <span class="text-muted fw-semibold font-13 text-uppercase">Active Company Staff</span>
                        <div class="avatar-sm rounded bg-soft-primary text-primary d-flex align-items-center justify-content-center">
                            <i class="fe-users font-18"></i>
                        </div>
                    </div>
                    <h2 class="fw-bold mb-1">{{ $totalEmployees ?? 0 }}</h2>
                    <p class="text-muted font-12 mb-0">
                        Active headcount
                    </p>
                </div>
            </div>
        </div>

        <!-- Card 4: Active Loans Balance -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <span class="text-muted fw-semibold font-13 text-uppercase">Active Loan Exposure</span>
                        <div class="avatar-sm rounded bg-soft-danger text-danger d-flex align-items-center justify-content-center">
                            <i class="fe-credit-card font-18"></i>
                        </div>
                    </div>
                    <h2 class="fw-bold mb-1">
                        ₱{{ number_format($totalLoanBalance ?? 0, 2) }}
                    </h2>
                    <p class="text-muted font-12 mb-0">
                        Across {{ $activeValeLoans ?? 0 }} active loans
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Section -->
    <div class="row">
        <!-- Left: Approval Queue Table -->
        <div class="col-lg-7 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent border-0 d-flex align-items-center justify-content-between pt-3 pb-0">
                    <h5 class="card-title fw-bold mb-0 text-dark">
                        <i class="fe-check-circle me-2 text-warning"></i> Pending HR Draft Approvals
                    </h5>
                    <a href="{{ panelRoute('payroll-approvals.index') }}" class="btn btn-sm btn-outline-warning">Review All</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-centered mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Employee</th>
                                    <th>Payroll Period</th>
                                    <th class="text-end">Net Payable</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($pendingApprovalsList as $item)
                                <tr>
                                    <td>
                                        <span class="fw-semibold text-dark d-block">
                                            {{ $item->employee->first_name ?? 'N/A' }} {{ $item->employee->last_name ?? '' }}
                                        </span>
                                        <small class="text-muted">Code: {{ $item->employee->employee_code ?? 'N/A' }}</small>
                                    </td>
                                    <td>{{ $item->payrollPeriod->period_name ?? 'N/A' }}</td>
                                    <td class="text-end fw-bold text-success">₱{{ number_format($item->net_pay, 2) }}</td>
                                    <td class="text-center">
                                        <a href="{{ panelRoute('payroll-approvals.index') }}" class="btn btn-xs btn-warning fw-semibold">
                                            <i class="fe-check"></i> Review
                                        </a>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">
                                        <i class="fe-check-circle font-22 text-success d-block mb-1"></i> All HR drafts are currently reviewed & approved!
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right: Statutory & Reports Quick Access Hub -->
        <div class="col-lg-5 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent border-0 pt-3 pb-0">
                    <h5 class="card-title fw-bold mb-0 text-dark">
                        <i class="fe-folder me-2 text-primary"></i> Reports & Statutory Compliance Hub
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <!-- SSS Report Card -->
                        <div class="col-6">
                            <a href="{{ panelRoute('reports.index') }}" class="card bg-light border-0 text-decoration-none p-3 h-100 d-block text-center hover-shadow">
                                <div class="avatar-sm rounded-circle bg-soft-primary text-primary mx-auto mb-2 d-flex align-items-center justify-content-center">
                                    <i class="fe-file-text font-18"></i>
                                </div>
                                <h6 class="fw-bold text-dark mb-1 font-13">SSS Contributions</h6>
                                <span class="text-muted font-11">Statutory Form</span>
                            </a>
                        </div>

                        <!-- PhilHealth Report Card -->
                        <div class="col-6">
                            <a href="{{ panelRoute('reports.index') }}" class="card bg-light border-0 text-decoration-none p-3 h-100 d-block text-center hover-shadow">
                                <div class="avatar-sm rounded-circle bg-soft-success text-success mx-auto mb-2 d-flex align-items-center justify-content-center">
                                    <i class="fe-heart font-18"></i>
                                </div>
                                <h6 class="fw-bold text-dark mb-1 font-13">PhilHealth Form</h6>
                                <span class="text-muted font-11">Statutory Form</span>
                            </a>
                        </div>

                        <!-- PagIBIG Report Card -->
                        <div class="col-6">
                            <a href="{{ panelRoute('reports.index') }}" class="card bg-light border-0 text-decoration-none p-3 h-100 d-block text-center hover-shadow">
                                <div class="avatar-sm rounded-circle bg-soft-info text-info mx-auto mb-2 d-flex align-items-center justify-content-center">
                                    <i class="fe-home font-18"></i>
                                </div>
                                <h6 class="fw-bold text-dark mb-1 font-13">Pag-IBIG HDMF</h6>
                                <span class="text-muted font-11">Statutory Form</span>
                            </a>
                        </div>

                        <!-- 13th Month Pay Card -->
                        <div class="col-6">
                            <a href="{{ panelRoute('reports.index') }}" class="card bg-light border-0 text-decoration-none p-3 h-100 d-block text-center hover-shadow">
                                <div class="avatar-sm rounded-circle bg-soft-warning text-warning mx-auto mb-2 d-flex align-items-center justify-content-center">
                                    <i class="fe-gift font-18"></i>
                                </div>
                                <h6 class="fw-bold text-dark mb-1 font-13">13th Month Report</h6>
                                <span class="text-muted font-11">Annual Bonus</span>
                            </a>
                        </div>

                        <!-- Bank Advice Report -->
                        <div class="col-6">
                            <a href="{{ panelRoute('reports.index') }}" class="card bg-light border-0 text-decoration-none p-3 h-100 d-block text-center hover-shadow">
                                <div class="avatar-sm rounded-circle bg-soft-secondary text-secondary mx-auto mb-2 d-flex align-items-center justify-content-center">
                                    <i class="fe-briefcase font-18"></i>
                                </div>
                                <h6 class="fw-bold text-dark mb-1 font-13">Bank Transmittal</h6>
                                <span class="text-muted font-11">Payroll Advice</span>
                            </a>
                        </div>

                        <!-- Master Sheet Report -->
                        <div class="col-6">
                            <a href="{{ panelRoute('reports.index') }}" class="card bg-light border-0 text-decoration-none p-3 h-100 d-block text-center hover-shadow">
                                <div class="avatar-sm rounded-circle bg-soft-danger text-danger mx-auto mb-2 d-flex align-items-center justify-content-center">
                                    <i class="fe-grid font-18"></i>
                                </div>
                                <h6 class="fw-bold text-dark mb-1 font-13">Master Payroll</h6>
                                <span class="text-muted font-11">Comprehensive</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
