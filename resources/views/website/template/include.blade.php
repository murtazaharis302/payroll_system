<ul class="list-unstyled topnav-menu float-end mb-0">
    <li class="dropdown notification-list topbar-dropdown">
        <a title="{{ Auth::user()->name }}" class="nav-link dropdown-toggle nav-user me-0 waves-effect waves-light text-white" data-bs-toggle="dropdown" href="#" role="button" aria-haspopup="false" aria-expanded="false">
            <i class="mdi mdi-account-circle-outline theme-color text-white"  style="font-size: 25px;"></i>
        </a>
        <div class="dropdown-menu dropdown-menu-end profile-dropdown ">
            <!-- item-->
            <a href="javascript:void(0);" class="dropdown-item notify-item">
                <i class="mdi mdi-view-dashboard-outline theme-color"></i>
                <span> {{ __('messages.dashboard') }} </span>
            </a>

            <!-- item-->
            <a href="javascript:void(0);" class="dropdown-item notify-item">
                <span class="mdi mdi-account theme-color"></span>
                <span> {{ __('messages.edit') }} {{ __('messages.profile') }}</span>
            </a>

            <!-- item-->
            <a href="javascript:void(0);" class="dropdown-item notify-item">
                <span class="mdi mdi-book-multiple theme-color"></span>
                <span>{{ __('messages.bookings') }} </span>
            </a>
            <div class="dropdown-divider"></div>

            <!-- item-->
            <a class="dropdown-item" href="{{ url('logout') }}" onclick="event.preventDefault(); document.getElementById('logout-form-web-ajax').submit();">
                <span class="mdi mdi-logout theme-color"></span>
                {{ __('messages.logout') }}
            </a>
            <form id="logout-form-web-ajax" action="{{ url('logout') }}" method="POST" class="d-none">
                @csrf
            </form>

        </div>
    </li>
</ul>