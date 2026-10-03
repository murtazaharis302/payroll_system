@extends('admin.layouts.Master')
@section('title') Employee Details @endsection
@section('content')
    <div class="col-12">
        <div class="card mt-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Employee Details - {{ $employee->full_name }}</h4>
                <div class="d-flex gap-2">
                    <a href="{{ panelRoute('payroll-employees.edit', $employee->id) }}" class="btn btn-primary" style="background: linear-gradient(135deg, #1f618d, #2980b9); border: none; padding: 8px 18px; font-weight: bold; color: #fff;">
                        <i class="mdi mdi-pencil"></i> Edit
                    </a>
                    <a href="{{ panelRoute('payroll-employees.index') }}" class="btn btn-secondary" style="background-color: #6c757d; border: none; padding: 8px 18px; font-weight: bold; color: #fff; text-decoration: none;">
                        <i class="mdi mdi-arrow-left"></i> Back to List
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <!-- Basic Information -->
                    <div class="col-md-6">
                        <h5 class="mb-3">Basic Information</h5>
                        <table class="table table-bordered">
                            <tr>
                                <th width="40%">Employee Code</th>
                                <td>{{ $employee->employee_code }}</td>
                            </tr>
                            <tr>
                                <th>Full Name</th>
                                <td>{{ $employee->full_name }}</td>
                            </tr>
                            <tr>
                                <th>Email</th>
                                <td>{{ $employee->email ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <th>Phone</th>
                                <td>{{ $employee->phone ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <th>Date of Birth</th>
                                <td>{{ $employee->date_of_birth ? $employee->date_of_birth->format('Y-m-d') : 'N/A' }}</td>
                            </tr>
                            <tr>
                                <th>Status</th>
                                <td>
                                    @if($employee->status == 'active')
                                        <span class="badge bg-success">Active</span>
                                    @elseif($employee->status == 'inactive')
                                        <span class="badge bg-warning">Inactive</span>
                                    @else
                                        <span class="badge bg-danger">Resigned</span>
                                    @endif
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- Employment Information -->
                    <div class="col-md-6">
                        <h5 class="mb-3">Employment Information</h5>
                        <table class="table table-bordered">
                            <tr>
                                <th width="40%">Company</th>
                                <td>{{ $employee->company->name ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <th>Position</th>
                                <td>{{ $employee->position }}</td>
                            </tr>
                            <tr>
                                <th>Department</th>
                                <td>{{ $employee->dept->name ?? $employee->department ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <th>Sub-Department</th>
                                <td>{{ $employee->subDepartment->name ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <th>Hire Date</th>
                                <td>{{ $employee->date_hired ? $employee->date_hired->format('Y-m-d') : 'N/A' }}</td>
                            </tr>
                            <tr>
                                <th>Biometric ID</th>
                                <td>{{ $employee->biometric_id ?? 'N/A' }}</td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="row mt-4">
                    <!-- Salary Rates -->
                    <div class="col-md-6">
                        <h5 class="mb-3">Salary Rates</h5>
                        @if(auth()->user()->role === 'hr' || auth()->user()->role_id === \App\Models\Role::HR)
                            <div class="alert alert-secondary py-3 text-center">
                                <i class="fa fa-lock me-1"></i> Salary rates restricted
                            </div>
                        @else
                            <table class="table table-bordered">
                                <tr>
                                    <th width="40%">Monthly Rate</th>
                                    <td>{{ $employee->monthly_rate ? '₱ ' . number_format($employee->monthly_rate, 2) : 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <th>Daily Rate</th>
                                    <td>{{ $employee->daily_rate ? '₱ ' . number_format($employee->daily_rate, 2) : 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <th>Hourly Rate</th>
                                    <td>{{ $employee->hourly_rate ? '₱ ' . number_format($employee->hourly_rate, 2) : 'N/A' }}</td>
                                </tr>
                            </table>
                        @endif
                    </div>

                    <!-- Government IDs -->
                    <div class="col-md-6">
                        <h5 class="mb-3">Government IDs</h5>
                        <table class="table table-bordered">
                            <tr>
                                <th width="40%">SSS Number</th>
                                <td>{{ $employee->sss_number ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <th>PhilHealth Number</th>
                                <td>{{ $employee->philhealth_number ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <th>Pag-IBIG Number</th>
                                <td>{{ $employee->pagibig_number ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <th>TIN Number</th>
                                <td>{{ $employee->tin_number ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <th>Tax Code</th>
                                <td>{{ $employee->tax_code ? ($taxCodes[$employee->tax_code] ?? $employee->tax_code) : 'N/A' }}</td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-md-6">
                        <h5 class="mb-3">Payroll Banking</h5>
                        <table class="table table-bordered">
                            <tr>
                                <th width="40%">Bank Account (AcctNum)</th>
                                <td>{{ $employee->acct_num ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <th>ATM Number (ATMNum)</th>
                                <td>{{ $employee->atm_num ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <th>Remarks</th>
                                <td>{{ $employee->remarks ?? 'N/A' }}</td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- Employee Loans -->
                <div class="row mt-4">
                    <div class="col-12">
                        <h5 class="mb-3">Loans (Loan Monitor)</h5>
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>Loan Type</th>
                                    <th>Principal Amount</th>
                                    <th>Amortization/Deduction Rate</th>
                                    <th>Current Balance</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($employee->empLoans as $loan)
                                    <tr>
                                        <td>{{ $loan->loanType->name ?? 'Company Loan' }}</td>
                                        <td>₱ {{ number_format($loan->principal_amount, 2) }}</td>
                                        <td>₱ {{ number_format($loan->installment_amount, 2) }}</td>
                                        <td>₱ {{ number_format($loan->balance, 2) }}</td>
                                        <td>
                                            @if($loan->status == 'active')
                                                <span class="badge bg-success">Active</span>
                                            @else
                                                <span class="badge bg-info">Paid</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center">No loans recorded yet</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
