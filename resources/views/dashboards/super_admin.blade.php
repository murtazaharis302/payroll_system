@extends('admin.layouts.Master')

@section('title', 'App Owner SaaS Platform Dashboard')

@section('content')
<div class="container-fluid">
    <!-- Page Title & Header Actions -->
    <div class="row align-items-center mb-3">
        <div class="col-md-7 col-12 mb-2 mb-md-0">
            <h4 class="page-title mb-1 font-18 fw-bold text-dark">App Owner SaaS Platform Dashboard</h4>
            <p class="text-muted font-12 mb-0">Global Multi-Tenant Control Hub & System Metrics</p>
        </div>
        <div class="col-md-5 col-12 text-md-end d-flex align-items-center justify-content-md-end gap-2">
            <a href="{{ route('admin.createCompany') }}" class="btn btn-primary btn-sm shadow-sm">
                <i class="fe-plus-circle me-1"></i> Onboard Tenant
            </a>
            <a href="{{ route('admin.users') }}" class="btn btn-outline-secondary btn-sm shadow-sm">
                <i class="fe-users me-1"></i> System Users
            </a>
        </div>
    </div>

    <!-- Platform Stat Cards -->
    <div class="row g-3 mb-3" id="cards-container">
        <!-- Active Companies -->
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100 mb-0">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted fw-semibold font-12 text-uppercase">Active Tenants</span>
                        <div class="avatar-xs rounded bg-soft-success text-success d-flex align-items-center justify-content-center" style="width:32px; height:32px;">
                            <i class="fe-briefcase font-16"></i>
                        </div>
                    </div>
                    <div class="d-flex align-items-baseline justify-content-between">
                        <h3 class="fw-bold mb-0 text-success">{{ $totalCompanies ?? 0 }}</h3>
                        <a href="{{ route('admin.companies') }}" class="text-success font-12 text-decoration-none fw-semibold">
                            View <i class="fe-arrow-right font-10 ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pending Companies -->
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100 mb-0">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted fw-semibold font-12 text-uppercase">Pending Approval</span>
                        <div class="avatar-xs rounded bg-soft-warning text-warning d-flex align-items-center justify-content-center" style="width:32px; height:32px;">
                            <i class="fe-clock font-16"></i>
                        </div>
                    </div>
                    <div class="d-flex align-items-baseline justify-content-between">
                        <h3 class="fw-bold mb-0 text-warning">{{ $pendingCompanies ?? 0 }}</h3>
                        <a href="{{ route('admin.pending') }}" class="text-warning font-12 text-decoration-none fw-semibold">
                            Review <i class="fe-arrow-right font-10 ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- System-wide Employees Counter -->
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100 mb-0">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted fw-semibold font-12 text-uppercase">Global Employees</span>
                        <div class="avatar-xs rounded bg-soft-primary text-primary d-flex align-items-center justify-content-center" style="width:32px; height:32px;">
                            <i class="fe-user-check font-16"></i>
                        </div>
                    </div>
                    <div class="d-flex align-items-baseline justify-content-between">
                        <h3 class="fw-bold mb-0 text-primary">{{ $totalEmployees ?? 0 }}</h3>
                        <span class="text-muted font-11">Across all tenants</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Platform System Users -->
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100 mb-0">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted fw-semibold font-12 text-uppercase">Platform Users</span>
                        <div class="avatar-xs rounded bg-soft-info text-info d-flex align-items-center justify-content-center" style="width:32px; height:32px;">
                            <i class="fe-users font-16"></i>
                        </div>
                    </div>
                    <div class="d-flex align-items-baseline justify-content-between">
                        <h3 class="fw-bold mb-0 text-info">{{ $totalUsers ?? 0 }}</h3>
                        <a href="{{ route('admin.users') }}" class="text-info font-12 text-decoration-none fw-semibold">
                            Manage <i class="fe-arrow-right font-10 ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="row g-3">
        <!-- Left Column: Tenant Matrix & Operational Audit Logs -->
        <div class="col-lg-7 col-xl-8">
            <!-- Recent Registered Companies Table -->
            <div class="card border-0 shadow-sm mb-3" id="dashboardtable">
                <div class="card-header bg-white border-bottom-0 d-flex align-items-center justify-content-between py-2.5 px-3">
                    <h5 class="card-title font-15 fw-bold mb-0 text-dark">
                        <i class="fe-building me-1.5 text-primary"></i> Registered Tenant Companies Matrix
                    </h5>
                    <a href="{{ route('admin.companies') }}" class="btn btn-xs btn-light font-12 fw-semibold">View All</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-sm table-centered mb-0 align-middle font-13">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3 py-2">Company Name</th>
                                    <th class="py-2">Contact</th>
                                    <th class="py-2">Status</th>
                                    <th class="py-2">Onboarded</th>
                                    <th class="text-end pe-3 py-2">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentCompanies->take(6) as $company)
                                <tr>
                                    <td class="ps-3 py-2">
                                        <span class="fw-semibold text-dark d-block">{{ $company->name }}</span>
                                        <small class="text-muted font-11">ID: {{ $company->id }}</small>
                                    </td>
                                    <td class="py-2">{{ $company->email ?? 'N/A' }}</td>
                                    <td class="py-2">
                                        @if($company->status == 1)
                                            <span class="badge bg-soft-success text-success px-2 py-1 font-11">Active</span>
                                        @elseif($company->status == 0)
                                            <span class="badge bg-soft-warning text-warning px-2 py-1 font-11">Pending</span>
                                        @else
                                            <span class="badge bg-soft-danger text-danger px-2 py-1 font-11">Deactivated</span>
                                        @endif
                                    </td>
                                    <td class="py-2 font-12">{{ $company->created_at ? $company->created_at->format('M d, Y') : 'N/A' }}</td>
                                    <td class="text-end pe-3 py-2">
                                        <a href="{{ route('admin.editCompany', $company->id) }}" class="btn btn-xs btn-outline-primary me-1" title="Edit">
                                            <i class="fe-edit"></i>
                                        </a>
                                        <a href="{{ route('super_admin.companies.permissions', $company->id) }}" class="btn btn-xs btn-outline-info" title="Permissions Matrix">
                                            <i class="fe-lock"></i>
                                        </a>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">
                                        No registered tenant companies found.
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Single-Company Tenant Audit Logs -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white border-bottom d-flex flex-wrap align-items-center justify-content-between py-2 px-3 gap-2">
                    <h5 class="card-title font-15 fw-bold mb-0 text-dark">
                        <i class="fe-activity me-1.5 text-primary"></i> Tenant Operational Audit Logs
                    </h5>
                    
                    {{-- Single-Company Filter Dropdown --}}
                    <form action="{{ route('admin.dashboard') }}" method="GET" class="d-flex align-items-center gap-2 mb-0">
                        <label for="company_id_select" class="form-label mb-0 fw-semibold text-muted font-12 text-nowrap">Filter Tenant:</label>
                        <select name="company_id" id="company_id_select" class="form-select form-select-sm font-12 py-1" onchange="this.form.submit()" style="min-width: 170px;">
                            <option value="">— Select Company —</option>
                            @foreach($allCompanies as $comp)
                                <option value="{{ $comp->id }}" {{ (string)$selectedCompanyId === (string)$comp->id ? 'selected' : '' }}>
                                    {{ $comp->name }} (ID: {{ $comp->id }})
                                </option>
                            @endforeach
                        </select>
                    </form>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-sm table-centered mb-0 align-middle font-13">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3 py-2">Timestamp</th>
                                    <th class="py-2">User</th>
                                    <th class="py-2">Module</th>
                                    <th class="py-2">Action</th>
                                    <th class="pe-3 py-2">Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($activityLogs->take(5) as $log)
                                    <tr>
                                        <td class="ps-3 py-2 font-12 text-nowrap">{{ $log->created_at ? $log->created_at->format('M d, h:i A') : '—' }}</td>
                                        <td class="py-2">
                                            <span class="fw-semibold text-dark d-block font-12">{{ $log->user->name ?? 'System' }}</span>
                                        </td>
                                        <td class="py-2">
                                            <span class="badge bg-soft-info text-info text-capitalize font-11">{{ str_replace('_', ' ', $log->module) }}</span>
                                        </td>
                                        <td class="py-2">
                                            @if(in_array($log->action, ['approved', 'approve', 'post', 'create']))
                                                <span class="badge bg-soft-success text-success text-capitalize font-11">{{ $log->action }}</span>
                                            @elseif(in_array($log->action, ['rejected', 'reject', 'delete']))
                                                <span class="badge bg-soft-danger text-danger text-capitalize font-11">{{ $log->action }}</span>
                                            @else
                                                <span class="badge bg-soft-primary text-primary text-capitalize font-11">{{ $log->action }}</span>
                                            @endif
                                        </td>
                                        <td class="pe-3 py-2 font-12 text-truncate" style="max-width: 180px;" title="{{ $log->description }}">{{ $log->description ?? '—' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-muted font-13">
                                            <i class="fe-info font-18 d-block mb-1 text-muted"></i>
                                            No activity logs recorded for the selected tenant company.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Shortcuts, Tenant Health Status & Recent Accounts -->
        <div class="col-lg-5 col-xl-4">
            <!-- SaaS Shortcuts Card -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white border-bottom-0 py-2.5 px-3">
                    <h5 class="card-title font-15 fw-bold mb-0 text-dark">
                        <i class="fe-zap me-1.5 text-warning"></i> Platform Shortcuts
                    </h5>
                </div>
                <div class="card-body p-3 pt-0">
                    <div class="d-flex flex-column gap-2">
                        <a href="{{ route('admin.createCompany') }}" class="btn btn-sm btn-outline-primary text-start d-flex align-items-center justify-content-between py-1.5 px-2.5">
                            <span><i class="fe-plus-square me-2"></i> Onboard New Tenant</span>
                            <i class="fe-chevron-right font-12"></i>
                        </a>
                        <a href="{{ route('admin.pending') }}" class="btn btn-sm btn-outline-warning text-start d-flex align-items-center justify-content-between py-1.5 px-2.5">
                            <span><i class="fe-clock me-2"></i> Review Pending ({{ $pendingCompanies ?? 0 }})</span>
                            <i class="fe-chevron-right font-12"></i>
                        </a>
                        <a href="{{ route('admin.deactivatedCompanies') }}" class="btn btn-sm btn-outline-danger text-start d-flex align-items-center justify-content-between py-1.5 px-2.5">
                            <span><i class="fe-x-circle me-2"></i> Deactivated Tenants ({{ $deactivatedCompanies ?? 0 }})</span>
                            <i class="fe-chevron-right font-12"></i>
                        </a>
                        <a href="{{ route('admin.users') }}" class="btn btn-sm btn-outline-info text-start d-flex align-items-center justify-content-between py-1.5 px-2.5">
                            <span><i class="fe-users me-2"></i> Global Users Directory</span>
                            <i class="fe-chevron-right font-12"></i>
                        </a>
                        <a href="{{ route('admin.settings') }}" class="btn btn-sm btn-outline-secondary text-start d-flex align-items-center justify-content-between py-1.5 px-2.5">
                            <span><i class="fe-sliders me-2"></i> System Global Settings</span>
                            <i class="fe-chevron-right font-12"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Tenant Health Breakdown Card -->
            @php
                $grandTotalCompanies = ($totalCompanies ?? 0) + ($pendingCompanies ?? 0) + ($deactivatedCompanies ?? 0);
                $activePct = $grandTotalCompanies > 0 ? round((($totalCompanies ?? 0) / $grandTotalCompanies) * 100) : 0;
                $pendingPct = $grandTotalCompanies > 0 ? round((($pendingCompanies ?? 0) / $grandTotalCompanies) * 100) : 0;
                $deactivePct = $grandTotalCompanies > 0 ? round((($deactivatedCompanies ?? 0) / $grandTotalCompanies) * 100) : 0;
            @endphp
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white border-bottom-0 py-2.5 px-3">
                    <h5 class="card-title font-15 fw-bold mb-0 text-dark">
                        <i class="fe-pie-chart me-1.5 text-success"></i> Tenant Status Overview
                    </h5>
                </div>
                <div class="card-body p-3 pt-0">
                    <div class="mb-2">
                        <div class="d-flex justify-content-between align-items-center font-12 mb-1">
                            <span class="fw-semibold text-dark"><i class="fe-check-circle me-1 text-success"></i> Active Tenants</span>
                            <span class="fw-bold text-success">{{ $totalCompanies ?? 0 }} ({{ $activePct }}%)</span>
                        </div>
                        <div class="progress" style="height: 6px;">
                            <div class="progress-bar bg-success" role="progressbar" style="width: {{ $activePct }}%"></div>
                        </div>
                    </div>
                    <div class="mb-2">
                        <div class="d-flex justify-content-between align-items-center font-12 mb-1">
                            <span class="fw-semibold text-dark"><i class="fe-clock me-1 text-warning"></i> Pending Approvals</span>
                            <span class="fw-bold text-warning">{{ $pendingCompanies ?? 0 }} ({{ $pendingPct }}%)</span>
                        </div>
                        <div class="progress" style="height: 6px;">
                            <div class="progress-bar bg-warning" role="progressbar" style="width: {{ $pendingPct }}%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="d-flex justify-content-between align-items-center font-12 mb-1">
                            <span class="fw-semibold text-dark"><i class="fe-x-circle me-1 text-danger"></i> Deactivated</span>
                            <span class="fw-bold text-danger">{{ $deactivatedCompanies ?? 0 }} ({{ $deactivePct }}%)</span>
                        </div>
                        <div class="progress" style="height: 6px;">
                            <div class="progress-bar bg-danger" role="progressbar" style="width: {{ $deactivePct }}%"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Platform Users -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white border-bottom-0 d-flex align-items-center justify-content-between py-2.5 px-3">
                    <h5 class="card-title font-15 fw-bold mb-0 text-dark">
                        <i class="fe-user me-1.5 text-info"></i> Recent System Accounts
                    </h5>
                    <a href="{{ route('admin.users') }}" class="btn btn-xs btn-light font-12 fw-semibold">View All</a>
                </div>
                <div class="card-body p-3 pt-0">
                    <ul class="list-group list-group-flush">
                        @forelse($recentUsers->take(4) as $u)
                        <li class="list-group-item px-0 py-2 d-flex align-items-center justify-content-between border-bottom">
                            <div>
                                <span class="fw-semibold text-dark font-12 d-block">{{ $u->name }}</span>
                                <small class="text-muted font-11">{{ $u->email }}</small>
                            </div>
                            <span class="badge bg-soft-primary text-primary font-11 text-capitalize">
                                {{ $u->role ?? 'User' }}
                            </span>
                        </li>
                        @empty
                        <li class="list-group-item px-0 text-center py-3 text-muted font-12 border-0">
                            No recent user registrations.
                        </li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
