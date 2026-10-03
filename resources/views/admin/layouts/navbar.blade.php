<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/flag-icon-css/3.4.6/css/flag-icon.min.css">
<meta name="csrf-token" content="{{ csrf_token() }}">
<div class="navbar-custom">
    
    <div class="container-fluid">
        <ul class="list-unstyled topnav-menu float-end mb-0">
            
            <li class="d-none d-lg-block">
                
                {{-- <form class="app-search">
                    <div class="app-search-box dropdown">
                        <div class="input-group">
                            <input type="search" class="form-control" placeholder="Search..." id="top-search">
                            <button class="btn input-group-text" type="submit">
                                <i class="fe-search"></i>
                            </button>
                        </div>
                        <div class="dropdown-menu dropdown-lg" id="search-dropdown">
                            <!-- item-->
                            <div class="dropdown-header noti-title">
                                <h5 class="text-overflow mb-2">Found 22 results</h5>
                            </div>

                            <!-- item-->
                            <a href="javascript:void(0);" class="dropdown-item notify-item">
                                <i class="fe-home me-1"></i>
                                <span>Analytics Report</span>
                            </a>

                            <!-- item-->
                            <a href="javascript:void(0);" class="dropdown-item notify-item">
                                <i class="fe-aperture me-1"></i>
                                <span>How can I help you?</span>
                            </a>
                
                            <!-- item-->
                            <a href="javascript:void(0);" class="dropdown-item notify-item">
                                <i class="fe-settings me-1"></i>
                                <span>User profile settings</span>
                            </a>

                            <!-- item-->
                            <div class="dropdown-header noti-title">
                                <h6 class="text-overflow mb-2 text-uppercase">Users</h6>
                            </div>

                            <div class="notification-list">
                                <!-- item-->
                                <a href="javascript:void(0);" class="dropdown-item notify-item">
                                    <div class="d-flex align-items-start">
                                        <img class="d-flex me-2 rounded-circle" src="{{asset('/')}}assets/images/users/user-2.jpg" alt="Generic placeholder image" height="32">
                                        <div class="w-100">
                                            <h5 class="m-0 font-14">Erwin E. Brown</h5>
                                            <span class="font-12 mb-0">UI Designer</span>
                                        </div>
                                    </div>
                                </a>

                                <!-- item-->
                                <a href="javascript:void(0);" class="dropdown-item notify-item">
                                    <div class="d-flex align-items-start">
                                        <img class="d-flex me-2 rounded-circle" src="{{asset('/')}}assets/images/users/user-5.jpg" alt="Generic placeholder image" height="32">
                                        <div class="w-100">
                                            <h5 class="m-0 font-14">Jacob Deo</h5>
                                            <span class="font-12 mb-0">Developer</span>
                                        </div>
                                    </div>
                                </a>
                            </div>

                        </div>  
                    </div>
                </form> --}}
            </li>

            <li class="dropdown d-inline-block d-lg-none">
                <a class="nav-link dropdown-toggle arrow-none waves-effect waves-light" data-bs-toggle="dropdown" href="#" role="button" aria-haspopup="false" aria-expanded="false">
                    <i class="fe-search noti-icon"></i>
                </a>
                <div class="dropdown-menu dropdown-lg dropdown-menu-end p-0">
                    <form class="p-3">
                        <input type="text" class="form-control" placeholder="Search ..." aria-label="Recipient's username">
                    </form>
                </div>
            </li>
             
            {{-- Language Dropdown - Disabled
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                    @if(!auth()->check())
                        <span class="flag-icon flag-icon-{{ session('langFlagCode') }}"></span> {{ session('langName') }}
                    @elseif(!empty($userDefaultLang))
                        <span class="flag-icon flag-icon-{{ $userDefaultLang->flag_code }}"></span> {{ $userDefaultLang->name }}
                    @elseif(empty(session('lang')))
                        <span class="flag-icon flag-icon-{{ $defaultLang->flag_code }}"></span> {{ $defaultLang->name }}
                    @endif
                </a>
                <ul class="dropdown-menu">
                    @if(!empty($languages))
                        @foreach ($languages as $lang)
                            <li>
                                <a class="dropdown-item" href="{{ route('change.language', $lang) }}">
                                    <span class="flag-icon flag-icon-{{ $lang['flag_code'] }}"></span> <span>{{ $lang['name'] }}</span>
                                </a>
                            </li>
                        @endforeach
                    @endif
                </ul>
            </li>
            --}}
              <!-- notification start -->
           {{-- <li class="notification_list"></li>  --}}
             <!-- end notifications -->
            <li class="dropdown notification-list topbar-dropdown">
                @php
                    $profileImage = Auth::user()?->profile_image;
                @endphp

                <a class="nav-link dropdown-toggle nav-user me-0 waves-effect waves-light text-white"
                data-bs-toggle="dropdown" href="#" role="button" aria-haspopup="false" aria-expanded="false" title="">
                    @if ($profileImage && file_exists(public_path($profileImage)))
                        <img src="{{ asset($profileImage) }}" alt="Profile" width="30" height="30"
                            class="rounded-circle object-fit-cover" style="object-fit: cover;">
                    @else
                        <i class="mdi mdi-account-circle-outline theme-color text-white" style="font-size: 25px;"></i>
                    @endif
                </a>
                <div class="dropdown-menu dropdown-menu-end profile-dropdown ">
                    <!-- item-->
                    <a class="dropdown-item notify-item">Welcome! {{ Auth::user()?->name }}</a>

                    <!-- item-->
                    @if(!in_array(Auth::user()?->role, ['employee', 'hr']))
                    <a href="{{ panelRoute('edit_profile', Auth::id() ?? 1) }}" class="dropdown-item notify-item">
                        <i class="fe-user theme-color"></i>
                        <span>{{ __('messages.my') }} {{ __('messages.account') }} </span>
                    </a>
                    @endif

                    <!-- item-->
                    <a href="{{ panelRoute('settings') }}" class="dropdown-item notify-item">
                        <i class="fe-settings theme-color"></i>
                        <span>{{ __('messages.settings') }} </span>
                    </a>

                    <!-- item-->
                    <button class="dropdown-item notify-item" id="lock-screen">
                        <i class="fe-lock theme-color"></i>
                        {{ __('messages.lock_screen') }} 
                    </button>

                    <div class="dropdown-divider"></div>

                    <!-- item-->
                    <a class="dropdown-item" href="{{ url('logout') }}" onclick="event.preventDefault(); document.getElementById('logout-form-admin-navbar').submit();">
                        <span class="mdi mdi-logout theme-color"></span>
                        {{ __('messages.logout') }}
                    </a>
                    <form id="logout-form-admin-navbar" action="{{ url('logout') }}" method="POST" class="d-none">
                        @csrf
                    </form>

                </div>
            </li>
            {{-- <li class="dropdown notification-list">
                <a href="javascript:void(0);" class="nav-link right-bar-toggle waves-effect waves-light">
                    <i class="fe-settings noti-icon theme-color"></i>
                </a>
            </li> --}}

        </ul>

        @php
            $user = Auth::user();
            $userName = $user ? ($user->name ?? $user->username ?? 'User') : 'User';
            $userRole = $user ? getRoleDisplayName($user->role ?? '') : '';

            $words = array_values(array_filter(preg_split('/\s+/', trim($userName))));
            if (count($words) >= 2) {
                $initials = strtoupper(mb_substr($words[0], 0, 1) . mb_substr($words[count($words) - 1], 0, 1));
            } elseif (count($words) === 1 && mb_strlen($words[0]) >= 2) {
                $initials = strtoupper(mb_substr($words[0], 0, 2));
            } elseif (count($words) === 1) {
                $initials = strtoupper(mb_substr($words[0], 0, 1));
            } else {
                $initials = 'U';
            }
        @endphp

        <!-- DYNAMIC USER PROFILE IDENTIFIER BRAND AREA (TOP LEFT) -->
        <div class="logo-box sidebar-toggle-btn" title="Click to toggle sidebar menu">
            <div class="logo-user-profile text-decoration-none">
                <!-- Avatar Circle with 2 Initials -->
                <div class="user-avatar-circle flex-shrink-0 d-flex align-items-center justify-content-center">
                    <span class="user-initials">{{ $initials }}</span>
                </div>

                <!-- User Full Name & Subtitle (Expanded State) -->
                <div class="user-info-text ms-2">
                    <span class="user-full-name">{{ $userName }}</span>
                    @if($userRole)
                        <span class="user-role-subtitle">{{ $userRole }}</span>
                    @endif
                </div>
            </div>

            <!-- Hamburger Toggle Button -->
            <div class="btn-sidebar-toggle flex-shrink-0 ms-1" title="Toggle Sidebar">
                <i class="fe-menu font-20"></i>
            </div>
        </div>

        <ul class="list-unstyled topnav-menu topnav-menu-left m-0 d-flex align-items-center">
            <li>
                <!-- Mobile menu toggle (Horizontal Layout)-->
                <a class="navbar-toggle nav-link" data-bs-toggle="collapse" data-bs-target="#topnav-menu-content">
                    <div class="lines">
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>
                </a>
                <!-- End mobile menu toggle-->
            </li>   
        </ul>
        <div class="clearfix"></div>
    </div>
