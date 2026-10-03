        @if(isset($formattedChartData))
        <script>
            window.bookingDashboardUrl = "{{ panelRoute('dashboard') }}";
            window.formattedChartData = @json($formattedChartData);
        </script>
        <script src="{{ asset('assets/js/admin/dashboardEarning.js') }}"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/raphael/2.3.0/raphael.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/morris.js/0.5.1/morris.min.js"></script>
        @endif
        <!-- Start Content-->
        <div class="container-fluid">
            <!-- start page title -->
            <div class="row">
                <div class="col-12">
                    <div class="page-title-box">
                        <div class="page-title-right">
                            <ol class="breadcrumb m-0">
                                <li class="breadcrumb-item active">{{ __('messages.dashboard') }}</li>
                            </ol>
                        </div>
                        <h4 class="page-title">
                            @if(Auth::user()->role === 'admin')
                                Admin Dashboard
                            @elseif(Auth::user()->role === 'company')
                                Company Dashboard
                            @elseif(Auth::user()->role === 'hr')
                                HR Dashboard
                            @elseif(Auth::user()->role === 'accounting')
                                Accounting Dashboard
                            @elseif(Auth::user()->role === 'employee')
                                Employee Dashboard
                            @else
                                {{ __('messages.dashboard') }}
                            @endif
                        </h4>
                    </div>
                </div>
            </div>
            <!-- end page title -->
            <div class="row" id="cards-container">
                @if(Auth::user()->role === 'admin' || (Auth::user()->role === 'employee' && isset($totalCompanies)))
                    <!-- Admin Dashboard Cards - Company Management -->
                    <!-- Card 1: Total Companies -->
                    @if(can('companies', 'view'))
                    <div class="col-md-4 mb-4">
                        <a href="{{ panelRoute('companies') }}" style="text-decoration: none;">
                            <div class="card bg-pattern card-clickable">
                                <div class="card-body d-flex">
                                    <div class="avatar-md bg-orange-clr rounded me-3">
                                        <i class="fe-briefcase avatar-title font-22 text-white"></i>
                                    </div>
                                    <div>
                                        <h3 class="text-dark my-1">{{ $totalCompanies }}</h3>
                                        <p class="text-muted mb-0">Total Active Companies</p>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    @endif

                    <!-- Card 2: Pending Companies -->
                    @if(can('companies', 'view'))
                    <div class="col-md-4 mb-4">
                        <a href="{{ panelRoute('pending') }}" style="text-decoration: none;">
                            <div class="card bg-pattern card-clickable">
                                <div class="card-body d-flex">
                                    <div class="avatar-md bg-orange-clr rounded me-3">
                                        <i class="fe-clock avatar-title font-22 text-white"></i>
                                    </div>
                                    <div>
                                        <h3 class="text-dark my-1">{{ $pendingCompanies }}</h3>
                                        <p class="text-muted mb-0">Pending Approval</p>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    @endif

                    <!-- Card 3: Deactivated Companies -->
                    @if(can('companies', 'view'))
                    <div class="col-md-4 mb-4">
                        <a href="{{ panelRoute('deactivatedCompanies') }}" style="text-decoration: none;">
                            <div class="card bg-pattern card-clickable">
                                <div class="card-body d-flex">
                                    <div class="avatar-md bg-orange-clr rounded me-3">
                                        <i class="fe-x-circle avatar-title font-22 text-white"></i>
                                    </div>
                                    <div>
                                        <h3 class="text-dark my-1">{{ $deactivatedCompanies }}</h3>
                                        <p class="text-muted mb-0">Deactivated Companies</p>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    @endif

                    <!-- Card 4: Total Employees -->
                    @if(can('employees', 'view'))
                    <div class="col-md-4 mb-4">
                        <a href="{{ panelRoute('employee') }}" style="text-decoration: none;">
                            <div class="card bg-pattern card-clickable">
                                <div class="card-body d-flex">
                                    <div class="avatar-md bg-orange-clr rounded me-3">
                                        <i class="fe-users avatar-title font-22 text-white"></i>
                                    </div>
                                    <div>
                                        <h3 class="text-dark my-1">{{ $totalEmployees }}</h3>
                                        <p class="text-muted mb-0">Total Employees</p>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    @endif

                    <!-- Card 5: Total Users -->
                    @if(can('users', 'view'))
                    <div class="col-md-4 mb-4">
                        <a href="{{ panelRoute('users') }}" style="text-decoration: none;">
                            <div class="card bg-pattern card-clickable">
                                <div class="card-body d-flex">
                                    <div class="avatar-md bg-orange-clr rounded me-3">
                                        <i class="fe-user avatar-title font-22 text-white"></i>
                                    </div>
                                    <div>
                                        <h3 class="text-dark my-1">{{ $totalUsers }}</h3>
                                        <p class="text-muted mb-0">Website Users</p>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    @endif

                @elseif(Auth::user()->role === 'hr')
                    <!-- HR Dashboard Cards -->
                    @if(isset($companyData))
                    <div class="col-md-6 mb-4">
                        <div class="card bg-pattern">
                            <div class="card-body">
                                <h5 class="card-title">Company</h5>
                                <p class="mb-1"><strong>Name:</strong> {{ $companyData->name }}</p>
                                <p class="mb-0"><strong>Email:</strong> {{ $companyData->email }}</p>
                            </div>
                        </div>
                    </div>
                    @elseif(!getActiveCompanyId())
                    <div class="col-12 mb-4">
                        <div class="alert alert-info mb-0">
                            You are logged in as <strong>Admin HR</strong> — you can view and manage data for <strong>all companies</strong>.
                        </div>
                    </div>
                    @endif

                    <div class="col-md-4 mb-4">
                        <div class="card bg-pattern">
                            <div class="card-body d-flex">
                                <div class="avatar-md bg-orange-clr rounded me-3">
                                    <i class="fe-users avatar-title font-22 text-white"></i>
                                </div>
                                <div>
                                    <h3 class="text-dark my-1">{{ $totalEmployees ?? 0 }}</h3>
                                    <p class="text-muted mb-0">Active Payroll Employees</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4 mb-4">
                        <div class="card bg-pattern">
                            <div class="card-body d-flex">
                                <div class="avatar-md bg-orange-clr rounded me-3">
                                    <i class="fe-file-text avatar-title font-22 text-white"></i>
                                </div>
                                <div>
                                    <h3 class="text-dark my-1">{{ $draftPayrolls ?? 0 }}</h3>
                                    <p class="text-muted mb-0">Draft Payrolls</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4 mb-4">
                        <div class="card bg-pattern">
                            <div class="card-body d-flex">
                                <div class="avatar-md bg-orange-clr rounded me-3">
                                    <i class="fe-clock avatar-title font-22 text-white"></i>
                                </div>
                                <div>
                                    <h3 class="text-dark my-1">{{ $pendingPayrolls ?? 0 }}</h3>
                                    <p class="text-muted mb-0">Pending Approval</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    @if(isset($recentDrafts) && $recentDrafts->count() > 0)
                    <div class="col-12 mt-4">
                        <div class="card shadow-sm border-0">
                            <div class="card-body">
                                <h5 class="fw-bold mb-3">Recent Payroll Drafts</h5>
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>Employee</th>
                                                <th>Period</th>
                                                <th>Net Pay</th>
                                                <th>Status</th>
                                                <th>Created</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($recentDrafts as $draft)
                                                <tr>
                                                    <td>{{ $draft->employee->full_name ?? 'N/A' }}</td>
                                                    <td>{{ $draft->payrollPeriod->period_name ?? 'N/A' }}</td>
                                                    <td>{{ number_format($draft->net_pay ?? 0, 2) }}</td>
                                                    <td><span class="badge bg-secondary">{{ ucfirst(str_replace('_', ' ', $draft->status)) }}</span></td>
                                                    <td>{{ $draft->created_at->format('Y-m-d') }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif

                @elseif(Auth::user()->role === 'accounting')
                    <!-- Accounting Dashboard Cards -->
                    @if(isset($companyData))
                    <div class="col-md-6 mb-4">
                        <div class="card bg-pattern">
                            <div class="card-body">
                                <h5 class="card-title">Company</h5>
                                <p class="mb-1"><strong>Name:</strong> {{ $companyData->name }}</p>
                                <p class="mb-0"><strong>Email:</strong> {{ $companyData->email }}</p>
                            </div>
                        </div>
                    </div>
                    @endif

                    <div class="col-md-4 mb-4">
                        <div class="card bg-pattern">
                            <div class="card-body d-flex">
                                <div class="avatar-md bg-orange-clr rounded me-3">
                                    <i class="fe-check-circle avatar-title font-22 text-white"></i>
                                </div>
                                <div>
                                    <h3 class="text-dark my-1">{{ $pendingApprovals ?? 0 }}</h3>
                                    <p class="text-muted mb-0">Pending Approvals</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4 mb-4">
                        <div class="card bg-pattern">
                            <div class="card-body d-flex">
                                <div class="avatar-md bg-orange-clr rounded me-3">
                                    <i class="fe-calendar avatar-title font-22 text-white"></i>
                                </div>
                                <div>
                                    <h3 class="text-dark my-1">{{ $approvedThisMonth ?? 0 }}</h3>
                                    <p class="text-muted mb-0">Approved This Month</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4 mb-4">
                        <div class="card bg-pattern">
                            <div class="card-body d-flex">
                                <div class="avatar-md bg-orange-clr rounded me-3">
                                    <i class="fe-dollar-sign avatar-title font-22 text-white"></i>
                                </div>
                                <div>
                                    <h3 class="text-dark my-1">{{ number_format($totalPendingAmount ?? 0, 2) }}</h3>
                                    <p class="text-muted mb-0">Pending Payroll Amount</p>
                                </div>
                            </div>
                        </div>
                    </div>

                @elseif(Auth::user()->role === 'company' || (Auth::user()->role === 'employee' && isset($companyData)))
                    <!-- Company/Employee (under company) Dashboard Cards -->
                    @if(isset($companyData))
                    <!-- Company Info Card -->
                    <div class="col-md-6 mb-4">
                        <div class="card bg-pattern">
                            <div class="card-body">
                                <h5 class="card-title">Company Information</h5>
                                <p class="mb-1"><strong>Company Name:</strong> {{ $companyData->name }}</p>
                                <p class="mb-1"><strong>Email:</strong> {{ $companyData->email }}</p>
                                <p class="mb-1"><strong>Phone:</strong> {{ $companyData->phone }}</p>
                                <p class="mb-0">
                                    <strong>Status:</strong>
                                    @if($companyData->status == 1)
                                        <span class="badge bg-success">Active</span>
                                    @elseif($companyData->status == 0)
                                        <span class="badge bg-warning">Pending</span>
                                    @else
                                        <span class="badge bg-danger">Deactivated</span>
                                    @endif
                                </p>
                            </div>
                        </div>
                    </div>
                    @endif                     <!-- Total Employees Card -->
                    <div class="col-md-6 mb-4">
                        @if(can('employees', 'view'))
                        <a href="{{ panelRoute('employee') }}" style="text-decoration: none;">
                        @endif
                            <div class="card bg-pattern {{ can('employees', 'view') ? 'card-clickable' : '' }}">
                                <div class="card-body d-flex">
                                    <div class="avatar-md bg-orange-clr rounded me-3">
                                        <i class="fe-users avatar-title font-22 text-white"></i>
                                    </div>
                                    <div>
                                        <h3 class="text-dark my-1">{{ $totalEmployees }}</h3>
                                        <p class="text-muted mb-0">Total Employees</p>
                                    </div>
                                </div>
                            </div>
                        @if(can('employees', 'view'))
                        </a>
                        @endif
                    </div>

                    @if(isset($recentEmployees) && $recentEmployees->count() > 0)
                    <!-- Recent Employees Table -->
                    <div class="col-12 mt-4">
                        <div class="card shadow-sm border-0">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="fw-bold mb-0">Recent Employees</h5>
                                    @if(can('employees', 'view'))
                                    <a href="{{ panelRoute('employee') }}" class="btn btn-sm text-white" style="background-color: #f06115;">
                                        View All
                                    </a>
                                    @endif
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Email</th>
                                                <th>Designation</th>
                                                <th>Phone</th>
                                                <th>Joined Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($recentEmployees as $emp)
                                                <tr>
                                                    <td>{{ $emp->employee->name ?? 'N/A' }}</td>
                                                    <td>{{ $emp->employee->email ?? 'N/A' }}</td>
                                                    <td>{{ $emp->designation }}</td>
                                                    <td>{{ $emp->employee->phone ?? 'N/A' }}</td>
                                                    <td>{{ $emp->created_at->format('Y-m-d') }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif

                @else
                    <!-- Default Welcome Message -->
                    <div class="col-12">
                        <div class="alert alert-info">
                            Welcome to your dashboard. Use the sidebar to navigate through available modules based on your permissions.
                        </div>
                    </div>
                @endif
            </div>
            <!-- end row-->
