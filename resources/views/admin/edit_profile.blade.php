@extends('admin.layouts.Master')
@section('title')
    {{ __('messages.edit_title') }}
@endsection
@section('content')

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    @if (session('success'))
                        <div id="alert-success" class="alert alert-success alert-dismissible fade show" role="alert">
                            {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif

                    @if ($errors->any())
                        <div id="alert-danger" class="alert alert-danger alert-dismissible fade show" role="alert">
                            <ul class="mb-0">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif
                    {{-- Tab Navigation --}}
                    <ul class="nav nav-tabs" id="profileTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="user-tab" data-bs-toggle="tab" data-bs-target="#userInfo"
                                type="button" role="tab">
                                {{ __('messages.user_info') }}
                            </button>
                        </li>
                        @if (auth()->user()->role === 'company')
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="company-tab" data-bs-toggle="tab" data-bs-target="#companyInfo"
                                    type="button" role="tab">
                                    {{ __('messages.company_info') }}
                                </button>
                            </li>
                        @endif
                    </ul>

                    {{-- Tab Content --}}
                    <div class="tab-content pt-3" id="profileTabsContent">
                        {{-- User Info Tab --}}
                        <div class="tab-pane fade show active" id="userInfo" role="tabpanel">
                            <form action="{{ panelRoute('update_profile', $user->id) }}" method="POST"
                                enctype="multipart/form-data">
                                @csrf

                                <div class="mb-3 text-center">
                                    @if ($user->profile_image)
                                        <img src="{{ asset($user->profile_image) }}" alt="Profile Image"
                                            class="rounded-circle mb-3 profile-preview-img"
                                            style="width: 120px; height: 120px; object-fit: cover;">
                                    @else
                                        <img src="{{ asset('frontend-assets/assets/profile/default.png') }}"
                                            alt="Default Profile Image" class="rounded-circle mb-3 profile-preview-img"
                                            style="width: 120px; height: 120px; object-fit: cover;">
                                    @endif
                                    <div class="text-start">
                                        <label for="profile_image"
                                            class="form-label">{{ __('messages.profile_image') }}</label>
                                        <input type="file" name="profile_image" class="form-control" accept="image/*">
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="name" class="form-label">{{ __('messages.profile_name') }}</label>
                                    <input type="text" name="name" class="form-control"
                                        value="{{ old('name', $user->name) }}">
                                </div>

                                <div class="mb-3">
                                    <label for="email" class="form-label">{{ __('messages.email') }}</label>
                                    <input type="email" class="form-control" value="{{ old('email', $user->email) }}"
                                        disabled>
                                </div>

                                <div class="mb-3">
                                    <label for="phone" class="form-label">{{ __('messages.number') }}</label>
                                    <input type="text" name="phone" class="form-control"
                                        value="{{ old('phone', $user->phone) }}">
                                </div>

                                <div class="text-end">
                                    <button type="submit" class="btn btn-info">{{ __('messages.save') }}</button>
                                </div>
                            </form>
                        </div>
                        @if (auth()->user()->role === 'company')
                            @php
                                $companyObj = $company ?? $user->companies ?? $user->company;
                                $companyLogo = $companyObj?->company_profile;
                            @endphp
                            {{-- Company Info Tab --}}
                            <div class="tab-pane fade" id="companyInfo" role="tabpanel">
                                <form action="{{ panelRoute('update_profile', $user->id) }}" method="POST"
                                    enctype="multipart/form-data">
                                    @csrf

                                    <div class="mb-3 text-center">
                                        @if ($companyLogo)
                                            <img src="{{ asset($companyLogo) }}" alt="Company Logo"
                                                class="rounded-circle mb-3 company-preview-img"
                                                style="width: 120px; height: 120px; object-fit: cover;">
                                        @else
                                            <img src="{{ asset('frontend-assets/assets/companyprofile/default.png') }}"
                                                alt="Default Company Logo" class="rounded-circle mb-3 company-preview-img"
                                                style="width: 120px; height: 120px; object-fit: cover;">
                                        @endif
                                        <div class="text-start">
                                            <label for="company_profile" class="form-label">{{ __('messages.company_profile') }}</label>
                                            <input type="file" name="company_profile" class="form-control"
                                                accept="image/*">
                                        </div>
                                    </div>

                                    @if(!empty($companyObj?->company_code))
                                        <div class="mb-3">
                                            <label class="form-label font-12 fw-semibold text-muted">Company Code</label>
                                            <input type="text" class="form-control bg-light text-dark fw-bold" value="{{ $companyObj->company_code }}" readonly disabled>
                                        </div>
                                    @endif

                                    <div class="mb-3">
                                        <label for="company_name" class="form-label">{{ __('messages.company_name') }}</label>
                                        <input type="text" name="company_name" class="form-control"
                                            placeholder="Enter company name"
                                            value="{{ old('company_name', $companyObj->name ?? '') }}">
                                    </div>

                                    <div class="mb-3">
                                        <label for="company_email" class="form-label">{{ __('messages.company_email') }}</label>
                                        <input type="email" name="company_email" class="form-control"
                                            placeholder="Enter company email"
                                            value="{{ old('company_email', $companyObj->email ?? '') }}">
                                    </div>

                                    <div class="mb-3">
                                        <label for="company_phone" class="form-label">{{ __('messages.company_phone') }}</label>
                                        <input type="text" name="company_phone" class="form-control"
                                            value="{{ old('company_phone', $companyObj->phone ?? '') }}"
                                            placeholder="Enter phone number">
                                    </div>

                                    <div class="mb-3">
                                        <label for="company_website" class="form-label">{{ __('messages.company_website') }}</label>
                                        <input type="url" name="company_website" id="company_website"
                                            class="form-control" placeholder="https://example.com"
                                            value="{{ old('company_website', $companyObj->website ?? '') }}">
                                    </div>
                                    <div class="text-end">
                                        <button type="submit" class="btn btn-success">{{ __('messages.save') }}</button>
                                    </div>
                                </form>
                            </div>
                        @endif
                    </div>

                </div> <!-- card-body -->
            </div> <!-- card -->
        </div>
    </div>

    {{-- JS for Image Preview + Alert Dismiss --}}
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            function previewImage(inputSelector, imgSelector) {
                const input = document.querySelector(inputSelector);
                const imgPreview = document.querySelector(imgSelector);

                if (input && imgPreview) {
                    input.addEventListener('change', function() {
                        const file = this.files[0];
                        if (file) {
                            const reader = new FileReader();
                            reader.onload = function(e) {
                                imgPreview.setAttribute('src', e.target.result);
                            }
                            reader.readAsDataURL(file);
                        }
                    });
                }
            }

            // Preview for profile_image
            previewImage('input[name="profile_image"]', '.profile-preview-img');

            // Preview for company_profile
            previewImage('input[name="company_profile"]', '.company-preview-img');

            // Hide alerts after 3 seconds
            setTimeout(function() {
                const successAlert = document.getElementById('alert-success');
                if (successAlert) successAlert.style.display = 'none';

                const dangerAlert = document.getElementById('alert-danger');
                if (dangerAlert) dangerAlert.style.display = 'none';
            }, 3000);
        });
    </script>

@endsection