</div>
<script>
    $(document).ready(function(){
        // Ensure localStorage state is reflected on page load
        var savedSize = localStorage.getItem('leftbar_size');
        if (savedSize) {
            $('body').attr('data-leftbar-size', savedSize);
            if (window.themeCustomizer) {
                window.themeCustomizer.config.leftbar.size = savedSize;
            }
        }

        $(document).on('click', '.sidebar-toggle-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();

            var $body = $('body');
            $body.toggleClass('sidebar-enable');

            var currentSize = $body.attr('data-leftbar-size');
            var newSize = (currentSize === 'condensed') ? 'default' : 'condensed';

            $body.attr('data-leftbar-size', newSize);
            localStorage.setItem('leftbar_size', newSize);

            if (window.themeCustomizer) {
                window.themeCustomizer.changeLeftbarSize(newSize);
            }
        });
    });
</script>
<script>
    $(document).ready(function(){
        $('#lock-screen').on('click', function(e) {
        e.preventDefault();
        fetch('/lock-screen', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Content-Type': 'application/json'
        },
            })
            .then(response => response.json())
            .then(data => console.log(data))
            .catch(error => console.error('Error:', error));

        });
    });
</script>
<script>
    var translations = {
        search: "{{ __('messages.search') }}"
    };
</script>