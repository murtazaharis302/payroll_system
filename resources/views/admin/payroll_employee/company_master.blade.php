@extends('admin.layouts.Master')
@section('title') Company Master Quick-Entry @endsection
@section('content')
    @include('admin.payroll_employee._company_master_content')
    @include('admin.payroll_employee._company_master_styles')
@endsection
@section('scripts')
    @include('admin.payroll_employee._company_master_scripts')
    @include('admin.payroll_employee._unsaved_changes_protection', [
        'saveFunction' => 'saveQuickMasterData',
        'employeeLoadFunction' => 'loadEmployeeQuickDetails',
    ])
@endsection
