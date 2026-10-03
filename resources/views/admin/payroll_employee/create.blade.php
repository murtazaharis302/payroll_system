@extends('admin.layouts.Master')
@section('title') Create Payroll Employee @endsection
@section('content')
    <div class="col-md-12">
        <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
            <div>
                <h4 class="mb-0 text-dark font-weight-bold">Create Payroll Employee</h4>
                <small class="text-muted">Onboard standard employee details with multi-step tabbed workflow</small>
            </div>
            <a href="{{ panelRoute('payroll-employees.index') }}" class="btn btn-secondary" style="background-color: #6c757d; border: none; padding: 8px 18px; font-weight: bold; color: #fff;">
                <i class="mdi mdi-arrow-left"></i> Back to Directory
            </a>
        </div>

        <form action="{{ panelRoute('payroll-employees.store') }}" method="POST" id="payrollEmployeeCreateForm">
            @csrf
            @include('admin.payroll_employee.form')
        </form>
    </div>
@endsection

@section('scripts')
<script>
function autoFillFromUser(selectElement) {
    const selectedOption = selectElement.options[selectElement.selectedIndex];
    if (!selectedOption || !selectedOption.value) return;

    const firstName = selectedOption.getAttribute('data-first-name');
    const lastName = selectedOption.getAttribute('data-last-name');
    const email = selectedOption.getAttribute('data-email');
    const phone = selectedOption.getAttribute('data-phone');
    const code = selectedOption.getAttribute('data-code');
    const position = selectedOption.getAttribute('data-position');

    if (firstName && document.getElementById('first_name')) document.getElementById('first_name').value = firstName;
    if (lastName && document.getElementById('last_name')) document.getElementById('last_name').value = lastName;
    if (email && document.getElementById('email')) document.getElementById('email').value = email;
    if (phone && document.getElementById('phone')) document.getElementById('phone').value = phone;
    if (code && document.getElementById('employee_code')) document.getElementById('employee_code').value = code;
    if (position && document.getElementById('position')) document.getElementById('position').value = position;
}
</script>
@include('admin.payroll_employee._dept_ajax_scripts', [
    'initialDeptId' => old('dept_id'),
    'initialSubDeptId' => old('sub_dept_id'),
])
@endsection
