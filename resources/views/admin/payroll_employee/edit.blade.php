@extends('admin.layouts.Master')
@section('title') Edit Payroll Employee @endsection
@section('content')
    <div class="col-md-12">
        <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
            <div>
                <h4 class="mb-0 text-dark font-weight-bold">Edit Payroll Employee - {{ $employee->full_name }}</h4>
                <small class="text-muted">Code: {{ $employee->employee_code }} | Position: {{ $employee->position ?? 'N/A' }}</small>
            </div>
            <a href="{{ panelRoute('payroll-employees.index') }}" class="btn btn-secondary" style="background-color: #6c757d; border: none; padding: 8px 18px; font-weight: bold; color: #fff;">
                <i class="mdi mdi-arrow-left"></i> Back to Directory
            </a>
        </div>

        <form action="{{ panelRoute('payroll-employees.update', $employee->id) }}" method="POST" id="payrollEmployeeEditForm">
            @csrf
            @method('PUT')
            @include('admin.payroll_employee.form', ['employee' => $employee])
        </form>
    </div>
@endsection

@section('scripts')
@include('admin.payroll_employee._dept_ajax_scripts', [
    'initialDeptId' => old('dept_id', $employee->dept_id),
    'initialSubDeptId' => old('sub_dept_id', $employee->sub_dept_id),
])
@endsection
