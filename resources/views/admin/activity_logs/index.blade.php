@extends('admin.layouts.Master')

@section('title') Activity Logs @endsection

@section('content')
<div class="row">
    <div class="col-12">
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show mt-3" role="alert">
                <i class="fe-alert-circle me-2 font-16"></i> {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if(session('status'))
            <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
                <i class="fe-check-circle me-2 font-16"></i> {{ session('status') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <div class="card shadow-sm border-0 mt-3">
            <div class="card-header bg-white border-bottom py-3">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                    <div>
                        <h4 class="card-title text-dark fw-bold mb-0">
                            <i class="fe-activity text-primary me-2"></i> Operational Audit Trail
                        </h4>
                        <p class="text-muted font-13 mb-0">
                            @if($userRole === 'admin')
                                System-wide operational activity & tenant log monitoring
                            @else
                                Tenant activity audit trail for your organization
                            @endif
                        </p>
                    </div>

                    @if($userRole !== 'admin')
                        <span class="badge bg-soft-info text-info px-3 py-2 font-13">
                            <i class="fe-shield me-1"></i> Company Scoped Logs
                        </span>
                    @endif
                </div>

                {{-- Date & Tenant Filter + Export Bar --}}
                <form action="{{ panelRoute('activity-logs.index') }}" method="GET" class="bg-light p-3 rounded border">
                    <div class="row g-2 align-items-end">
                        @if($userRole === 'admin')
                            <div class="col-md-3">
                                <label for="company_id_select" class="form-label font-12 fw-semibold text-muted mb-1">Tenant Company</label>
                                <select name="company_id" id="company_id_select" class="form-select form-select-sm">
                                    <option value="">— All Tenant Companies —</option>
                                    @foreach($allCompanies as $comp)
                                        <option value="{{ $comp->id }}" {{ (string)$selectedCompanyId === (string)$comp->id ? 'selected' : '' }}>
                                            {{ $comp->name }} (ID: {{ $comp->id }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        <div class="{{ $userRole === 'admin' ? 'col-md-3' : 'col-md-4' }}">
                            <label for="start_date" class="form-label font-12 fw-semibold text-muted mb-1">Start Date</label>
                            <input type="date" name="start_date" id="start_date" class="form-control form-control-sm" value="{{ $startDate ?? '' }}">
                        </div>

                        <div class="{{ $userRole === 'admin' ? 'col-md-3' : 'col-md-4' }}">
                            <label for="end_date" class="form-label font-12 fw-semibold text-muted mb-1">End Date</label>
                            <input type="date" name="end_date" id="end_date" class="form-control form-control-sm" value="{{ $endDate ?? '' }}">
                        </div>

                        <div class="{{ $userRole === 'admin' ? 'col-md-3' : 'col-md-4' }} d-flex gap-2">
                            <button type="submit" class="btn btn-primary btn-sm flex-fill" title="Apply Filter">
                                <i class="fe-filter me-1"></i> Filter
                            </button>

                            <button type="submit" formAction="{{ panelRoute('activity-logs.export') }}" class="btn btn-success btn-sm flex-fill" title="Download Audit Report CSV">
                                <i class="fe-download me-1"></i> Export Report
                            </button>

                            @if($selectedCompanyId || $startDate || $endDate)
                                <a href="{{ panelRoute('activity-logs.index') }}" class="btn btn-sm btn-outline-secondary" title="Clear Filters">
                                    <i class="fe-x"></i>
                                </a>
                            @endif
                        </div>
                    </div>
                </form>
            </div>

            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover table-centered mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Timestamp</th>
                                <th>User</th>
                                @if($userRole === 'admin')
                                    <th>Tenant Company</th>
                                @endif
                                <th>Module</th>
                                <th>Action</th>
                                <th>Description</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($logs as $log)
                                <tr>
                                    <td class="font-12 text-nowrap">
                                        {{ $log->created_at ? $log->created_at->format('M d, Y h:i A') : '—' }}
                                    </td>
                                    <td>
                                        <span class="fw-bold text-dark d-block">{{ $log->user->name ?? 'System' }}</span>
                                        <small class="text-muted">{{ $log->user->email ?? '' }}</small>
                                    </td>
                                    @if($userRole === 'admin')
                                        <td>
                                            <span class="badge bg-soft-secondary text-dark font-12">
                                                {{ $log->company->name ?? 'Global / N/A' }}
                                            </span>
                                        </td>
                                    @endif
                                    <td>
                                        <span class="badge bg-soft-primary text-primary text-uppercase px-2 py-1 font-11">
                                            {{ str_replace('_', ' ', $log->module) }}
                                        </span>
                                    </td>
                                    <td>
                                        @if(in_array($log->action, ['approved', 'approve', 'post', 'create']))
                                            <span class="badge bg-soft-success text-success text-capitalize">{{ $log->action }}</span>
                                        @elseif(in_array($log->action, ['rejected', 'reject', 'delete']))
                                            <span class="badge bg-soft-danger text-danger text-capitalize">{{ $log->action }}</span>
                                        @else
                                            <span class="badge bg-soft-info text-info text-capitalize">{{ $log->action }}</span>
                                        @endif
                                    </td>
                                    <td class="font-13">{{ $log->description ?? '—' }}</td>
                                    <td class="font-12 text-muted">{{ $log->ip_address ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $userRole === 'admin' ? 7 : 6 }}" class="text-center py-4 text-muted">
                                        <i class="fe-activity display-4 d-block mb-2 text-muted"></i>
                                        No activity logs recorded for the selected criteria.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-end mt-3">
                    {{ $logs->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
