@extends('loginLayout')

@section('title', 'App Owner Login')

@section('content')
<body class="bg-light" style="background-color: #f8f9fa !important;">

    <div class="account-pages py-5">
        <div class="container" style="max-width: 460px;">
            
            <div class="mb-3 d-flex align-items-center justify-content-end">
                <!-- Language Dropdown -->
                <ul class="list-unstyled mb-0">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle font-13 text-muted" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            @php
                                $isLoggedIn = auth()->check();
                                $sessionLangName = session('langName');
                                $sessionLangFlag = session('langFlagCode');
                                $langToShow = null;

                                if (!$isLoggedIn && !empty($sessionLangName)) {
                                    $langToShow = (object)['name' => $sessionLangName, 'flag_code' => $sessionLangFlag];
                                } elseif ($isLoggedIn && !empty($userDefaultLang)) {
                                    $langToShow = $userDefaultLang;
                                } elseif (!empty($defaultLang)) {
                                    $langToShow = $defaultLang;
                                }
                            @endphp

                            @if($langToShow && !empty($langToShow->flag_code))
                                <span class="flag-icon flag-icon-{{ $langToShow->flag_code }}"></span> {{ $langToShow->name }}
                            @endif
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            @if(!empty($languages))
                                @foreach ($languages as $lang)
                                    <li>
                                        <a class="dropdown-item font-13" href="{{ route('change.language', $lang) }}">
                                            <span class="flag-icon flag-icon-{{ $lang['flag_code'] }}"></span> {{ $lang['name'] }}
                                        </a>
                                    </li>
                                @endforeach
                            @endif
                        </ul>
                    </li>
                </ul>
            </div>

            <!-- Skeleton Loader Card -->
            <div id="skeletonLoaderCard" class="card bg-white border-0 shadow-sm rounded-3 p-4">
                <div class="skeleton-avatar mx-auto mb-3"></div>
                <div class="skeleton-text mx-auto w-50 mb-4"></div>
                <div class="skeleton-label w-25 mb-1"></div>
                <div class="skeleton-input w-100 mb-3"></div>
                <div class="skeleton-label w-25 mb-1"></div>
                <div class="skeleton-input w-100 mb-3"></div>
                <div class="skeleton-button w-100 mt-2"></div>
            </div>

            <!-- Real Login Card Box -->
            <div id="realLoginCard" class="card bg-white border-0 shadow-sm rounded-3 d-none">
                <div class="card-body p-4">

                    <!-- Header Branding -->
                    <div class="text-center mb-4">
                        <div class="d-inline-flex align-items-center justify-content-center avatar-md rounded-circle mb-2 text-white shadow-sm" style="background-color: #f06115; width:48px; height:48px;">
                            <i class="fe-shield font-22"></i>
                        </div>
                        <h4 class="fw-bold text-dark mb-1 font-18">App Owner</h4>
                        <p class="text-muted font-12 mb-0">App Owner Portal Access</p>
                    </div>

                    @if(session('statusDanger'))
                        <div class="alert alert-danger font-13 shadow-sm rounded-3 mb-3" role="alert">
                            {!! session('statusDanger') !!}
                        </div>
                    @endif
                    @if(session('status'))
                        <div class="alert alert-success font-13 shadow-sm rounded-3 mb-3" role="alert">
                            {!! session('status') !!}
                        </div>
                    @endif

                    <!-- Form -->
                    <form id="adminLoginForm" method="POST" action="{{ route('login') }}">
                        @csrf

                        <div class="mb-3">
                            <label for="email" class="form-label font-13 fw-semibold text-dark">{{ __('messages.email') }} {{ __('messages.address') }}</label>
                            <input id="email" type="email" class="form-control form-control-md @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" required autocomplete="email" autofocus placeholder="admin@example.com">

                            @error('email')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label font-13 fw-semibold text-dark">{{ __('messages.password') }}</label>
                            <div class="input-group">
                                <input id="password" type="password" class="form-control form-control-md @error('password') is-invalid @enderror" name="password" required placeholder="••••••••">
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                    <i class="bi bi-eye-slash" id="togglePasswordIcon"></i>
                                </button>
                            </div>

                            @error('password')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>
                                <label class="form-check-label font-13 text-muted" for="remember">
                                    {{ __('messages.remember_me') }}
                                </label>
                            </div>
                        </div>

                        <div class="d-grid mt-4">
                            <button type="submit" id="loginSubmitBtn" class="btn text-white fw-semibold py-2" style="background-color: #f06115; border-color: #f06115;">
                                <span id="loginBtnText">{{ __('messages.login') }}</span>
                                <span id="loginSpinner" class="spinner-border spinner-border-sm me-1 d-none" role="status" aria-hidden="true"></span>
                            </button>
                        </div>
                    </form>

                </div>
            </div>

            <div class="text-center text-muted font-12 mt-3">
                &copy; {{ date('Y') }} Payroll System. All rights reserved.
            </div>

        </div>
    </div>

    <style>
        .skeleton-avatar { width:48px; height:48px; border-radius:50%; background: linear-gradient(90deg, #e2e8f0 25%, #f1f5f9 50%, #e2e8f0 75%); background-size:200% 100%; animation:shimmer 1.2s infinite; }
        .skeleton-text { height:16px; border-radius:4px; background: linear-gradient(90deg, #e2e8f0 25%, #f1f5f9 50%, #e2e8f0 75%); background-size:200% 100%; animation:shimmer 1.2s infinite; }
        .skeleton-label { height:12px; border-radius:4px; background: linear-gradient(90deg, #e2e8f0 25%, #f1f5f9 50%, #e2e8f0 75%); background-size:200% 100%; animation:shimmer 1.2s infinite; }
        .skeleton-input { height:38px; border-radius:6px; background: linear-gradient(90deg, #e2e8f0 25%, #f1f5f9 50%, #e2e8f0 75%); background-size:200% 100%; animation:shimmer 1.2s infinite; }
        .skeleton-button { height:40px; border-radius:6px; background: linear-gradient(90deg, #e2e8f0 25%, #f1f5f9 50%, #e2e8f0 75%); background-size:200% 100%; animation:shimmer 1.2s infinite; }
        @keyframes shimmer { 0% { background-position:-200% 0; } 100% { background-position:200% 0; } }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Skeleton display transition
            setTimeout(function() {
                var skel = document.getElementById('skeletonLoaderCard');
                var real = document.getElementById('realLoginCard');
                if (skel) skel.classList.add('d-none');
                if (real) real.classList.remove('d-none');
            }, 250);

            // Password visibility toggle
            const passwordInput = document.getElementById('password');
            const toggleButton = document.getElementById('togglePassword');
            const toggleIcon = document.getElementById('togglePasswordIcon');

            if (toggleButton && passwordInput && toggleIcon) {
                toggleButton.addEventListener('click', function () {
                    const isPassword = passwordInput.type === 'password';
                    passwordInput.type = isPassword ? 'text' : 'password';
                    toggleIcon.classList.toggle('bi-eye');
                    toggleIcon.classList.toggle('bi-eye-slash');
                });
            }

            // Form submit loading state
            const loginForm = document.getElementById('adminLoginForm');
            const loginBtn = document.getElementById('loginSubmitBtn');
            const btnText = document.getElementById('loginBtnText');
            const spinner = document.getElementById('loginSpinner');

            if (loginForm && loginBtn) {
                loginForm.addEventListener('submit', function() {
                    if (spinner) spinner.classList.remove('d-none');
                    if (btnText) btnText.textContent = 'Authenticating...';
                    loginBtn.disabled = true;
                });
            }
        });
    </script>
</body>
@endsection
