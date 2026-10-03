@extends('admin.layouts.Master')
@section('title') Payroll Periods @endsection
@section('content')
    @php
        $isSuperAdmin = (auth()->user()->role === 'admin' || auth()->user()->role_id === \App\Models\Role::SUPER_ADMIN);
    @endphp
    <div class="col-12">
        <div class="card mt-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Payroll Periods Management</h4>
                <div class="d-flex" style="gap: 8px;">
                    <a href="{{ panelRoute('payroll-periods.create') }}" class="btn btn-primary" style="background: linear-gradient(135deg, #1f618d, #2980b9); border: none; padding: 8px 18px; font-weight: bold; color: #fff;">
                        <i class="mdi mdi-plus"></i> Create Period
                    </a>
                    <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#quickCreateModal" style="background: linear-gradient(135deg, #16a085, #1abc9c); border: none; padding: 8px 18px; font-weight: bold; color: #fff;">
                        <i class="mdi mdi-flash"></i> Quick Create
                    </button>
                </div>
            </div>
            <div class="card-body">
                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <strong>Success!</strong> {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                @if(session('error'))
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <strong>Error!</strong> {{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
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
                            <th scope="col">#</th>
                            <th scope="col">Period Name</th>
                            <th scope="col">Company</th>
                            <th scope="col">Start Date</th>
                            <th scope="col">End Date</th>
                            <th scope="col">Pay Date</th>
                            <th scope="col">Status</th>
                            <th scope="col">Action</th>
                        </tr>
                        </thead>
                        <tbody>
                            @forelse($periods as $period)
                                <tr>
                                    <td>{{ $period->id }}</td>
                                    <td><strong>{{ $period->period_name }}</strong></td>
                                    <td>{{ $period->company->company_name ?? 'N/A' }}</td>
                                    <td>{{ \Carbon\Carbon::parse($period->period_start)->format('M d, Y') }}</td>
                                    <td>{{ \Carbon\Carbon::parse($period->period_end)->format('M d, Y') }}</td>
                                    <td>{{ \Carbon\Carbon::parse($period->pay_date)->format('M d, Y') }}</td>
                                    <td>
                                        @if($period->status === 'open')
                                            <span class="badge bg-success">Open</span>
                                        @elseif($period->status === 'closed')
                                            <span class="badge bg-warning text-dark">Closed</span>
                                        @else
                                            <span class="badge bg-info">Completed</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center" style="gap: 12px; min-width: 80px;">
                                            <a href="{{ panelRoute('payroll-periods.edit', $period->id) }}" class="text-warning" title="Edit" style="text-decoration: none;">
                                                <i class="fa-solid fa-pen-to-square" style="font-size: 14px;"></i>
                                            </a>
                                            <form action="{{ panelRoute('payroll-periods.destroy', $period->id) }}" method="POST" style="display:inline; margin:0;" class="btn-delete-form" onsubmit="return confirm('Are you sure you want to delete this period?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-link p-0 text-danger btn-delete" title="Delete" style="border: none; background: none; line-height: 1;">
                                                    <i class="fa-sharp fa-solid fa-trash" style="font-size: 14px;"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>    
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center">No payroll periods found</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                <div class="d-flex align-items-center justify-content-between flex-wrap mt-3">
                    <div>
                        {{ $periods->links() }}
                    </div>
                    <x-per-page-selector :perPage="request('per_page', 50)" />
                </div>
        </div> 
    </div>

    <!-- Quick Create Modal -->
    <div class="modal fade" id="quickCreateModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="{{ panelRoute('payroll-periods.quick-create') }}" method="POST">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Quick Create Multiple Periods</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        @if($isSuperAdmin)
                            <div class="form-group mb-3">
                                <label class="form-label" style="font-size: 11px; font-weight: bold; color: #555;">Company</label>
                                <select name="company_id" class="form-control" required>
                                    <option value="">Select Company</option>
                                    @foreach(\App\Models\Company::where('status', 1)->get() as $comp)
                                        <option value="{{ $comp->id }}">{{ $comp->company_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @else
                            <input type="hidden" name="company_id" value="{{ getActiveCompanyId() ?? auth()->user()->company_id }}">
                        @endif
                        <div class="form-group mb-3">
                            <label class="form-label" style="font-size: 11px; font-weight: bold; color: #555;">Start Date</label>
                            <input type="date" name="start_date" class="form-control" value="{{ now()->format('Y-m-01') }}" required>
                        </div>
                        <div class="form-group mb-3">
                            <label class="form-label" style="font-size: 11px; font-weight: bold; color: #555;">Number of Periods</label>
                            <input type="number" name="number_of_periods" class="form-control" value="6" min="1" max="24" required>
                        </div>
                        <div class="form-group mb-3">
                            <label class="form-label" style="font-size: 11px; font-weight: bold; color: #555;">Frequency</label>
                            <select name="frequency" class="form-control" required>
                                <option value="bi-monthly">Bi-Monthly (15th & End of Month)</option>
                                <option value="monthly">Monthly</option>
                            </select>
                        </div>
                        <p class="text-muted small mb-0">This will automatically create multiple periods based on your selection.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" style="background: linear-gradient(135deg, #16a085, #1abc9c); border: none; padding: 8px 18px; font-weight: bold; color: #fff;">Create Periods</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        var translations = {
            search: "{{ __('messages.search') }}"
        };
    </script>
@endsection
