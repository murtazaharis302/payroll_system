@extends('admin.layouts.Master')
@section('title') Generate Payroll Drafts @endsection
@section('content')
    @include('admin.payroll-drafts._create_generation_options')
    @include('admin.payroll-drafts._create_deduction_options')
@endsection
