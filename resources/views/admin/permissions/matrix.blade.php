@extends('admin.layouts.Master')

@section('title', 'Role Permissions Matrix')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-flex align-items-center justify-content-between">
                <h4 class="page-title mb-0">Role Permissions Matrix</h4>
                <button type="submit" form="matrix-form" class="btn btn-primary btn-sm shadow-sm">
                    <i class="fe-save me-1"></i> Save All Changes
                </button>
            </div>
        </div>
    </div>

    @if(session('statusSuccess'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fe-check-circle me-1"></i> {{ session('statusSuccess') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm border-0" style="background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); border-radius: 12px;">
                <div class="card-body">
                    <form id="matrix-form" method="POST" action="{{ function_exists('panelRoute') ? panelRoute('permissions.save') : route('company.permissions.save') }}">
                        @csrf
                        <input type="hidden" name="company_id" value="{{ $companyId }}">

                        <div class="d-flex align-items-center justify-content-between flex-wrap mb-3">
                            <div>
                                <h5 class="card-title mb-1">Role Delegation Matrix</h5>
                                <p class="text-muted mb-0 font-13">Toggles auto-save immediately on switch, or click <strong>Save All Changes</strong> to persist bulk decisions.</p>
                            </div>
                            @if(!empty($isSuperAdmin) && !empty($companies) && $companies->count() > 0)
                                <div class="mt-2 mt-sm-0">
                                    <div class="d-flex align-items-center">
                                        <label class="form-label font-12 fw-semibold me-2 mb-0 text-nowrap">Target Company:</label>
                                        <select name="company_id_selector" class="form-select form-select-sm" onchange="window.location.href='{{ route(auth()->user()->role === 'admin' || auth()->user()->role_id === \App\Models\Role::SUPER_ADMIN ? 'admin.permissions.matrix' : 'company.permissions.matrix') }}?company_id=' + this.value">
                                            @foreach($companies as $comp)
                                                <option value="{{ $comp->id }}" {{ (int)$companyId === (int)$comp->id ? 'selected' : '' }}>
                                                    {{ $comp->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            @endif
                        </div>

                        <div class="table-responsive mt-3">
                            <table class="table table-hover table-centered mb-0 align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Module Name</th>
                                        <th>Category (Slug)</th>
                                        @if(!empty($isSuperAdmin))
                                            <th class="text-center" style="width: 200px;">Company Admin (Tier 1)</th>
                                        @endif
                                        <th class="text-center" style="width: 220px;">HR Role Access</th>
                                        <th class="text-center" style="width: 220px;">Employee Role Access</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($menus as $menu)
                                        @php
                                            $hasCompanyEntitlement = \App\Models\MenuRoleAccess::checkAccess($companyId, \App\Models\Role::COMPANY_ADMIN, $menu->id);
                                            $hrAccess = \App\Models\MenuRoleAccess::checkAccess($companyId, \App\Models\Role::HR, $menu->id);
                                            $employeeAccess = \App\Models\MenuRoleAccess::checkAccess($companyId, \App\Models\Role::EMPLOYEE, $menu->id);
                                        @endphp
                                        <tr @if($menu->parent_id) class="bg-light bg-opacity-50" @endif>
                                            <td>
                                                <div class="d-flex align-items-center @if($menu->parent_id) ms-4 ps-2 @endif">
                                                    <div class="avatar-sm d-inline-block me-3">
                                                        <span class="avatar-title @if($menu->parent_id) bg-soft-info text-info @else bg-soft-primary text-primary @endif rounded-circle" style="width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; font-size: 14px;">
                                                            <i class="{{ $menu->icon ?? 'fe-layers' }}"></i>
                                                        </span>
                                                    </div>
                                                    <div>
                                                        <span class="fw-medium">{{ $menu->name }}</span>
                                                        @if($menu->parent_id)
                                                            <span class="badge bg-soft-info text-info ms-1 font-10">Sub-Capability</span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </td>
                                            <td><code>{{ $menu->module }}</code></td>

                                            {{-- Super Admin Tier 1 Switch --}}
                                            @if(!empty($isSuperAdmin))
                                                <td class="text-center">
                                                    <div class="form-check form-switch d-inline-block">
                                                        <input class="form-check-input toggle-permission" type="checkbox"
                                                               name="permissions[{{ $menu->id }}][{{ \App\Models\Role::COMPANY_ADMIN }}]"
                                                               value="1"
                                                               data-menu-id="{{ $menu->id }}"
                                                               data-role-id="{{ \App\Models\Role::COMPANY_ADMIN }}"
                                                               id="switch-company-{{ $menu->id }}"
                                                               {{ $hasCompanyEntitlement ? 'checked' : '' }}
                                                               style="cursor: pointer; width: 40px; height: 20px;">
                                                    </div>
                                                </td>
                                            @endif

                                            {{-- HR Switch --}}
                                            <td class="text-center">
                                                @if(!$hasCompanyEntitlement && empty($isSuperAdmin))
                                                    <span class="badge bg-soft-secondary text-secondary border border-secondary px-2 py-1">Disabled by Host</span>
                                                @else
                                                    <div class="form-check form-switch d-inline-block">
                                                        <input class="form-check-input toggle-permission" type="checkbox"
                                                               name="permissions[{{ $menu->id }}][{{ \App\Models\Role::HR }}]"
                                                               value="1"
                                                               data-menu-id="{{ $menu->id }}"
                                                               data-role-id="{{ \App\Models\Role::HR }}"
                                                               id="switch-hr-{{ $menu->id }}"
                                                               {{ $hrAccess ? 'checked' : '' }}
                                                               style="cursor: pointer; width: 40px; height: 20px;">
                                                    </div>
                                                @endif
                                            </td>

                                            {{-- Employee Switch --}}
                                            <td class="text-center">
                                                @if(!$hasCompanyEntitlement && empty($isSuperAdmin))
                                                    <span class="badge bg-soft-secondary text-secondary border border-secondary px-2 py-1">Disabled by Host</span>
                                                @else
                                                    <div class="form-check form-switch d-inline-block">
                                                        <input class="form-check-input toggle-permission" type="checkbox"
                                                               name="permissions[{{ $menu->id }}][{{ \App\Models\Role::EMPLOYEE }}]"
                                                               value="1"
                                                               data-menu-id="{{ $menu->id }}"
                                                               data-role-id="{{ \App\Models\Role::EMPLOYEE }}"
                                                               id="switch-employee-{{ $menu->id }}"
                                                               {{ $employeeAccess ? 'checked' : '' }}
                                                               style="cursor: pointer; width: 40px; height: 20px;">
                                                    </div>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="d-flex justify-content-end mt-4">
                            <button type="submit" class="btn btn-primary px-4 shadow-sm">
                                <i class="fe-save me-1"></i> Save Permissions Matrix
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Toast Feedback Container -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1050">
    <div id="toast-feedback" class="toast align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body" id="toast-message">
                Permission updated successfully.
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const switches = document.querySelectorAll('.toggle-permission');
    const toastElement = document.getElementById('toast-feedback');
    const toastMessage = document.getElementById('toast-message');
    const toast = new bootstrap.Toast(toastElement);

    switches.forEach(el => {
        el.addEventListener('change', function () {
            const menuId = this.getAttribute('data-menu-id');
            const roleId = this.getAttribute('data-role-id');
            const isEnabled = this.checked ? 1 : 0;
            const toggleUrl = '{{ function_exists("panelRoute") ? panelRoute("permissions.toggle") : route("company.permissions.toggle") }}';

            fetch(toggleUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    menu_id: menuId,
                    role_id: roleId,
                    is_enabled: isEnabled,
                    company_id: {{ $companyId }}
                })
            })
            .then(async response => {
                const data = await response.json();
                if (response.ok && data.success !== false) {
                    toastElement.classList.remove('bg-danger');
                    toastElement.classList.add('bg-success');
                    toastMessage.textContent = data.message || 'Permission updated successfully.';
                    toast.show();
                } else {
                    toastElement.classList.remove('bg-success');
                    toastElement.classList.add('bg-danger');
                    toastMessage.textContent = data.message || 'Error saving settings.';
                    toast.show();
                    this.checked = !this.checked; // Revert switch state on failure
                }
            })
            .catch(error => {
                console.error('Error:', error);
                toastElement.classList.remove('bg-success');
                toastElement.classList.add('bg-danger');
                toastMessage.textContent = 'Server connection error.';
                toast.show();
                this.checked = !this.checked; // Revert switch state on error
            });
        });
    });
});
</script>
@endsection
