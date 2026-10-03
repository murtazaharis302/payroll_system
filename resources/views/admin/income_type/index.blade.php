@extends('admin.layouts.Master')
@section('title') Income Types Catalog @endsection
@section('content')
    <div class="col-12">
        <div class="card mt-4 shadow-sm border-0" style="border: 1px solid #e9ecef !important;">
            <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center" style="border-bottom: 2px solid #10b981 !important;">
                <div>
                    <h4 class="mb-0 font-weight-bold text-dark"><i class="mdi mdi-cash-plus me-2 text-success"></i> Income Types Master Catalog</h4>
                    <small class="text-muted">Manage system default and company-custom earnings categories</small>
                </div>
                <a href="{{ panelRoute('income-types.create') }}" class="btn font-weight-bold text-white shadow-sm" style="background-color: #10b981; border: none; padding: 8px 18px;">
                    <i class="mdi mdi-plus-circle me-1"></i> Create Income Type
                </a>
            </div>
            <div class="card-body p-3">
                @if(session('statusSuccess') || session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="mdi mdi-check-circle me-1"></i> {{ session('statusSuccess') ?? session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                @if(session('statusDanger') || session('error'))
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="mdi mdi-alert-circle me-1"></i> {{ session('statusDanger') ?? session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                <!-- Search Bar -->
                <form action="{{ panelRoute('income-types.index') }}" method="GET" class="row g-2 mb-3">
                    <div class="col-md-4">
                        <div class="input-group input-group-sm">
                            <input type="text" name="search" class="form-control" placeholder="Search by name or code..." value="{{ request('search') }}">
                            <button class="btn btn-outline-secondary" type="submit"><i class="mdi mdi-magnify"></i> Search</button>
                        </div>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Code</th>
                                <th>Income Name</th>
                                <th>Origin</th>
                                <th>Taxable</th>
                                <th>Created By</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($incomeTypes as $incomeType)
                                <tr>
                                    <td><span class="badge bg-light text-dark border font-mono">{{ $incomeType->code }}</span></td>
                                    <td>
                                        <span class="font-weight-bold text-dark d-block">{{ $incomeType->name }}</span>
                                        @if($incomeType->description)
                                            <small class="text-muted">{{ Str::limit($incomeType->description, 50) }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        @if($incomeType->company_id === null)
                                            <span class="badge bg-primary text-white" title="System-wide default income type"><i class="mdi mdi-earth me-1"></i> System Default</span>
                                        @else
                                            <span class="badge bg-success text-white" title="Custom income type created for this company"><i class="mdi mdi-domain me-1"></i> Company Custom</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($incomeType->is_taxable)
                                            <span class="badge bg-warning text-dark">Taxable</span>
                                        @else
                                            <span class="badge bg-info text-white">Non-Taxable</span>
                                        @endif
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            {{ $incomeType->creator->name ?? 'System' }}
                                        </small>
                                    </td>
                                    <td>
                                        <form action="{{ panelRoute('income-types.toggle-status', $incomeType->id) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm p-0 border-0 background-none" title="Click to toggle status">
                                                @if($incomeType->is_active)
                                                    <span class="badge bg-success" style="cursor: pointer;"><i class="mdi mdi-check-circle me-1"></i> Active</span>
                                                @else
                                                    <span class="badge bg-secondary" style="cursor: pointer;"><i class="mdi mdi-pause-circle me-1"></i> Inactive</span>
                                                @endif
                                            </button>
                                        </form>
                                    </td>
                                    <td class="text-end">
                                        @if(auth()->user()->role === 'admin' || $incomeType->company_id !== null)
                                            <a href="{{ panelRoute('income-types.edit', $incomeType->id) }}" class="btn btn-sm btn-outline-primary me-1" title="Edit">
                                                <i class="mdi mdi-pencil"></i>
                                            </a>
                                            <form action="{{ panelRoute('income-types.destroy', $incomeType->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this income type?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                                    <i class="mdi mdi-trash-can"></i>
                                                </button>
                                            </form>
                                        @else
                                            <span class="badge bg-light text-muted border">System Protected</span>
                                        @endif
                                    </td>    
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">
                                        No income types found.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">
                    {{ $incomeTypes->links() }}
                </div>
            </div> 
        </div> 
    </div>
@endsection
