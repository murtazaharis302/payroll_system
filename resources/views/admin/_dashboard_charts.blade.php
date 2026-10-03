            <!-- Chart Section -->
            @if(Auth::user()->role === 'admin' || (Auth::user()->role === 'employee' && isset($formattedChartData)))
            <div class="col-12">
                <div class="card" dir="ltr">
                    <div class="card-body" id="dashboardtable">
                        <h4 class="header-title mb-3">Company Registration Chart</h4>
                        <div class="text-center">
                            <p class="text-muted font-15 font-family-secondary mb-0">
                                <span class="mx-2">
                                    <i class="mdi mdi-checkbox-blank-circle" style="color: #28a745"></i>
                                    Active
                                </span>
                                <span class="mx-2">
                                    <i class="mdi mdi-checkbox-blank-circle" style="color: #f06115"></i>
                                    Pending
                                </span>
                                <span class="mx-2">
                                    <i class="mdi mdi-checkbox-blank-circle" style="color: #dc3545"></i>
                                    Deactivated
                                </span>
                            </p>
                        </div>
                        <div id="morris-bar-example" style="height: 350px;" class="morris-chart"></div>
                    </div>
                </div>
            </div>
            @endif

            @if(Auth::user()->role === 'admin' || (Auth::user()->role === 'employee' && isset($recentCompanies)))
            <!-- Recent Companies Table -->
            <div class="col-12 mt-4">
                <div class="card shadow-sm border-0">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="fw-bold mb-0">Recent Companies</h5>
                            @if(can('companies', 'view'))
                            <a href="{{ panelRoute('companies') }}" class="btn btn-sm text-white" style="background-color: #f06115;">
                                View All
                            </a>
                            @endif
                        </div>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Company Name</th>
                                        <th>Owner</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Status</th>
                                        <th>Registered Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($recentCompanies as $company)
                                        <tr>
                                            <td>{{ $company->name }}</td>
                                            <td>{{ $company->user->name ?? 'N/A' }}</td>
                                            <td>{{ $company->email }}</td>
                                            <td>{{ $company->phone }}</td>
                                            <td>
                                                @if($company->status == 1)
                                                    <span class="badge bg-success">Active</span>
                                                @elseif($company->status == 0)
                                                    <span class="badge bg-warning">Pending</span>
                                                @else
                                                    <span class="badge bg-danger">Deactivated</span>
                                                @endif
                                            </td>
                                            <td>{{ $company->created_at->format('Y-m-d') }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center text-muted">No companies found</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            @endif

        </div> <!-- container -->
