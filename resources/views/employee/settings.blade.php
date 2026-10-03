@extends('admin.layouts.Master')

@section('title') Settings @endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="card mt-3 shadow-sm border-0">
            <div class="card-header bg-white border-bottom py-3">
                <h4 class="card-title mb-0 text-dark fw-bold">
                    <i class="fe-settings text-primary me-2"></i> Account Settings
                </h4>
            </div>
            <div class="card-body p-4">
                @if (session('status'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fe-check-circle me-1"></i> {{ session('status') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                @if (session('statusDanger'))
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fe-alert-triangle me-1"></i> {{ session('statusDanger') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <ul class="mb-0 ps-3">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                <div class="user-profile-summary mb-4 p-3 bg-light rounded border">
                    <div class="d-flex align-items-center">
                        <div class="avatar-lg bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px; font-size: 1.25rem;">
                            {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}
                        </div>
                        <div>
                            <h5 class="mb-1 text-dark">{{ auth()->user()->name }}</h5>
                            <p class="text-muted mb-0 small"><i class="fe-mail me-1"></i> {{ auth()->user()->email }} &bull; <span class="badge bg-soft-info text-info text-capitalize">{{ auth()->user()->role ?? 'User' }}</span></p>
                        </div>
                    </div>
                </div>

                <div class="border-top pt-3">
                    <h5 class="text-dark mb-3"><i class="fe-lock me-1"></i> Change Password</h5>
                    
                    <form action="{{ panelRoute('settings.update-password') }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label for="current_password" class="form-label font-weight-bold">Current Password <span class="text-danger">*</span></label>
                            <input type="password" name="current_password" id="current_password" class="form-control @error('current_password') is-invalid @enderror" placeholder="Enter your current password" required>
                            @error('current_password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="new_password" class="form-label font-weight-bold">New Password <span class="text-danger">*</span></label>
                            <input type="password" name="new_password" id="new_password" class="form-control @error('new_password') is-invalid @enderror" placeholder="Enter your new password (min. 8 characters)" required>
                            @error('new_password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="new_password_confirmation" class="form-label font-weight-bold">Confirm New Password <span class="text-danger">*</span></label>
                            <input type="password" name="new_password_confirmation" id="new_password_confirmation" class="form-control" placeholder="Re-enter your new password" required>
                        </div>

                        <div class="d-flex justify-content-end mt-4">
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="fe-save me-1"></i> Update Password
                            </button>
                        </div>
                    </form>
                </div>            </div>
        </div>
    </div>
</div>
@endsection
