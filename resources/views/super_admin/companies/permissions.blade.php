@extends('admin.layouts.master')

@section('title', 'Company Permissions Matrix')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <h4 class="page-title">Tenant Permission Matrix: {{ $company->name }}</h4>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm border-0" style="background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(10px); border-radius: 12px;">
                <div class="card-body">
                    <h5 class="card-title mb-4">Module Entitlements (Tier 1)</h5>
                    <p class="text-muted">Control which modules are enabled for this company's subscription. Disabling a module here restricts access for all roles within the company.</p>

                    <div class="table-responsive mt-4">
                        <table class="table table-hover table-centered mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Module Name</th>
                                    <th>Identifier (Slug)</th>
                                    <th>Route Name</th>
                                    <th class="text-center" style="width: 150px;">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($menus as $menu)
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-sm d-inline-block me-3">
                                                    <span class="avatar-title bg-soft-primary text-primary rounded-circle" style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; font-size: 16px;">
                                                        <i class="{{ $menu->icon ?? 'fe-layers' }}"></i>
                                                    </span>
                                                </div>
                                                <span class="fw-medium">{{ $menu->name }}</span>
                                            </div>
                                        </td>
                                        <td><code>{{ $menu->module }}</code></td>
                                        <td><code>{{ $menu->route_name }}</code></td>
                                        <td class="text-center">
                                            @php
                                                // Check if explicitly disabled or enabled in DB
                                                $isEnabled = \App\Models\MenuRoleAccess::checkAccess($company->id, \App\Models\Role::COMPANY_ADMIN, $menu->id);
                                            @endphp
                                            <div class="form-check form-switch d-inline-block">
                                                <input class="form-check-input toggle-entitlement" type="checkbox" 
                                                       data-menu-id="{{ $menu->id }}" 
                                                       id="switch-{{ $menu->id }}" 
                                                       {{ $isEnabled ? 'checked' : '' }}
                                                       style="cursor: pointer; width: 40px; height: 20px;">
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Toast Notifications Container -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
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
    const switches = document.querySelectorAll('.toggle-entitlement');
    const toastElement = document.getElementById('toast-feedback');
    const toastMessage = document.getElementById('toast-message');
    const toast = new bootstrap.Toast(toastElement);

    switches.forEach(el => {
        el.addEventListener('change', function () {
            const menuId = this.getAttribute('data-menu-id');
            const isEnabled = this.checked ? 1 : 0;

            fetch(`/super-admin/companies/{{ $company->id }}/permissions/toggle`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    menu_id: menuId,
                    is_enabled: isEnabled
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    toastElement.classList.remove('bg-danger');
                    toastElement.classList.add('bg-success');
                    toastMessage.textContent = data.message;
                } else {
                    toastElement.classList.remove('bg-success');
                    toastElement.classList.add('bg-danger');
                    toastMessage.textContent = data.message || 'Error saving settings.';
                    this.checked = !this.checked; // Revert
                }
                toast.show();
            })
            .catch(error => {
                console.error('Error:', error);
                toastElement.classList.remove('bg-success');
                toastElement.classList.add('bg-danger');
                toastMessage.textContent = 'Server connection error.';
                this.checked = !this.checked; // Revert
                toast.show();
            });
        });
    });
});
</script>
@endsection
