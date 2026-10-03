@extends('admin.layouts.Master')
@section('title') Edit User @endsection
@section('content')
    <div class="col">
        <div class="card mt-4">
            <div class="card-header">
                <h4>Edit User Account</h4>
                <a href=" {{ panelRoute('employee') }}" class="btn btn-primary float-end" >{{__('messages.back') }}</a>
            </div>
            <div class="card-body">
                <form action="{{ panelRoute('updateEmployee',$employee->e_user_id) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="row">
                        <div class="col-6">
                            <div class="form-group mb-3">
                                <label for="name">{{ __('messages.name') }} <span class="text-danger">*</span></label>
                                <input type="text" name="name" id="name" class="form-control" value="{{ $employee->employee?->name ?? $employee->name }}" required>
                            </div>
                            <div class="form-group mb-3">
                                <label for="email">{{ __('messages.email') }} <span class="text-danger">*</span></label>
                                <input type="email" name="email" id="email" class="form-control" value="{{ $employee->employee?->email ?? $employee->email }}" required>
                            </div>
                            <div class="form-group mb-3">
                                <label for="phone">{{ __('messages.phone') }} <span class="text-danger">*</span></label>
                                <input type="text" name="phone" id="phone" class="form-control" value="{{ $employee->employee?->phone ?? $employee->phone }}" required>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group mb-3">
                                <label for="role">Role <span class="text-danger">*</span></label>
                                <select name="role" id="role" class="form-control" required>
                                    <option value="">Select Role</option>
                                    <option value="hr" {{ ($employee->employee?->role ?? $employee->role) == 'hr' ? 'selected' : '' }}>HR</option>
                                    <option value="employee" {{ ($employee->employee?->role ?? $employee->role) == 'employee' ? 'selected' : '' }}>Employee</option>
                                </select>
                            </div>
                            <div class="form-group mb-3">
                                <label for="id_number">{{ __('messages.id_number') }}</label>
                                <input type="text" class="form-control" value="{{ $employee->id_number ?? ('USR-' . str_pad($employee->id, 4, '0', STR_PAD_LEFT)) }}" readonly disabled>
                            </div>
                            <div class="form-group mb-3">
                                <label for="address">{{ __('messages.address') }} <span class="text-danger">*</span></label>
                                <input type="text" name="address" id="address" class="form-control" value="{{ $employee->address }}" required>
                            </div>
                            <div class="form-group mb-3">
                                <label for="age">{{ __('messages.age') }}</label>
                                <input type="text" name="age" id="age" class="form-control" value="{{ $employee->age }}">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="form-group mb-3 float-end">
                            <button type="submit" class="btn btn-primary">{{ __('messages.submit') }}</button>
                        </div>
                    </div>
                </form>
            </div> 
        </div> 
    </div>
@endsection
