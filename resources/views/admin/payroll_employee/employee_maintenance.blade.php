@extends('admin.layouts.Master')
@section('title') Employee Record Management @endsection
@section('content')
    @include('admin.payroll_employee._maintenance_content')
    @include('admin.payroll_employee._maintenance_styles')
@endsection
@section('scripts')
    @include('admin.payroll_employee._maintenance_scripts')
    @include('admin.payroll_employee._unsaved_changes_protection', [
        'saveFunction' => 'saveEmployeeRecord',
        'employeeLoadFunction' => 'loadEmployeeRecordDetails',
    ])
@endsection
