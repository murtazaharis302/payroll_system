<script src="{{asset('/')}}assets/Js/admin/permissions.js"></script>
<div class="form-group">
    <label for="name">{{ __('messages.employees') }}</label>
    <select id="getUserName" name="name" class="form-control">
        <option value="" disabled selected>{{ __('messages.select') }}</option>
        @foreach ($usersList as $user)
            <option value={{ $user->e_user_id }}>{{ $user->employee->name }}</option>
        @endforeach
    </select>
</div>