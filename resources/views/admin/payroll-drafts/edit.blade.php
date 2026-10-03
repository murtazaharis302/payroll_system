@extends('admin.layouts.Master')
@section('title') Payroll Input Quick-Entry - Edit Draft @endsection
@section('content')
    @include('admin.payroll-drafts._edit_form')
    @include('admin.payroll-drafts._edit_modals')
@endsection
@section('scripts')
    @include('admin.payroll-drafts._edit_scripts')
    @include('admin.payroll-drafts._remove_attendance_grid')
@endsection
