</style>

<?php
if (Auth::check()) {
    $userRole = Auth::user()->role;
    $userId = Auth::id();
} else {
    $userRole = '';
    $userId = 0;
}
?>

<div class="left-side-menu">
    <div class="h-100" data-simplebar>
        <!--- Sidemenu -->
        <div id="sidebar-menu">

            <ul id="side-menu">

                <li class="menu-title">{{ __('messages.navigation') }}</li>

                {{-- Dashboard --}}
                @canMenu('dashboard')
                    <li class="{{ request()->routeIs('*dashboard*') ? 'menuitem-active' : '' }}">
                        <a href="{{ panelRoute('dashboard') }}" class="{{ request()->routeIs('*dashboard*') ? 'active' : '' }}">
                            <i class="fe-grid"></i>
                            <span>{{ __('messages.dashboards') }}</span>
                        </a>
                    </li>
                @endcanMenu

                {{-- Core Applications --}}
                @if(
                    \App\Models\MenuRoleAccess::hasModuleAccess(auth()->user(), 'employees') ||
                    \App\Models\MenuRoleAccess::hasModuleAccess(auth()->user(), 'gov_contributions')
                )
                    <li class="menu-title mt-2">Core Applications</li>

                    {{-- Users Management Directory --}}
                    @canMenu('employees')
                        @php
                            $isUsersActive = request()->routeIs('*employee*', '*createEmployee*', '*editEmployee*', '*users*', '*createUser*', '*editUser*');
                        @endphp
                        <li class="{{ $isUsersActive ? 'menuitem-active' : '' }}">
                            <a href="#sub_menu_users" data-bs-toggle="collapse" class="{{ $isUsersActive ? 'active' : '' }}">
                                <i class="fe-user"></i>
                                <span> Users </span>
                                <span class="menu-arrow"></span>
                            </a>
                            <div class="collapse {{ $isUsersActive ? 'show' : '' }}" id="sub_menu_users">
                                <ul class="nav-second-level">
                                    <li class="{{ request()->routeIs('*employee') ? 'menuitem-active' : '' }}">
                                        <a href="{{ panelRoute('employee') }}" class="{{ request()->routeIs('*employee') ? 'active' : '' }}">
                                            <i class="fe-users"></i>
                                            <span class="custom-ml-15">Users List</span>
                                            @if(function_exists('getEmployeeCount'))
                                            <span class="badge ms-2" style="background-color: #f06115; color: white; font-weight: bold;">
                                                {{ getEmployeeCount() }}
                                            </span>
                                            @endif
                                        </a>
                                    </li>
                                    <li class="{{ request()->routeIs('*createEmployee*') ? 'menuitem-active' : '' }}">
                                        <a href="{{ panelRoute('createEmployee') }}" class="{{ request()->routeIs('*createEmployee*') ? 'active' : '' }}">
                                            <i class="fe-user-plus"></i>
                                            <span class="custom-ml-15">Create User</span>
                                        </a>
                                    </li>
                                    @if($userRole === 'admin')
                                        <li class="{{ request()->routeIs('*users*', '*createUser*', '*editUser*') ? 'menuitem-active' : '' }}">
                                            <a href="{{ panelRoute('users') }}" class="{{ request()->routeIs('*users*', '*createUser*', '*editUser*') ? 'active' : '' }}">
                                                <i class="fe-shield"></i>
                                                <span class="custom-ml-15">Admins / System Users</span>
                                            </a>
                                        </li>
                                    @endif
                                </ul>
                            </div>
                        </li>
                    @endcanMenu

                    {{-- Government Contributions --}}
                    @canMenu('gov_contributions')
                        <li class="{{ request()->routeIs('*government-contributions*') ? 'menuitem-active' : '' }}">
                            <a href="{{ panelRoute('government-contributions.index') }}" class="{{ request()->routeIs('*government-contributions*') ? 'active' : '' }}">
                                <i class="fe-file-text"></i>
                                <span>Gov. Contributions</span>
                            </a>
                        </li>
                    @endcanMenu
                @endif

                {{-- Master Catalogs Dropdown --}}
                @if(
                    \App\Models\MenuRoleAccess::hasModuleAccess(auth()->user(), 'income_types') ||
                    \App\Models\MenuRoleAccess::hasModuleAccess(auth()->user(), 'deduction_types') ||
                    \App\Models\MenuRoleAccess::hasModuleAccess(auth()->user(), 'loan_types')
                )
                    @php
                        $isMasterCatalogActive = request()->routeIs('*income-types*', '*deduction-types*', '*loan-types*');
                    @endphp
                    <li class="{{ $isMasterCatalogActive ? 'menuitem-active' : '' }}">
                        <a href="#sub_menu_master_catalogs" data-bs-toggle="collapse" class="{{ $isMasterCatalogActive ? 'active' : '' }}">
                            <i class="fe-book-open"></i>
                            <span>Master Catalogs</span>
                            <span class="menu-arrow"></span>
                        </a>
                        <div class="collapse {{ $isMasterCatalogActive ? 'show' : '' }}" id="sub_menu_master_catalogs">
                            <ul class="nav-second-level">
                                @canMenu('income_types')
                                    <li class="{{ request()->routeIs('*income-types*') ? 'menuitem-active' : '' }}">
                                        <a href="{{ panelRoute('income-types.index') }}" class="{{ request()->routeIs('*income-types*') ? 'active' : '' }}">
                                            <i class="fe-plus-circle"></i>
                                            <span class="custom-ml-15">Income Types (Earnings)</span>
                                        </a>
                                    </li>
                                @endcanMenu

                                @canMenu('deduction_types')
                                    <li class="{{ request()->routeIs('*deduction-types*') ? 'menuitem-active' : '' }}">
                                        <a href="{{ panelRoute('deduction-types.index') }}" class="{{ request()->routeIs('*deduction-types*') ? 'active' : '' }}">
                                            <i class="fe-minus-circle"></i>
                                            <span class="custom-ml-15">Deduction Types</span>
                                        </a>
                                    </li>
                                @endcanMenu

                                @canMenu('loan_types')
                                    <li class="{{ request()->routeIs('*loan-types*') ? 'menuitem-active' : '' }}">
                                        <a href="{{ panelRoute('loan-types.index') }}" class="{{ request()->routeIs('*loan-types*') ? 'active' : '' }}">
                                            <i class="fe-repeat"></i>
                                            <span class="custom-ml-15">Loan Types Setup</span>
                                        </a>
                                    </li>
                                @endcanMenu
                            </ul>
                        </div>
                    </li>
                @endif

                {{-- Payroll Operations --}}
                @if(
                    \App\Models\MenuRoleAccess::hasModuleAccess(auth()->user(), 'payroll_employees') ||
                    \App\Models\MenuRoleAccess::hasModuleAccess(auth()->user(), 'departments') ||
                    \App\Models\MenuRoleAccess::hasModuleAccess(auth()->user(), 'attendance') ||
                    \App\Models\MenuRoleAccess::hasModuleAccess(auth()->user(), 'hr_adjustments') ||
                    \App\Models\MenuRoleAccess::hasModuleAccess(auth()->user(), 'payroll_drafts') ||
                    \App\Models\MenuRoleAccess::hasModuleAccess(auth()->user(), 'payroll_approvals') ||
                    \App\Models\MenuRoleAccess::hasModuleAccess(auth()->user(), 'payroll_periods')
                )
                    <li class="menu-title mt-2">Payroll Operations</li>

                    @php
                        $isPayrollActive = request()->routeIs(
                            '*payroll-employees*',
                            '*departments*',
                            '*attendance*',
                            '*hr-adjustments*',
                            '*payroll-drafts*',
                            '*payroll-approvals*',
                            '*payroll-periods*'
                        );
                    @endphp
                    <li class="{{ $isPayrollActive ? 'menuitem-active' : '' }}">
                        <a href="#sub_menu_payroll" data-bs-toggle="collapse" class="{{ $isPayrollActive ? 'active' : '' }}">
                            <i class="fe-dollar-sign"></i>
                            <span>Payroll</span>
                            <span class="menu-arrow"></span>
                        </a>
                        <div class="collapse {{ $isPayrollActive ? 'show' : '' }}" id="sub_menu_payroll">
                            <ul class="nav-second-level">
                                @canMenu('payroll_employees')
                                    <li class="{{ request()->routeIs('*payroll-employees.index', '*payroll-employees.create*', '*payroll-employees.edit*') ? 'menuitem-active' : '' }}">
                                        <a href="{{ panelRoute('payroll-employees.index') }}" class="{{ request()->routeIs('*payroll-employees.index', '*payroll-employees.create*', '*payroll-employees.edit*') ? 'active' : '' }}">
                                            <i class="fe-users"></i>
                                            <span class="custom-ml-15">Employee Record Management</span>
                                        </a>
                                    </li>
                                    <li class="{{ request()->routeIs('*company-master*') ? 'menuitem-active' : '' }}">
                                        <a href="{{ panelRoute('payroll-employees.company-master') }}" class="{{ request()->routeIs('*company-master*') ? 'active' : '' }}">
                                            <i class="fe-grid"></i>
                                            <span class="custom-ml-15">Company Master</span>
                                        </a>
                                    </li>
                                @endcanMenu

                                @canMenu('departments')
                                    <li class="{{ request()->routeIs('*departments*') ? 'menuitem-active' : '' }}">
                                        <a href="{{ panelRoute('departments.index') }}" class="{{ request()->routeIs('*departments*') ? 'active' : '' }}">
                                            <i class="fe-layers"></i>
                                            <span class="custom-ml-15">Departments</span>
                                        </a>
                                    </li>
                                @endcanMenu

                                @canMenu('attendance')
                                    <li class="{{ (request()->routeIs('*attendance*') && !request()->routeIs('*attendance.devices*')) ? 'menuitem-active' : '' }}">
                                        <a href="{{ panelRoute('attendance.index') }}" class="{{ (request()->routeIs('*attendance*') && !request()->routeIs('*attendance.devices*')) ? 'active' : '' }}">
                                            <i class="fe-clock"></i>
                                            <span class="custom-ml-15">Attendance</span>
                                        </a>
                                    </li>
                                    @if(auth()->check() && (auth()->user()->role === 'admin' || (int)auth()->user()->role_id === \App\Models\Role::SUPER_ADMIN || in_array(auth()->user()->role, ['company', 'company_admin'], true) || (int)auth()->user()->role_id === \App\Models\Role::COMPANY_ADMIN))
                                        <li class="{{ request()->routeIs('*attendance.devices*') ? 'menuitem-active' : '' }}">
                                            <a href="{{ panelRoute('attendance.devices.index') }}" class="{{ request()->routeIs('*attendance.devices*') ? 'active' : '' }}">
                                                <i class="fe-cpu"></i>
                                                <span class="custom-ml-15">DELI Device Integration</span>
                                            </a>
                                        </li>
                                    @endif
                                @endcanMenu

                                @canMenu('hr_adjustments')
                                    @if(hasPanelRoute('hr-adjustments.index'))
                                        <li class="{{ request()->routeIs('*hr-adjustments*') ? 'menuitem-active' : '' }}">
                                            <a href="{{ panelRoute('hr-adjustments.index') }}" class="{{ request()->routeIs('*hr-adjustments*') ? 'active' : '' }}">
                                                <i class="fe-sliders"></i>
                                                <span class="custom-ml-15">HR Adjustments</span>
                                            </a>
                                        </li>
                                    @endif
                                @endcanMenu

                                @canMenu('payroll_drafts')
                                    <li class="{{ request()->routeIs('*payroll-drafts*') ? 'menuitem-active' : '' }}">
                                        <a href="{{ panelRoute('payroll-drafts.index') }}" class="{{ request()->routeIs('*payroll-drafts*') ? 'active' : '' }}">
                                            <i class="fe-file-plus"></i>
                                            <span class="custom-ml-15">Payroll Drafts</span>
                                        </a>
                                    </li>
                                @endcanMenu

                                @canMenu('payroll_approvals')
                                    <li class="{{ request()->routeIs('*payroll-approvals*') ? 'menuitem-active' : '' }}">
                                        <a href="{{ panelRoute('payroll-approvals.index') }}" class="{{ request()->routeIs('*payroll-approvals*') ? 'active' : '' }}">
                                            <i class="fe-check-square"></i>
                                            <span class="custom-ml-15">Payroll Approvals</span>
                                        </a>
                                    </li>
                                @endcanMenu

                                @canMenu('payroll_periods')
                                    @if(hasPanelRoute('payroll-periods.index'))
                                        <li class="{{ request()->routeIs('*payroll-periods*') ? 'menuitem-active' : '' }}">
                                            <a href="{{ panelRoute('payroll-periods.index') }}" class="{{ request()->routeIs('*payroll-periods*') ? 'active' : '' }}">
                                                <i class="fe-calendar"></i>
                                                <span class="custom-ml-15">Payroll Periods</span>
                                            </a>
                                        </li>
                                    @endif
                                @endcanMenu
                            </ul>
                        </div>
                    </li>
                @endif

                {{-- Loans & Requests --}}
                @if(
                    \App\Models\MenuRoleAccess::hasModuleAccess(auth()->user(), 'vale_loans') ||
                    \App\Models\MenuRoleAccess::hasModuleAccess(auth()->user(), 'loan_requests')
                )
                    <li class="menu-title mt-2">Loans & Requests</li>
                    @canMenu('vale_loans')
                        <li class="{{ request()->routeIs('*loan-monitor*') ? 'menuitem-active' : '' }}">
                            <a href="{{ panelRoute('loan-monitor.index') }}" class="{{ request()->routeIs('*loan-monitor*') ? 'active' : '' }}">
                                <i class="fe-credit-card"></i>
                                <span>Loan Monitor</span>
                            </a>
                        </li>
                    @endcanMenu

                    @canMenu('loan_requests')
                        <li class="{{ request()->routeIs('*loan-requests*') ? 'menuitem-active' : '' }}">
                            <a href="{{ panelRoute(auth()->check() && auth()->user()->role === 'employee' ? 'loan-requests.index' : 'loan-requests.approval-queue') }}" class="{{ request()->routeIs('*loan-requests*') ? 'active' : '' }}">
                                <i class="fe-inbox"></i>
                                <span>Loan Requests</span>
                            </a>
                        </li>
                    @endcanMenu
                @endif

                {{-- Reporting & Audit --}}
                @if(
                    \App\Models\MenuRoleAccess::hasModuleAccess(auth()->user(), 'reports') ||
                    \App\Models\MenuRoleAccess::hasModuleAccess(auth()->user(), 'activity_logs')
                )
                    <li class="menu-title mt-2">Reporting & Audit</li>
                    @canMenu('reports')
                        <li class="{{ request()->routeIs('*reports.index*') ? 'menuitem-active' : '' }}">
                            <a href="{{ panelRoute('reports.index') }}" class="{{ request()->routeIs('*reports.index*') ? 'active' : '' }}">
                                <i class="fe-bar-chart-2"></i>
                                <span>Reports Hub</span>
                            </a>
                        </li>
                    @endcanMenu

                    @canMenu('activity_logs')
                        <li class="{{ request()->routeIs('*activity-logs*') ? 'menuitem-active' : '' }}">
                            <a href="{{ panelRoute('activity-logs.index') }}" class="{{ request()->routeIs('*activity-logs*') ? 'active' : '' }}">
                                <i class="fe-activity"></i>
                                <span>Activity Logs</span>
                            </a>
                        </li>
                    @endcanMenu
                @endif

                {{-- Tenant Control (Super Admin) --}}
                @if($userRole === 'admin' || (auth()->check() && (int) auth()->user()->role_id === \App\Models\Role::SUPER_ADMIN))
                    @canMenu('companies')
                        @php
                            $isCompaniesActive = request()->routeIs('*createCompany*', '*companies*', '*pending*', '*deactivatedCompanies*');
                        @endphp
                        <li class="menu-title mt-2">Tenant Control</li>
                        <li class="{{ $isCompaniesActive ? 'menuitem-active' : '' }}">
                            <a href="#sub_menu_company_listing" data-bs-toggle="collapse" class="{{ $isCompaniesActive ? 'active' : '' }}">
                                <i class="fe-briefcase"></i>
                                <span> {{ __('messages.companies') }} </span>
                                <span class="menu-arrow"></span>
                            </a>
                            <div class="collapse {{ $isCompaniesActive ? 'show' : '' }}" id="sub_menu_company_listing">
                                <ul class="nav-second-level">
                                    @if(hasPanelRoute('createCompany'))
                                    <li class="{{ request()->routeIs('*createCompany*') ? 'menuitem-active' : '' }}">
                                        <a href="{{ panelRoute('createCompany') }}" class="{{ request()->routeIs('*createCompany*') ? 'active' : '' }}">
                                            <i class="fe-plus"></i>
                                            <span class="custom-ml-15">{{ __('messages.create') }}</span>
                                        </a>
                                    </li>
                                    @endif

                                    @if(hasPanelRoute('companies'))
                                    <li class="{{ request()->routeIs('*companies*') ? 'menuitem-active' : '' }}">
                                        <a href="{{ panelRoute('companies') }}" class="{{ request()->routeIs('*companies*') ? 'active' : '' }}">
                                            <i class="fe-check-circle"></i>
                                            <span class="custom-ml-15">{{ __('messages.active') }}</span>
                                            @if(function_exists('getCompaniesCount'))
                                            <span class="badge ms-2" style="background-color: #f06115; color: white; font-weight: bold;">
                                                {{ getCompaniesCount('1') }}
                                            </span>
                                            @endif
                                        </a>
                                    </li>
                                    @endif

                                    @if(hasPanelRoute('pending'))
                                    <li class="{{ request()->routeIs('*pending*') ? 'menuitem-active' : '' }}">
                                        <a href="{{ panelRoute('pending') }}" class="{{ request()->routeIs('*pending*') ? 'active' : '' }}">
                                            <i class="fe-clock"></i>
                                            <span class="custom-ml-15">{{ __('messages.pending') }}</span>
                                            @if(function_exists('getCompaniesCount'))
                                            <span class="badge ms-2" style="background-color: #f06115; color: white; font-weight: bold;">
                                                {{ getCompaniesCount('0') }}
                                            </span>
                                            @endif
                                        </a>
                                    </li>
                                    @endif

                                    @if(hasPanelRoute('deactivatedCompanies'))
                                    <li class="{{ request()->routeIs('*deactivatedCompanies*') ? 'menuitem-active' : '' }}">
                                        <a href="{{ panelRoute('deactivatedCompanies') }}" class="{{ request()->routeIs('*deactivatedCompanies*') ? 'active' : '' }}">
                                            <i class="fe-x-circle"></i>
                                            <span class="custom-ml-15">{{ __('messages.deactivated') }}</span>
                                            @if(function_exists('getCompaniesCount'))
                                            <span class="badge ms-2" style="background-color: #f06115; color: white; font-weight: bold;">
                                                {{ getCompaniesCount('2') }}
                                            </span>
                                            @endif
                                        </a>
                                    </li>
                                    @endif
                                </ul>
                            </div>
                        </li>
                    @endcanMenu
                @endif

                {{-- Permissions Matrix --}}
                @canMenu('permissions')
                    <li class="{{ request()->routeIs('*permissions*') ? 'menuitem-active' : '' }}">
                        <a href="{{ panelRoute('permissions.matrix') }}" class="{{ request()->routeIs('*permissions*') ? 'active' : '' }}">
                            <i class="fe-lock"></i>
                            <span>Permissions Matrix</span>
                        </a>
                    </li>
                @endcanMenu

                {{-- System / Account Settings --}}
                @canMenu('settings')
                    <li class="{{ request()->routeIs('*settings*') ? 'menuitem-active' : '' }}">
                        <a href="{{ panelRoute('settings') }}" class="{{ request()->routeIs('*settings*') ? 'active' : '' }}">
                            <i class="fe-settings"></i>
                            <span>Settings</span>
                        </a>
                    </li>
                @endcanMenu

            </ul>

        </div>
        <!-- End Sidebar -->

        <div class="clearfix"></div>

    </div>
    <!-- Sidebar -left -->
</div>
