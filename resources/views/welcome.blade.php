@extends('loginLayout')

@section('title', 'Select Portal')

@section('content')
<body class="bg-light" style="background-color: #f8f9fa !important;">
    
    <div class="account-pages py-4">
        <div class="container" style="max-width: 720px;">
            
            <!-- System Branding Header -->
            <div class="text-center mb-3">
                <div class="d-inline-flex align-items-center justify-content-center avatar-md rounded-circle me-2 text-white shadow-sm" style="background-color: #f06115; width:44px; height:44px;">
                    <i class="mdi mdi-account-group font-22"></i>
                </div>
                <h3 class="d-inline-block align-middle fw-bold text-dark mb-0 font-22">Payroll <span style="color: #f06115;">System</span></h3>
                <p class="text-muted font-13 mt-1 mb-0">Select a portal module to log in</p>
            </div>

            <!-- Skeleton Loader UI (Displays initially then hides) -->
            <div id="skeletonLoaderGrid" class="row g-3 justify-content-center">
                <div class="col-6">
                    <div class="card border-0 shadow-sm rounded-3 p-3 text-center bg-white">
                        <div class="skeleton-avatar mx-auto mb-2"></div>
                        <div class="skeleton-text mx-auto w-75 mb-1"></div>
                        <div class="skeleton-badge mx-auto w-50"></div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="card border-0 shadow-sm rounded-3 p-3 text-center bg-white">
                        <div class="skeleton-avatar mx-auto mb-2"></div>
                        <div class="skeleton-text mx-auto w-75 mb-1"></div>
                        <div class="skeleton-badge mx-auto w-50"></div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="card border-0 shadow-sm rounded-3 p-3 text-center bg-white">
                        <div class="skeleton-avatar mx-auto mb-2"></div>
                        <div class="skeleton-text mx-auto w-75 mb-1"></div>
                        <div class="skeleton-badge mx-auto w-50"></div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="card border-0 shadow-sm rounded-3 p-3 text-center bg-white">
                        <div class="skeleton-avatar mx-auto mb-2"></div>
                        <div class="skeleton-text mx-auto w-75 mb-1"></div>
                        <div class="skeleton-badge mx-auto w-50"></div>
                    </div>
                </div>
            </div>

            <!-- 4 Compact Module Cards (2x2 Grid, Dashboard Orange Accent) -->
            <div id="realModulesGrid" class="row g-3 justify-content-center d-none">
                
                <!-- Module 1: App Owner -->
                <div class="col-6">
                    <a href="{{ url('admin/login') }}" class="text-decoration-none">
                        <div class="card bg-white border-0 shadow-sm rounded-3 p-2 text-center compact-module-card h-100">
                            <div class="card-body p-2">
                                <div class="avatar-md rounded-circle mx-auto mb-2 d-flex align-items-center justify-content-center text-white" style="background-color: #f06115; width: 44px; height: 44px; font-size: 20px;">
                                    <i class="fe-shield"></i>
                                </div>
                                <h5 class="fw-bold text-dark mb-1 font-15">App Owner</h5>
                                <span class="badge font-11 px-2 py-1" style="background-color: rgba(240, 97, 21, 0.12); color: #f06115;">App Owner</span>
                            </div>
                        </div>
                    </a>
                </div>

                <!-- Module 2: Administrative Office -->
                <div class="col-6">
                    <a href="{{ url('company/login') }}" class="text-decoration-none">
                        <div class="card bg-white border-0 shadow-sm rounded-3 p-2 text-center compact-module-card h-100">
                            <div class="card-body p-2">
                                <div class="avatar-md rounded-circle mx-auto mb-2 d-flex align-items-center justify-content-center text-white" style="background-color: #f06115; width: 44px; height: 44px; font-size: 20px;">
                                    <i class="fe-briefcase"></i>
                                </div>
                                <h5 class="fw-bold text-dark mb-1 font-15">Administrative Office</h5>
                                <span class="badge font-11 px-2 py-1" style="background-color: rgba(240, 97, 21, 0.12); color: #f06115;">Admin</span>
                            </div>
                        </div>
                    </a>
                </div>

                <!-- Module 3: Human Resource -->
                <div class="col-6">
                    <a href="{{ url('hr/login') }}" class="text-decoration-none">
                        <div class="card bg-white border-0 shadow-sm rounded-3 p-2 text-center compact-module-card h-100">
                            <div class="card-body p-2">
                                <div class="avatar-md rounded-circle mx-auto mb-2 d-flex align-items-center justify-content-center text-white" style="background-color: #f06115; width: 44px; height: 44px; font-size: 20px;">
                                    <i class="fe-sliders"></i>
                                </div>
                                <h5 class="fw-bold text-dark mb-1 font-15">Human Resource</h5>
                                <span class="badge font-11 px-2 py-1" style="background-color: rgba(240, 97, 21, 0.12); color: #f06115;">HR Operations</span>
                            </div>
                        </div>
                    </a>
                </div>

                <!-- Module 4: Employee -->
                <div class="col-6">
                    <a href="{{ url('employee/login') }}" class="text-decoration-none">
                        <div class="card bg-white border-0 shadow-sm rounded-3 p-2 text-center compact-module-card h-100">
                            <div class="card-body p-2">
                                <div class="avatar-md rounded-circle mx-auto mb-2 d-flex align-items-center justify-content-center text-white" style="background-color: #f06115; width: 44px; height: 44px; font-size: 20px;">
                                    <i class="fe-user"></i>
                                </div>
                                <h5 class="fw-bold text-dark mb-1 font-15">Employee</h5>
                                <span class="badge font-11 px-2 py-1" style="background-color: rgba(240, 97, 21, 0.12); color: #f06115;">Self Service</span>
                            </div>
                        </div>
                    </a>
                </div>

            </div>

            <div class="text-center text-muted font-12 mt-4">
                &copy; {{ date('Y') }} Payroll System. All rights reserved.
            </div>

        </div>
    </div>

    <style>
        /* Skeleton Animation */
        .skeleton-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: linear-gradient(90deg, #e2e8f0 25%, #f1f5f9 50%, #e2e8f0 75%);
            background-size: 200% 100%;
            animation: shimmer 1.2s infinite;
        }
        .skeleton-text {
            height: 14px;
            border-radius: 4px;
            background: linear-gradient(90deg, #e2e8f0 25%, #f1f5f9 50%, #e2e8f0 75%);
            background-size: 200% 100%;
            animation: shimmer 1.2s infinite;
        }
        .skeleton-badge {
            height: 12px;
            border-radius: 4px;
            background: linear-gradient(90deg, #e2e8f0 25%, #f1f5f9 50%, #e2e8f0 75%);
            background-size: 200% 100%;
            animation: shimmer 1.2s infinite;
        }
        @keyframes shimmer {
            0% { background-position: -200% 0; }
            100% { background-position: 200% 0; }
        }

        /* Compact Module Cards */
        .compact-module-card {
            transition: all 0.2s ease-in-out;
            border: 1px solid #e2e8f0 !important;
        }
        .compact-module-card:hover {
            transform: translateY(-3px);
            border-color: #f06115 !important;
            box-shadow: 0 8px 16px rgba(240, 97, 21, 0.12) !important;
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                var skel = document.getElementById('skeletonLoaderGrid');
                var real = document.getElementById('realModulesGrid');
                if (skel) skel.classList.add('d-none');
                if (real) real.classList.remove('d-none');
            }, 300);
        });
    </script>
</body>
@endsection
