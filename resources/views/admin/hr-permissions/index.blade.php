@extends('admin.layouts.Master')

@section('title', 'HR Permissions Management')

@section('content')
<div class="content-page">
    <div class="content">
        <div class="container-fluid">

            <!-- Page Title -->
            <div class="row">
                <div class="col-12">
                    <div class="page-title-box d-flex align-items-center justify-content-between py-3">
                        <h4 class="mb-0 text-dark font-weight-bold">
                            <i class="fe-shield me-2 text-primary"></i> HR Delegated Permissions
                        </h4>
                        <div class="page-title-right">
                            <ol class="breadcrumb m-0 font-12">
                                <li class="breadcrumb-item"><a href="javascript: void(0);">Payroll System</a></li>
                                <li class="breadcrumb-item active">HR Permissions</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Flash Status Alert -->
            @if(session('status'))
                <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
                    <i class="mdi mdi-check-circle-outline me-2 font-16"></i> {{ session('status') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @if(session('statusDanger'))
                <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
                    <i class="mdi mdi-alert-circle-outline me-2 font-16"></i> {{ session('statusDanger') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            <!-- Super Admin Company Filter -->
            @if($isSuperAdmin && $companies)
                <div class="card border-0 shadow-sm mb-4" style="border: 1px solid #e2e8f0 !important;">
                    <div class="card-body py-3">
                        <form method="GET" action="{{ route('admin.hr-permissions.index') }}" class="row align-items-center g-3">
                            <div class="col-md-3">
                                <label for="company_id" class="form-label font-weight-semibold text-dark mb-0"><i class="mdi mdi-domain me-1 text-primary"></i> Select Target Company:</label>
                            </div>
                            <div class="col-md-6">
                                <select name="company_id" id="company_id" class="form-select form-select-sm font-weight-semibold" onchange="this.form.submit()">
                                    @foreach($companies as $comp)
                                        <option value="{{ $comp->id }}" {{ (int) $companyId === (int) $comp->id ? 'selected' : '' }}>
                                            {{ $comp->name }} ({{ $comp->company_code }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </form>
                    </div>
                </div>
            @endif

            @if($selectedCompany)
                <div class="row">
                    <div class="col-lg-12">
                        <form method="POST" action="{{ $isSuperAdmin ? route('admin.hr-permissions.update') : route('company.hr-permissions.update') }}">
                            @csrf
                            <input type="hidden" name="company_id" value="{{ $selectedCompany->id }}">

                            <div class="card border-0 shadow-sm mb-4" style="border: 1px solid #e2e8f0 !important;">
                                <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center" style="border-bottom: 2px solid #3b82f6 !important;">
                                    <div>
                                        <h5 class="card-title mb-0 font-weight-bold text-dark">
                                            <i class="fe-lock me-2 text-primary"></i> HR Delegated Capabilities — {{ $selectedCompany->name }}
                                        </h5>
                                        <small class="text-muted">Control which specific payroll input capabilities HR staff are permitted to perform for this company.</small>
                                    </div>
                                    <button type="submit" class="btn btn-primary btn-sm px-4 font-weight-semibold shadow-sm">
                                        <i class="mdi mdi-content-save me-1"></i> Save HR Permissions
                                    </button>
                                </div>
                                <div class="card-body p-4">
                                    <div class="row g-4">

                                        @foreach($capabilities as $module => $capability)
                                        <div class="col-md-12">
                                            <div class="p-3 rounded border bg-light d-flex justify-content-between align-items-start" style="border: 1px solid #cbd5e1 !important;">
                                                <div class="me-3">
                                                    <div class="d-flex align-items-center mb-1">
                                                        <h6 class="font-weight-bold text-dark mb-0 me-2">{{ $capability['name'] }}</h6>
                                                        <span class="badge {{ $capability['enabled'] ? 'bg-success' : 'bg-secondary' }}">
                                                            {{ $capability['enabled'] ? 'ENABLED' : 'DISABLED (Default)' }}
                                                        </span>
                                                    </div>
                                                    <p class="text-muted font-13 mb-0">{{ $capability['description'] }}</p>
                                                </div>
                                                <div class="form-check form-switch form-switch-md ms-3">
                                                    <input class="form-check-input"
                                                           type="checkbox" 
                                                           id="{{ $module }}"
                                                           name="{{ $module }}"
                                                           value="1" 
                                                           {{ $capability['enabled'] ? 'checked' : '' }}
                                                           style="width: 2.5em; height: 1.25em; cursor: pointer;">
                                                </div>
                                            </div>
                                        </div>
                                        @endforeach

                                    </div>
                                </div>
                                <div class="card-footer bg-white py-3 px-4 d-flex justify-content-end" style="border-top: 1px solid #f1f5f9;">
                                    <button type="submit" class="btn btn-primary btn-sm px-4 font-weight-semibold shadow-sm">
                                        <i class="mdi mdi-content-save me-1"></i> Save HR Permissions
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            @else
                <div class="card border-0 shadow-sm p-4 text-center">
                    <p class="text-muted mb-0">No active company selected. Please select a company to manage HR permissions.</p>
                </div>
            @endif

        </div>
    </div>
</div>
@endsection
