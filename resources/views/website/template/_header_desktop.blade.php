<link href="{{ asset('/') }}assets/css/icons.min.css" rel="stylesheet" type="text/css" />
<script src="{{ asset('/frontend-assets/assets/Js/carSearch.js') }}"></script>
{{-- location suggessions link google api --}}
<script src="{{ asset('/') }}assets/js/locationSuggestion.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/flag-icon-css/3.4.6/css/flag-icon.min.css">
@if (request()->is('/'))
    <div class="hero-header">
    @elseif(request()->is('cardetail/*'))
        <style>
            .header-container {
                background-color: #07407B !important;
            }
        </style>
    @elseif(request()->is('carsearch'))
        <style>
            .header-container {
                background-color: #07407B !important;
            }

            .join-program {
                /* padding-top: 12rem; */
                padding-top: 0px;
            }
        </style>

    @else
        <div class="hero-header-2">
@endif
<!-- Desktop Header (Visible on Large Screens) -->
<header class="header-container d-none d-md-block">
    <div class="container">
        <div class="d-flex align-items-center">
            <!-- Logo -->
            <div class="col-6 col-md-2">
                <a href="{{ url('/') }}">
                    <h4 class="text-primary mb-0" style="font-weight: 700;">
                        <i class="mdi mdi-account-group"></i> Payroll
                    </h4>
                    <small class="text-muted" style="font-size: 0.7rem;">HR & Payroll</small>
                </a>
            </div>
            <!-- Navbar -->
            @if (Cart::instance('cart')->content()->count() > 0)
                <div class="col-5">
                @else
                    <div class="col-6">
            @endif
            <nav class="navbar navbar-expand-md" style="white-space: nowrap;">
                <div class="collapse navbar-collapse justify-content-center" id="navbarNav">
                    <ul class="navbar-nav">
                        <li class="nav-item"><a class="nav-link"
                                href="{{ url('/') }}">{{ __('messages.home') }}</a></li>
                        <li class="nav-item"><a class="nav-link"
                                href="{{ url('/categories') }}">{{ __('messages.categories') }}</a></li>
                        <li class="nav-item"><a class="nav-link"
                                href="{{ url('/join-our-program') }}">{{ __('messages.program') }}</a></li>
                        <li class="nav-item"><a class="nav-link"
                                href="{{ url('/blog') }}">{{ __('messages.blogs') }}</a></li>
                        <li class="nav-item"><a class="nav-link"
                                href="{{ url('/about-us') }}">{{ __('messages.about') }}</a></li>
                        <li class="nav-item"><a class="nav-link"
                                href="{{ url('/contact') }}">{{ __('messages.contact') }}</a></li>
                    </ul>
                </div>
            </nav>
        </div>
        <style>
            .lang-tab .dropdown:hover > .dropdown-menu {
                display: block;
                margin-top: 0;
            }

            .lang-tab .dropdown-menu {
                margin-top: 0;
            }
        </style>
        <!-- Multi Lingual -->
        <div class="col-md-1 text-end">
            <ul class="list-unstyled topnav-menu float-end mb-0 lang-tab">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle text-white lang-tab-font" href="#" id="navbarDropdown" role="button">
                        @php
                            $isLoggedIn = auth()->check();
                            $sessionLangName = session('langName');
                            $sessionLangFlag = session('langFlagCode');

                            $langToShow = null;
                            $flagToShow = null;

                            // If user is not logged in and session lang is set
                            if (!$isLoggedIn && !empty($sessionLangName)) {
                                $langToShow = (object) ['name' => $sessionLangName, 'flag_code' => $sessionLangFlag];
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
        </div>
        <?php
        $data = getActiveDefaultCurrency();

        $defaultCurrency = $data['defaultCurrency'];
        $activeCurrencies = $data['activeCurrencies'];
        ?>
        <div class="col-md-1 text-end">
            <ul class="list-unstyled topnav-menu float-end mb-0 lang-tab">
                <li class="nav-item dropdown">
                    @if (session('defaultCurrencyCode'))
                        <a class="nav-link dropdown-toggle text-white lang-tab-font" href="#" id="currencyDropdown"
                            role="button" data-bs-toggle="dropdown">
                            {{ session('defaultCurrencyCode') }}
                        </a>
                    @elseif(isset($defaultCurrency))
                        <a class="nav-link dropdown-toggle text-white" href="#" id="currencyDropdown"
                            role="button" data-bs-toggle="dropdown">
                            {{ $defaultCurrency->code }}
                        </a>
                    @else
                        <a class="nav-link dropdown-toggle text-white" href="#" id="currencyDropdown"
                            role="button" data-bs-toggle="dropdown">
                            <?php
                            setDefaultCurreny();
                            header('Location: ' . $_SERVER['REQUEST_URI']);
                            exit();
                            ?>
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
                        {{-- @else
                            <ul class="dropdown-menu">
                                @foreach ($activeCurrencies as $currency)
                                    <li>
                                        <a class="dropdown-item" href=" {{ route('setDefaultCurreny', $currency->id) }}">
                                            <span>{{ $currency->name }}</span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul> --}}
                    @endif
                </li>
            </ul>
        </div>
        @if (Cart::instance('cart')->content()->count() > 0)
            <div class="col-md-1">
                <div class="col-md-1 text-end position-relative">
                    <a href="{{ route('car.booking') }}" class="px-3 text-white position-relative d-inline-block">
                        <i class="fa-solid fa-car fs-4"></i>
                        <span class="car-count-badge">{{ Cart::instance('cart')->content()->count() }}</span>
                    </a>
                </div>
            </div>
        @endif
        <!-- Get Started Button -->

        @if (Auth::check() && Auth::user()->role == 'user')

            <div class="col-md-2 text-end">
                <ul class="list-unstyled topnav-menu float-end mb-0">
                    <li class="dropdown notification-list topbar-dropdown">
                        @php
                            $profileImage = Auth::user()->profile_image;
                        @endphp

                        <a class="nav-link dropdown-toggle nav-user me-0 waves-effect waves-light text-white"
                            data-bs-toggle="dropdown" href="#" role="button" aria-haspopup="false"
                            aria-expanded="false" title="">
                            @if ($profileImage && file_exists(public_path($profileImage)))
                                <img src="{{ asset($profileImage) }}" alt="Profile" width="30" height="30"
                                    class="rounded-circle object-fit-cover" style="object-fit: cover;">
                            @else
                                <i class="mdi mdi-account-circle-outline theme-color text-white"
                                    style="font-size: 25px;"></i>
                            @endif
                        </a>

                        <div class="dropdown-menu dropdown-menu-end profile-dropdown ">
                            <a class="dropdown-item notify-item">{{ __('messages.welcome') }} {{ Auth::user()->name }}</a>
                            <!-- item-->
                            <a href="{{ route('website.dashboard') }}" class="dropdown-item notify-item">
                                <i class="mdi mdi-view-dashboard-outline theme-color"></i>
                                <span> {{ __('messages.dashboard') }} </span>
                            </a>

                            <!-- item-->
                            <a href="{{ route('website.edit_profile', Auth::id()) }}"
                                class="dropdown-item notify-item">
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

                                onclick="event.preventDefault(); document.getElementById('logout-form-web-desktop').submit();">
                                <span class="mdi mdi-logout theme-color"></span>
                                {{ __('messages.logout') }}
                            </a>
                            <form id="logout-form-web-desktop" action="{{ url('logout') }}" method="POST" class="d-none">

                                @csrf
                            </form>

                        </div>
                    </li>
                </ul>
            </div>
        @else
            <div class="col-md-2 text-end" id="getStartedButton">
                <a href="#" class="btn ms-3 rounded-pill px-3 text-white btn-orange-clr button-font-tab" data-bs-toggle="modal"
                    data-bs-target="#loginModal">
                    {{ __('messages.rent_sign_in') }} <i class="fa-solid fa-arrow-right"></i>
                </a>
            </div>
        @endif
    </div>
    </div>
</header>
