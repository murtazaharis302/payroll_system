@extends('admin.layouts.Master')
@section('title') {{__('messages.create') }}@endsection
@section('content')
    @if(auth()->user()->role == 'admin')
        <div class="col-6">
            <div class="card mt-4">
                <div class="card-header">
                    <h4>{{__('messages.register') }} / {{__('messages.create') }} {{__('messages.admin') }}</h4>
                    <a href=" {{ panelRoute('users') }}" class="btn btn-primary float-end" >{{__('messages.back') }}</a>
                </div>
                <div class="card-body">
                    <form action="{{ panelRoute('storeUser') }}" method="POST">
                        @csrf
                        <div class="form-group mb-3">
                            <label for="name">{{__('messages.name') }}</label>
                            <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}">
                            @error('name')
                                <span class="text-danger small">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="form-group mb-3">
                            <label for="email">{{__('messages.email') }}</label>
                            <input type="email" id="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}">
                            @error('email')
                                <span class="text-danger small">{{ $message }}</span>
                            @enderror
                        </div>    
                        {{-- <div class="form-group mb-3">
                            <label for="role">{{__('messages.choose_role') }}</label>
                            <select id="role" class="form-control" name="role">
                                <option value="user">{{__('messages.user') }}</option>
                                <option value="admin">Admin</option>
                            </select>   
                        </div> --}}
                        <div class="form-group mb-3">
                            <label for="password">{{__('messages.password') }}</label>
                            <input id="password" type="password" class="form-control @error('password') is-invalid @enderror" name="password" required autocomplete="new-password">
                            @error('password')
                                <span class="text-danger small">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="form-group mb-3">
                                <label for="password-confirm" >{{__('messages.confirm_password') }}</label>
                                <input id="password-confirm" type="password" class="form-control" name="password_confirmation" required autocomplete="new-password">
                        </div>
                            <div class="row mb-0">
                        <div class="form-group mb-3">
                            <button type="submit" class="btn btn-primary">{{__('messages.submit') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @else
        <div class="alert alert-danger" role="alert">
            Access Denied
        </div>
    @endif  
@endsection


