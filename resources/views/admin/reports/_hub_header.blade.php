<div class="content-wrapper">
    <div class="page-header d-flex align-items-center justify-content-between py-2 mb-3">
        <h3 class="page-title mb-0">
            <span class="page-title-icon bg-gradient-primary text-white me-2 d-inline-flex align-items-center justify-content-center" style="width:36px; height:36px; border-radius:6px;">
                <i class="fe-bar-chart-2"></i>
            </span> Reports Hub & Statutory Compliance
        </h3>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb m-0">
                <li class="breadcrumb-item"><a href="{{ panelRoute('dashboard') }}">Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Reports</li>
            </ol>
        </nav>
    </div>

    <!-- Display Validation Errors / Flash Messages -->
    @if (isset($errors) && $errors->any())
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <strong>Validation Error!</strong>
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <strong>Error!</strong> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <strong>Success!</strong> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Super Admin Target Company Filter -->
    @if(auth()->user()?->role === 'admin' && isset($companies) && count($companies) > 0)
    <div class="row mb-3">
        <div class="col-12">
            <div class="card bg-white border-0 shadow-sm">
                <div class="card-body py-2">
                    <form method="GET" action="{{ panelRoute('reports.index') }}" class="d-flex align-items-center flex-wrap">
                        <label class="mb-0 me-3 font-weight-bold text-dark font-13">
                            <i class="fe-briefcase text-primary me-1"></i> Target Tenant Company:
                        </label>
                        <select name="company_id" class="form-select form-select-sm w-auto me-3 font-13" onchange="this.form.submit()">
                            @foreach($companies as $comp)
                                <option value="{{ $comp->id }}" {{ $selectedCompanyId == $comp->id ? 'selected' : '' }}>
                                    {{ $comp->name }} ({{ $comp->company_code }})
                                </option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-sm btn-primary">Switch Tenant</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- 📊 Statistics Metric Cards -->
    <div class="row mb-4">
        <div class="col-md-4 stretch-card grid-margin mb-3 mb-md-0">
            <div class="card bg-white border-0 shadow-sm border-start border-4 border-info h-100">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted font-12 text-uppercase d-block fw-semibold">Active Employees</span>
                            <h2 class="mb-0 text-dark fw-bold">{{ $stats['total_employees'] }}</h2>
                        </div>
                        <div class="avatar-md rounded-circle bg-soft-info text-info d-flex align-items-center justify-content-center font-22 shadow-sm" style="width:48px; height:48px;">
                            <i class="fe-users"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 stretch-card grid-margin mb-3 mb-md-0">
            <div class="card bg-white border-0 shadow-sm border-start border-4 border-success h-100">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted font-12 text-uppercase d-block fw-semibold">Payroll Periods</span>
                            <h2 class="mb-0 text-dark fw-bold">{{ $stats['total_payroll_periods'] }}</h2>
                        </div>
                        <div class="avatar-md rounded-circle bg-soft-success text-success d-flex align-items-center justify-content-center font-22 shadow-sm" style="width:48px; height:48px;">
                            <i class="fe-calendar"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 stretch-card grid-margin mb-3 mb-md-0">
            <div class="card bg-white border-0 shadow-sm border-start border-4 border-danger h-100">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted font-12 text-uppercase d-block fw-semibold">Latest Cut-off Period</span>
                            <h5 class="mb-0 text-dark fw-bold">{{ $stats['latest_period'] ? $stats['latest_period']->period_name : 'N/A' }}</h5>
                        </div>
                        <div class="avatar-md rounded-circle bg-soft-danger text-danger d-flex align-items-center justify-content-center font-22 shadow-sm" style="width:48px; height:48px;">
                            <i class="fe-clock"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 🔍 Real-Time Fuzzy Search Bar & Filters -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm bg-white">
                <div class="card-body py-3">
                    <div class="row align-items-center">
                        <div class="col-md-8 mb-2 mb-md-0">
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0 text-muted">
                                    <i class="fe-search font-16"></i>
                                </span>
                                <input type="text" id="reportSearchInput" class="form-control border-start-0 ps-0" placeholder="Type to filter reports instantly across categories (e.g., Payslip, 13th Month, SSS, PhilHealth, Tax, Bank, Ledger)..." autocomplete="off">
                            </div>
                        </div>
                        <div class="col-md-4 text-md-end">
                            <span id="reportSearchCount" class="badge bg-soft-primary text-primary font-13 px-3 py-2 fw-semibold">
                                Showing all report modules
                            </span>
                            <button type="button" id="clearReportSearch" class="btn btn-sm btn-link text-muted d-none ms-2" onclick="clearReportSearchInput()">
                                <i class="fe-x"></i> Clear
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 🗂️ Categorized Top Navigation Tabs -->
    <div class="row mb-3">
        <div class="col-12">
            <ul class="nav nav-pills nav-justified bg-white p-2 rounded shadow-sm border" id="reportCategoryTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active font-14 fw-bold" id="tab-payroll" data-bs-toggle="tab" data-bs-target="#pane-payroll" type="button" role="tab" data-tab-id="payroll">
                        <i class="fe-dollar-sign me-1 text-primary"></i> Payroll & Slips
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link font-14 fw-bold" id="tab-statutory" data-bs-toggle="tab" data-bs-target="#pane-statutory" type="button" role="tab" data-tab-id="statutory">
                        <i class="fe-shield me-1 text-warning"></i> Statutory & Tax
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link font-14 fw-bold" id="tab-banking" data-bs-toggle="tab" data-bs-target="#pane-banking" type="button" role="tab" data-tab-id="banking">
                        <i class="fe-credit-card me-1 text-info"></i> Banking & Transmittals
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link font-14 fw-bold" id="tab-history" data-bs-toggle="tab" data-bs-target="#pane-history" type="button" role="tab" data-tab-id="history">
                        <i class="fe-book-open me-1 text-purple" style="color: #6f42c1;"></i> History & Ledgers
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link font-14 fw-bold" id="tab-all" data-bs-toggle="tab" data-bs-target="#pane-all" type="button" role="tab" data-tab-id="all">
                        <i class="fe-grid me-1 text-success"></i> All Reports
                    </button>
                </li>
            </ul>
        </div>
    </div>

    <!-- Empty Search Results Alert -->
    <div id="noReportsFoundMessage" class="row d-none">
        <div class="col-12 text-center py-5 bg-white rounded shadow-sm">
            <div class="avatar-lg bg-soft-secondary text-secondary rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center" style="width:60px; height:60px;">
                <i class="fe-search font-24"></i>
            </div>
            <h5 class="fw-bold text-dark">No matching report cards found</h5>
            <p class="text-muted font-13 mb-3">Try searching for keywords like "payslip", "sss", "bank", "tax", "income", or "13th month".</p>
            <button type="button" class="btn btn-sm btn-outline-primary" onclick="clearReportSearchInput()">
                Reset Filter
            </button>
        </div>
    </div>
