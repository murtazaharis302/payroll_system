@extends('admin.layouts.Master')
@section('title') Payroll Employees @endsection
@section('content')
    <div class="col-12">
        <div class="card mt-4">
            <div class="card-header">
                <h4>Payroll Employees</h4>
                <div class="float-end d-flex gap-2">
                    <a href="{{ panelRoute('payroll-employees.company-master') }}" class="btn btn-primary" style="background-color: #1f618d; border: none; color: white">
                        <i class="mdi mdi-view-dashboard-variant me-1"></i> Company Master Quick-Entry
                    </a>
                    @if(can('payroll_employees', 'edit') || in_array(auth()->user()->role, ['admin', 'company', 'hr']))
                        <a href="{{ panelRoute('payroll-employees.create') }}" class="btn" style="background-color: #f06115; color: white">Create Payroll Employee</a>
                    @endif
                </div>
            </div>
            <div class="card-body">
                <style>
                    #myTable th, #myTable td {
                        vertical-align: middle !important;
                        white-space: nowrap !important;
                    }
                </style>
                <div class="table-responsive">
                    <table class="table table-striped align-middle" id="myTable">
                    <thead>
                    <tr>
                        <th scope="col">Employee Code</th>
                        <th scope="col">Name</th>
                        <th scope="col">Position</th>
                        <th scope="col">Department</th>
                        <th scope="col">Company</th>
                        <th scope="col">Hire Date</th>
                        <th scope="col">Status</th>
                        <th scope="col">Action</th>
                    </tr>
                    </thead>
                    <tbody>
                        @if(isset($employees))
                            @foreach ($employees as $employee)
                                <tr>
                                    <td>{{ $employee->employee_code }}</td>
                                    <td>{{ $employee->full_name }}</td>
                                    <td>{{ $employee->position }}</td>
                                    <td>{{ $employee->department ?? 'N/A' }}</td>
                                    <td>{{ $employee->company->name ?? 'N/A' }}</td>
                                    <td>{{ $employee->date_hired ? $employee->date_hired->format('Y-m-d') : 'N/A' }}</td>
                                     <td>
                                        @if($employee->status == 'active')
                                            <span class="badge bg-success">Active</span>
                                        @elseif($employee->status == 'pending_approval')
                                            <span class="badge bg-warning">Pending Approval</span>
                                        @elseif($employee->status == 'inactive')
                                            <span class="badge bg-secondary">Inactive</span>
                                        @else
                                            <span class="badge bg-danger">Resigned</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center" style="gap: 12px; min-width: 80px;">
                                            <a href="{{ panelRoute('payroll-employees.show', $employee->id) }}" class="text-primary" title="View" style="text-decoration: none;">
                                                <i class="fa-solid fa-eye" style="font-size: 14px;"></i>
                                            </a>
                                            @if($employee->status == 'pending_approval' && in_array(auth()->user()->role, ['admin', 'company']))
                                                <form action="{{ panelRoute('payroll-employees.approve', $employee->id) }}" method="POST" style="display:inline; margin: 0;">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-success py-0 px-1" title="Approve Employee">
                                                        <i class="fa-solid fa-check" style="font-size: 12px;"></i> Approve
                                                    </button>
                                                </form>
                                            @endif
                                            @if(can('payroll_employees', 'edit') || in_array(auth()->user()->role, ['admin', 'company', 'hr']))
                                                <a href="{{ panelRoute('payroll-employees.edit', $employee->id) }}" class="text-warning" title="Edit" style="text-decoration: none;">
                                                    <i class="fa-solid fa-pen-to-square" style="font-size: 14px;"></i>
                                                </a>
                                                <form action="{{ panelRoute('payroll-employees.destroy', $employee->id) }}" method="POST" style="display:inline; margin: 0;" class="btn-delete-form">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-link p-0 text-danger btn-delete" title="Delete" style="border: none; background: none; line-height: 1;">
                                                        <i class="fa-sharp fa-solid fa-trash" style="font-size: 14px;"></i>
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>    
                                </tr>
                            @endforeach
                        @endif
                    </tbody>
                </table>
                </div>
                {{ $employees->links() }}
            </div> 
        </div> 
    </div>
    <script>
        var translations = {
            search: "{{ __('messages.search') }}"
        };
    </script>
@endsection
