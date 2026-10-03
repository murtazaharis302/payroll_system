@extends('admin.layouts.Master')
@section('title') Deduction Types Catalog @endsection
@section('content')
    <div class="col-12">
        <div class="card mt-4 shadow-sm border-0" style="border: 1px solid #e9ecef !important;">
            <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center" style="border-bottom: 2px solid #ef4444 !important;">
                <div>
                    <h4 class="mb-0 font-weight-bold text-dark"><i class="mdi mdi-cash-minus me-2 text-danger"></i> Deduction Types Master Catalog</h4>
                    <small class="text-muted">Manage statutory, loan, and company-custom deduction categories</small>
                </div>
                <a href="{{ panelRoute('deduction-types.create') }}" class="btn font-weight-bold text-white shadow-sm" style="background-color: #ef4444; border: none; padding: 8px 18px;">
                    <i class="mdi mdi-plus-circle me-1"></i> Create Deduction Type
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
                <form action="{{ panelRoute('deduction-types.index') }}" method="GET" class="row g-2 mb-3">
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
                                <th>Deduction Name</th>
                                <th>Category</th>
                                <th>Origin</th>
                                <th>Created By</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($deductionTypes as $deductionType)
                                <tr>
                                    <td><span class="badge bg-light text-dark border font-mono">{{ $deductionType->code }}</span></td>
                                    <td>
                                        <span class="font-weight-bold text-dark d-block">{{ $deductionType->name }}</span>
                                        @if($deductionType->description)
                                            <small class="text-muted">{{ Str::limit($deductionType->description, 50) }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        @if($deductionType->type == 'statutory')
                                            <span class="badge bg-primary text-white"><i class="mdi mdi-shield-check me-1"></i> Statutory</span>
                                        @elseif($deductionType->type == 'loan')
                                            <span class="badge bg-warning text-dark"><i class="mdi mdi-bank me-1"></i> Loan</span>
                                        @else
                                            <span class="badge bg-secondary text-white"><i class="mdi mdi-format-list-bulleted me-1"></i> Other</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($deductionType->company_id === null)
                                            <span class="badge bg-primary text-white" title="System-wide default deduction type"><i class="mdi mdi-earth me-1"></i> System Default</span>
                                        @else
                                            <span class="badge bg-success text-white" title="Custom deduction type created for this company"><i class="mdi mdi-domain me-1"></i> Company Custom</span>
                                        @endif
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            {{ $deductionType->creator->name ?? 'System' }}
                                        </small>
                                    </td>
                                    <td>
                                        <form action="{{ panelRoute('deduction-types.toggle-status', $deductionType->id) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm p-0 border-0 background-none" title="Click to toggle status">
                                                @if($deductionType->is_active)
                                                    <span class="badge bg-success" style="cursor: pointer;"><i class="mdi mdi-check-circle me-1"></i> Active</span>
                                                @else
                                                    <span class="badge bg-secondary" style="cursor: pointer;"><i class="mdi mdi-pause-circle me-1"></i> Inactive</span>
                                                @endif
                                            </button>
                                        </form>
                                    </td>
                                    <td class="text-end">
                                        @if(auth()->user()->role === 'admin' || $deductionType->company_id !== null)
                                            <a href="{{ panelRoute('deduction-types.edit', $deductionType->id) }}" class="btn btn-sm btn-outline-primary me-1" title="Edit">
                                                <i class="mdi mdi-pencil"></i>
                                            </a>
                                            <form action="{{ panelRoute('deduction-types.destroy', $deductionType->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this deduction type?');">
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
                                        No deduction types found.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">
                    {{ $deductionTypes->links() }}
                </div>
            </div> 
        </div> 
    </div>
@endsection
