@extends('admin.layouts.Master')
@section('title') Review Payroll Draft @endsection
@section('header-additional')
    @include('admin.payroll-approvals._review_styles')
@endsection
@section('content')
    @include('admin.payroll-approvals._review_content')
    @include('admin.payroll-approvals._review_modals')
@endsection
@section('scripts')
    @include('admin.payroll-approvals._review_scripts')
@endsection
