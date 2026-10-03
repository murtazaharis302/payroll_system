@extends('admin.layouts.Master')
@section('title') Users @endsection
@section('content')
    <div class="col-12">
        <div class="card mt-4">
            <div class="card-header">
                <h4>Users Management</h4>
                @if(in_array(auth()->user()->role, ['admin', 'company']) || can('employees','edit'))
                    <a href=" {{ panelRoute('createEmployee') }}" class="btn float-end" style="background-color: #f06115; color: white " ><i class="fa-solid fa-plus me-1"></i> Create User</a>
                @endif
            </div>
            <div class="card-body">
                <form method="GET" action="{{ panelRoute('employee') }}" class="row g-3 mb-4 align-items-end">
                    @if(auth()->user()->role === 'admin' && isset($companies) && $companies->count() > 0)
                        <div class="col-md-4">
                            <label for="company_id" class="form-label font-weight-bold">Filter by Company</label>
                            <select name="company_id" id="company_id" class="form-control">
                                <option value="">All Companies</option>
                                @foreach($companies as $company)
                                    <option value="{{ $company->id }}" {{ request('company_id') == $company->id ? 'selected' : '' }}>
                                        {{ $company->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div class="col-md-3">
                        <label for="role" class="form-label font-weight-bold">Filter by Role</label>
                        <select name="role" id="role" class="form-control">
                            <option value="">All Roles</option>
                            <option value="hr" {{ request('role') === 'hr' ? 'selected' : '' }}>HR Staff</option>
                            <option value="employee" {{ request('role') === 'employee' ? 'selected' : '' }}>Normal Employee</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100"><i class="fa-solid fa-filter me-1"></i> Filter</button>
                    </div>
                    @if(request()->filled('company_id') || request()->filled('role'))
                        <div class="col-md-2">
                            <a href="{{ panelRoute('employee') }}" class="btn btn-secondary w-100">Reset</a>
                        </div>
                    @endif
                </form>
                <div class="table-responsive">
                <table id="employees_table" class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>{{ __('messages.id') }}</th>
                            <th>{{ __('messages.name') }}</th>
                            <th>{{ __('messages.email') }}</th>
                            @if(in_array(auth()->user()->role, ['admin']) || auth()->user()->role_id === \App\Models\Role::SUPER_ADMIN)
                                <th>{{ __('messages.company') }}</th>
                            @endif
                            <th>Role</th>
                            <th>{{ __('messages.phone') }}</th>
                            <th>{{ __('messages.id_number') }}</th>
                            <th>{{ __('messages.age') }}</th>
                            <th>{{ __('messages.address') }}</th>
                            <th>{{ __('messages.action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if ($employees)
                            @foreach ($employees as $employee)
                                @php
                                    $legacy = $employee->employees;
                                    $master = $employee->payrollMaster;
                                    $empName = $employee->name ?? ($master ? $master->full_name : ($legacy ? $legacy->name : 'N/A'));
                                    $empEmail = $employee->email ?? ($master ? $master->email : ($legacy ? $legacy->email : 'N/A'));
                                    $phoneVal = $employee->phone ?? ($master ? $master->phone : ($legacy ? $legacy->phone : 'N/A'));
                                    $idVal = $master ? ($master->employee_code ?? 'N/A') : ($legacy ? ($legacy->id_number ?? 'N/A') : 'N/A');
                                    $ageVal = $legacy ? ($legacy->age ?? 'N/A') : 'N/A';
                                    $addressVal = $master ? ($master->address ?? 'N/A') : ($legacy ? ($legacy->address ?? 'N/A') : 'N/A');
                                    $compName = $employee->company ? $employee->company->name : ($legacy && $legacy->owner && $legacy->owner->company ? $legacy->owner->company->name : 'N/A');
                                    $roleDisplay = $employee->role ? ucfirst($employee->role) : ($legacy ? ucfirst($legacy->designation ?? 'Employee') : 'Employee');
                                @endphp
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ $empName }}</td>
                                    <td>{{ $empEmail }}</td>
                                    @if(in_array(auth()->user()->role, ['admin']) || auth()->user()->role_id === \App\Models\Role::SUPER_ADMIN)
                                        <td>{{ $compName }}</td>
                                    @endif
                                    <td> {{ $roleDisplay }}</td>
                                    <td> {{ $phoneVal }}</td>
                                    <td> {{ $idVal }}</td>
                                    <td> {{ $ageVal }}</td>
                                    <td> {{ $addressVal }}</td>
                                    <td>
                                        @if(in_array(auth()->user()->role, ['admin', 'company']) || auth()->user()->role_id === \App\Models\Role::SUPER_ADMIN || can('employees','edit'))
                                            <a href="{{ panelRoute('editEmployee',$employee->id) }}"><i class="fa-solid fa-pen-to-square "></i></a>
                                            <form action="{{ panelRoute('deleteEmployee',$employee->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-link p-0 text-danger border-0">
                                                    <i class="fa-sharp fa-solid fa-trash" style="color: red"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </td>    
                                </tr>
                            @endforeach
                        @endif
                    </tbody>

                </table>
                <div class="d-flex align-items-center justify-content-between flex-wrap mt-3">
                    <div>
                        {{ $employees->links() }}
                    </div>
                    <x-per-page-selector :perPage="request('per_page', 50)" />
                </div>
        </div> 
    </div>
    <script>
        var translations = {
            search: "{{ __('messages.search') }}"
        };
    </script>
@endsection
