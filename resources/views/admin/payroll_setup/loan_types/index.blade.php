@extends('admin.layouts.Master')
@section('title') Loan Type Amortization Setup @endsection
@section('content')
    <div class="col-12">
        <div class="card mt-4 shadow-sm border-0" style="border: 1px solid #e9ecef !important;">
            <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center" style="border-bottom: 2px solid #f06115 !important;">
                <div>
                    <h4 class="mb-0 font-weight-bold text-dark"><i class="mdi mdi-cash-refund me-2" style="color: #f06115;"></i> Loan Types & Amortization Rules</h4>
                    <small class="text-muted">Configure default repayment rules, cutoff amortization rates, and company loan categories</small>
                </div>
                <button type="button" class="btn font-weight-bold text-white shadow-sm" style="background-color: #f06115; border: none; padding: 8px 18px;" data-bs-toggle="modal" data-bs-target="#createLoanTypeModal">
                    <i class="mdi mdi-plus-circle me-1"></i> Add Loan Type
                </button>
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

                <!-- Controls Bar -->
                <div class="row g-2 mb-3 align-items-center">
                    <div class="col-md-5">
                        <form action="{{ panelRoute('loan-types.index') }}" method="GET" class="d-flex gap-2">
                            @if(isset($isSuperAdmin) && $isSuperAdmin && isset($companyId))
                                <input type="hidden" name="company_id" value="{{ $companyId }}">
                            @endif
                            <div class="input-group input-group-sm">
                                <input type="text" name="search" class="form-control" placeholder="Search loan types..." value="{{ request('search') }}">
                                <button class="btn btn-outline-secondary" type="submit"><i class="mdi mdi-magnify"></i> Search</button>
                            </div>
                        </form>
                    </div>

                    @if(isset($isSuperAdmin) && $isSuperAdmin && isset($companies) && $companies->count() > 0)
                        <div class="col-md-5 ms-auto">
                            <form action="{{ panelRoute('loan-types.index') }}" method="GET" class="d-flex align-items-center justify-content-end gap-2">
                                <label for="company_id_select" class="form-label mb-0 small text-dark font-weight-bold text-nowrap">Filter Company:</label>
                                <select name="company_id" id="company_id_select" class="form-select form-select-sm" onchange="this.form.submit()">
                                    <option value="">-- All Companies / Defaults --</option>
                                    @foreach($companies as $comp)
                                        <option value="{{ $comp->id }}" {{ (string) $companyId === (string) $comp->id ? 'selected' : '' }}>
                                            {{ $comp->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </form>
                        </div>
                    @endif
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Loan Category Name</th>
                                <th>Mapped Deduction Type</th>
                                <th>Default Amortization / Cutoff (₱)</th>
                                <th>Default Installments</th>
                                <th>Tenant Scope</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($loanTypes as $type)
                                <tr>
                                    <td>
                                        <span class="font-weight-bold text-dark d-block" style="font-size: 14px;">{{ $type->name }}</span>
                                    </td>
                                    <td>
                                        @if($type->deductionType)
                                            <span class="badge bg-secondary text-dark border font-mono" style="font-size: 13px;"><i class="mdi mdi-link-variant me-1"></i> {{ $type->deductionType->name }}</span>
                                        @else
                                            <span class="badge bg-danger text-white"><i class="mdi mdi-alert-circle me-1"></i> Unmapped</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="font-weight-bold text-success font-mono" style="font-size: 14px;">
                                            ₱{{ number_format((float) ($type->default_amortization_per_cutoff ?? 0), 2) }}
                                        </span>
                                    </td>
                                    <td>
                                        @if($type->default_installments_count)
                                            <span class="badge bg-light text-dark border">{{ $type->default_installments_count }} Cutoffs</span>
                                        @else
                                            <span class="text-muted small">N/A</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($type->company_id === null)
                                            <span class="badge bg-primary text-white" title="Global default loan type"><i class="mdi mdi-earth me-1"></i> System Global</span>
                                        @else
                                            <span class="badge bg-info text-white" title="Company custom loan type"><i class="mdi mdi-domain me-1"></i> {{ $type->company->name ?? 'Company Custom' }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <form action="{{ panelRoute('loan-types.toggle-status', $type->id) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm p-0 border-0 background-none" title="Click to toggle status">
                                                @if($type->is_active)
                                                    <span class="badge bg-success" style="cursor: pointer;"><i class="mdi mdi-check-circle me-1"></i> Active</span>
                                                @else
                                                    <span class="badge bg-secondary" style="cursor: pointer;"><i class="mdi mdi-pause-circle me-1"></i> Inactive</span>
                                                @endif
                                            </button>
                                        </form>
                                    </td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm btn-outline-primary me-1" 
                                                onclick="openEditModal({{ json_encode($type) }})" title="Edit Settings">
                                            <i class="mdi mdi-pencil"></i> Edit
                                        </button>
                                        <form action="{{ panelRoute('loan-types.destroy', $type->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this loan type?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                                <i class="mdi mdi-trash-can"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">
                                        No loan types configured yet. Click "Add Loan Type" above to set repayment default rules.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $loanTypes->links() }}
                </div>
            </div>
        </div>
    </div>

    <!-- Create Loan Type Modal -->
    <div class="modal fade" id="createLoanTypeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="{{ panelRoute('loan-types.store') }}" method="POST">
                    @csrf
                    <div class="modal-header bg-light">
                        <h5 class="modal-title font-weight-bold text-dark"><i class="mdi mdi-plus-circle me-1 text-primary"></i> Create Loan Type</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-3">
                        @if(isset($isSuperAdmin) && $isSuperAdmin)
                            <div class="mb-3">
                                <label for="create_company_id" class="form-label font-weight-semibold">Company Scope</label>
                                <select name="company_id" id="create_company_id" class="form-select">
                                    <option value="">-- Global Default (All Companies) --</option>
                                    @foreach($companies as $comp)
                                        <option value="{{ $comp->id }}">{{ $comp->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        <div class="mb-3">
                            <label for="create_name" class="form-label font-weight-semibold">Loan Type Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="create_name" class="form-control" placeholder="e.g. Cash Advance / Vale, SSS Loan" required>
                        </div>

                        <div class="mb-3">
                            <label for="create_deduction_type_id" class="form-label font-weight-semibold">Mapped Deduction Type <span class="text-danger">*</span></label>
                            <select name="deduction_type_id" id="create_deduction_type_id" class="form-select" required>
                                <option value="">-- Select Mapped Deduction Type --</option>
                                @foreach($deductionTypes as $dt)
                                    <option value="{{ $dt->id }}">{{ $dt->name }} ({{ $dt->code }})</option>
                                @endforeach
                            </select>
                            <small class="text-muted">Required: Payroll deductions for this loan type will use this explicit mapping.</small>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="create_default_amortization" class="form-label font-weight-semibold">Default Cutoff Amortization (₱)</label>
                                <input type="number" step="0.01" min="0" name="default_amortization_per_cutoff" id="create_default_amortization" class="form-control" placeholder="0.00">
                                <small class="text-muted">Auto-populates cutoff deduction</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="create_default_installments" class="form-label font-weight-semibold">Default Cutoffs / Installments</label>
                                <input type="number" min="1" name="default_installments_count" id="create_default_installments" class="form-control" placeholder="e.g. 10">
                                <small class="text-muted">Optional default cutoffs</small>
                            </div>
                        </div>

                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" name="is_active" id="create_is_active" value="1" checked>
                            <label class="form-check-label font-weight-semibold text-dark" for="create_is_active">Active Loan Category</label>
                        </div>
                    </div>
                    <div class="modal-footer bg-light py-2">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn text-white font-weight-bold" style="background-color: #f06115;">Save Loan Type</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Loan Type Modal -->
    <div class="modal fade" id="editLoanTypeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="editLoanTypeForm" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="modal-header bg-light">
                        <h5 class="modal-title font-weight-bold text-dark"><i class="mdi mdi-pencil me-1 text-primary"></i> Edit Loan Type Amortization Settings</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-3">
                        <div class="mb-3">
                            <label for="edit_name" class="form-label font-weight-semibold">Loan Type Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="edit_name" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label for="edit_deduction_type_id" class="form-label font-weight-semibold">Mapped Deduction Type <span class="text-danger">*</span></label>
                            <select name="deduction_type_id" id="edit_deduction_type_id" class="form-select" required>
                                <option value="">-- Select Mapped Deduction Type --</option>
                                @foreach($deductionTypes as $dt)
                                    <option value="{{ $dt->id }}">{{ $dt->name }} ({{ $dt->code }})</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_default_amortization" class="form-label font-weight-semibold">Default Cutoff Amortization (₱)</label>
                                <input type="number" step="0.01" min="0" name="default_amortization_per_cutoff" id="edit_default_amortization" class="form-control" placeholder="0.00">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_default_installments" class="form-label font-weight-semibold">Default Cutoffs / Installments</label>
                                <input type="number" min="1" name="default_installments_count" id="edit_default_installments" class="form-control" placeholder="e.g. 10">
                            </div>
                        </div>

                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" name="is_active" id="edit_is_active" value="1">
                            <label class="form-check-label font-weight-semibold text-dark" for="edit_is_active">Active Loan Category</label>
                        </div>
                    </div>
                    <div class="modal-footer bg-light py-2">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn text-white font-weight-bold" style="background-color: #f06115;">Update Settings</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
<script>
function openEditModal(type) {
    const updateUrlTemplate = "{{ panelRoute('loan-types.update', ['id' => '__ID__']) }}";
    const updateUrl = updateUrlTemplate.replace('__ID__', type.id);
    document.getElementById('editLoanTypeForm').action = updateUrl;
    document.getElementById('edit_name').value = type.name || '';
    document.getElementById('edit_deduction_type_id').value = type.deduction_type_id || '';
    document.getElementById('edit_default_amortization').value = (type.default_amortization_per_cutoff !== null) ? parseFloat(type.default_amortization_per_cutoff).toFixed(2) : '0.00';
    document.getElementById('edit_default_installments').value = type.default_installments_count || '';
    document.getElementById('edit_is_active').checked = !!type.is_active;

    const modal = new bootstrap.Modal(document.getElementById('editLoanTypeModal'));
    modal.show();
}
</script>
@endsection
