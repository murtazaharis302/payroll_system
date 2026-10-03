@extends('admin.layouts.Master')
@section('title') Loan Monitor @endsection
@section('content')
    @include('admin.loan_monitor._monitor_content')
    @include('admin.loan_monitor._monitor_modals')
@endsection
@section('scripts')
    @include('admin.loan_monitor._monitor_scripts')
@endsection
