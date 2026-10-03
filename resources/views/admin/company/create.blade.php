
@extends('admin.layouts.Master')
@section('title') {{ __('messages.create') }} {{ __('messages.company') }} @endsection
@section('content')
    @if(auth()->user()->role == 'admin')
        <div class="col">
            <div class="card mt-4">
                <div class="card-header">
                    <h4>{{ __('messages.create') }} {{ __('messages.company') }}</h4>
                </div>
                <div class="card-body">
                    <form action="{{ panelRoute('storeCompany') }}" method="POST">
                        @csrf
                        <div class="row">
                            <div class="col-6">
                                <h5>{{ __('messages.personal') }} {{ __('messages.information') }}</h5>
                                <div class="form-group mb-3">
                                    <label for="name">{{ __('messages.user') }} {{ __('messages.name') }}</label>
                                    <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}">
                                    @error('name')
                                        <span class="text-danger small">{{ $message }}</span>
                                    @enderror
                                </div>
                                <div class="form-group mb-3">
                                    <label for="email">{{ __('messages.personal') }} {{ __('messages.email') }}</label>
                                    <input type="email" id="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}">
                                    @error('email')
                                        <span class="text-danger small">{{ $message }}</span>
                                    @enderror
                                </div>
                                <div class="form-group mb-3">
                                    <label for="password">{{ __('messages.password') }}</label>
                                    <input id="password" type="password" class="form-control @error('password') is-invalid @enderror" name="password" autocomplete="new-password">
                                    @error('password')
                                        <span class="text-danger small">{{ $message }}</span>
                                    @enderror
                                </div>
                                <div class="form-group mb-3">
                                    <label for="password-confirm">{{ __('messages.confirm_password') }}</label>
                                    <input id="password-confirm" type="password" class="form-control" name="password_confirmation" autocomplete="new-password">
                                </div>
                            </div>
                            <div class="col-6">
                                <h5>{{ __('messages.company') }} {{ __('messages.information') }}</h5>
                                <div class="form-group mb-3">
                                    <label for="companyName">{{ __('messages.company') }} {{ __('messages.name') }}</label>
                                    <input type="text" id="companyName" name="companyName" class="form-control @error('companyName') is-invalid @enderror" value="{{ old('companyName') }}">
                                    @error('companyName')
                                        <span class="text-danger small">{{ $message }}</span>
                                    @enderror
                                </div>
                                <div class="form-group mb-3">
                                    <label for="company_code">Company Code <span class="text-muted font-11">(Optional - Auto-assigned if left empty)</span></label>
                                    <input type="text" id="company_code" name="company_code" class="form-control @error('company_code') is-invalid @enderror" value="{{ old('company_code') }}" placeholder="e.g. COMP-0001">
                                    @error('company_code')
                                        <span class="text-danger small">{{ $message }}</span>
                                    @enderror
                                </div>
                                <div class="form-group mb-3">
                                    <label for="companyEmail">{{ __('messages.company') }} {{ __('messages.email') }}</label>
                                    <input type="email" id="companyEmail" name="companyEmail" class="form-control @error('companyEmail') is-invalid @enderror" value="{{ old('companyEmail') }}">
                                    @error('companyEmail')
                                        <span class="text-danger small">{{ $message }}</span>
                                    @enderror
                                </div>
                                <div class="form-group mb-3">
                                    <label for="phone">{{ __('messages.company') }} {{ __('messages.phone') }}</label>
                                    <input type="text" id="phone" name="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone') }}">
                                    @error('phone')
                                        <span class="text-danger small">{{ $message }}</span>
                                    @enderror
                                </div>
                                <div class="form-group mb-3">
                                    <label for="website">{{ __('messages.company') }} {{ __('messages.website') }}</label>
                                    <input type="text" id="website" name="website" class="form-control @error('website') is-invalid @enderror" value="{{ old('website') }}">
                                    @error('website')
                                        <span class="text-danger small">{{ $message }}</span>
                                    @enderror
                                </div>
                                <div class="form-group mb-3">
                                    <label for="address">Corporate Office Address</label>
                                    <textarea name="address" id="address" class="form-control @error('address') is-invalid @enderror" rows="3" placeholder="Enter Corporate Office Address (displayed on reports & transmittals)">{{ old('address') }}</textarea>
                                    @error('address')
                                        <span class="text-danger small">{{ $message }}</span>
                                    @enderror
                                </div>
                                <div class="form-group mb-3 float-end">
                                    <button type="submit" class="btn btn-primary">{{ __('messages.submit') }}</button>
                                </div>
                            </div>
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


