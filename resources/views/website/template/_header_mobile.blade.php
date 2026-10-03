
<!-- Mobile Header (Visible on Mobile Screens) -->
<header class="header-container d-md-none">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center">
            <!-- Navbar Toggle Button (Left Side) -->
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse"
                data-bs-target="#mobileNavbar" aria-controls="mobileNavbar" aria-expanded="false"
                aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <!-- Logo (Center) -->
            <div class="flex-grow-1 text-center">
                <a href="{{ url('/') }}">
                    <h5 class="text-primary mb-0" style="font-weight: 700;">
                        <i class="mdi mdi-account-group"></i> Payroll System
                    </h5>
                    <small class="text-muted" style="font-size: 0.65rem;">HR & Payroll Management</small>
                </a>
            </div>

            @if (Auth::check() && Auth::user()->role == 'user')
                <div class="col-md-2 text-end">
                    <ul class="list-unstyled topnav-menu float-end mb-0">
                        <li class="dropdown notification-list topbar-dropdown">
                            <a title="{{ Auth::user()->name }}"
                                class="nav-link dropdown-toggle nav-user me-0 waves-effect waves-light text-white"
                                data-bs-toggle="dropdown" href="#" role="button" aria-haspopup="false"
                                aria-expanded="false">
                                <i class="mdi mdi-account-circle-outline theme-color text-white"
                                    style="font-size: 25px;"></i>
                            </a>
                            <div class="dropdown-menu dropdown-menu-end profile-dropdown ">
                                <!-- item-->
                                <a href="{{ route('website.dashboard') }}" class="dropdown-item notify-item">
                                    <i class="mdi mdi-view-dashboard-outline theme-color"></i>
                                    <span> {{ __('messages.dashboard') }} </span>
                                </a>

                                <!-- item-->
                                <a href="{{ route('website.edit_profile', Auth::id()) }}" class="dropdown-item notify-item">
                                    <span class="mdi mdi-account theme-color"></span>
                                    <span> {{ __('messages.edit') }} {{ __('messages.profile') }}</span>
                                </a>

                                <!-- item-->
                                <a href="{{ route('website.booking') }}" class="dropdown-item notify-item">
                                    <span class="mdi mdi-book-multiple theme-color"></span>
                                    <span>{{ __('messages.bookings') }} </span>
                                </a>

                                <div class="dropdown-divider"></div>

                                <!-- item-->
                                <a class="dropdown-item" href="{{ url('logout') }}"

                                    onclick="event.preventDefault(); document.getElementById('logout-form-web-mobile').submit();">

                                    <span class="mdi mdi-logout theme-color"></span>
                                    {{ __('messages.logout') }}
                                </a>
                                <form id="logout-form-web-mobile" action="{{ url('logout') }}" method="POST" class="d-none">
                                    @csrf
                                </form>

                            </div>
                        </li>
                    </ul>
                </div>
            @else
                <!-- Get Started Button (Smaller) -->
                <div class="col-md-2 text-end" id="getStartedButtonMobile">
                    <a href="#" class="btn rounded-pill px-2 py-1 text-white btn-orange-clr"
                        data-bs-toggle="modal" data-bs-target="#loginModal"
                        style="font-size: 10px;">{{ __('messages.rent_sign_in') }} </a>
                </div>
            @endif


        </div>

        <!-- Mobile Collapsible Menu -->
        <div class="collapse mt-2" id="mobileNavbar">
            <ul class="navbar-nav text-center">
                <li class="nav-item"><a class="nav-link" href="{{ url('/') }}">{{ __('messages.home') }}</a>
                </li>
                <li class="nav-item"><a class="nav-link"
                        href="{{ url('/categories') }}">{{ __('messages.categories') }}</a></li>
                <li class="nav-item"><a class="nav-link"
                        href="{{ url('/join-our-program') }}">{{ __('messages.our') }}
                        {{ __('messages.program') }}</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ url('/blog') }}">{{ __('messages.blogs') }}</a>
                </li>
                <li class="nav-item"><a class="nav-link"
                        href="{{ url('/about-us') }}">{{ __('messages.about') }}</a></li>
                <li class="nav-item"><a class="nav-link"
                        href="{{ url('/contact') }}">{{ __('messages.contact') }}</a></li>
                <li class="nav-item">
                    <ul class="list-unstyled topnav-menu mb-0">
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle text-white" href="#" id="navbarDropdown"
                                role="button" data-bs-toggle="dropdown">
                                @php
                                    $isLoggedIn = auth()->check();
                                    $sessionLangName = session('langName');
                                    $sessionLangFlag = session('langFlagCode');

                                    $langToShow = null;
                                    $flagToShow = null;

                                    // If user is not logged in and session lang is set
                                    if (!$isLoggedIn && !empty($sessionLangName)) {
                                        $langToShow = (object) [
                                            'name' => $sessionLangName,
                                            'flag_code' => $sessionLangFlag,
                                        ];
                                        $flagToShow = $sessionLangFlag;
                                    }

                                    // If user is logged in and userDefaultLang is set
                                    elseif ($isLoggedIn && !empty($userDefaultLang)) {
                                        $langToShow = $userDefaultLang;
                                        $flagToShow = $userDefaultLang->flag_code ?? '';
                                    }

                                    // Fallback to defaultLang if available
                                    elseif (!empty($defaultLang)) {
                                        $langToShow = $defaultLang;
                                        $flagToShow = $defaultLang->flag_code ?? '';
                                    }
                                @endphp

                                @if ($langToShow && !empty($langToShow->flag_code))
                                    <span class="flag-icon flag-icon-{{ $langToShow->flag_code }}"></span>
                                    {{ $langToShow->name }}
                                @endif

                            </a>
                            <ul class="dropdown-menu">
                                @if (!empty($languages))
                                    @foreach ($languages as $lang)
                                        <li>
                                            <a class="dropdown-item" href="{{ route('change.language', $lang) }}">
                                                <span class="flag-icon flag-icon-{{ $lang['flag_code'] }}"></span>
                                                <span>{{ $lang['name'] }}</span>
                                            </a>
                                        </li>
                                    @endforeach
                                @endif
                            </ul>
                        </li>
                    </ul>
                </li>
                <li class="nav-item">
                    <ul class="list-unstyled topnav-menu mb-0">
                        <li class="nav-item dropdown">
                            @if (isset($defaultCurrency->name))
                                <a class="nav-link dropdown-toggle text-white" href="#" id="currencyDropdown"
                                    role="button" data-bs-toggle="dropdown">
                                    {{ $defaultCurrency->name }}
                                </a>
                            @else
                                <a class="nav-link dropdown-toggle text-white" href="#" id="currencyDropdown"
                                    role="button" data-bs-toggle="dropdown">
                                    {{ __('messages.currency') }}
                                </a>
                            @endif
                            @if (isset($activeCurrencies) && isset($defaultCurrency))
                                <ul class="dropdown-menu">
                                    @foreach ($activeCurrencies as $currency)
                                        @if ($currency->name != $defaultCurrency->name)
                                            <li>
                                                <a class="dropdown-item"
                                                    href=" {{ route('setDefaultCurreny', $currency->id) }}">
                                                    <span>{{ $currency->name }}</span>
                                                </a>
                                            </li>
                                        @endif
                                    @endforeach
                                </ul>
                            @else
                                <ul class="dropdown-menu">
                                    @foreach ($activeCurrencies as $currency)
                                        <li>
                                            <a class="dropdown-item"
                                                href=" {{ route('setDefaultCurreny', $currency->id) }}">
                                                <span>{{ $currency->name }}</span>
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</header>
