@extends('admin.layouts.Master')
@section('title')
    {{ __('messages.dashboard') }}
@endsection
@section('content')
    @if (can('dashboard', 'view'))
        @include('admin._dashboard_overview')
        @include('admin._dashboard_charts')
    @else
        <div class="alert alert-danger" role="alert">Access Denied</div>
    @endif
@endsection
