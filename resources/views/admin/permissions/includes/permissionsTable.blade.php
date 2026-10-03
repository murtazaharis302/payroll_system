@php
    $storedPermissions = [];
    foreach ($userPermissions as $perm) {
        $storedPermissions[$perm->module][$perm->key] = $perm->value;
    }

    $modules = getPermissionModules($user_role ?? null, $ownerRole ?? null);
    $actions = permissionActions();
    $viewOnlyModules = permissionViewOnlyModules();
@endphp

<thead class="thead-dark">
    <tr>
        <th>{{ __('messages.module') }}</th>
        @foreach ($actions as $action)
            <th>{{ __('messages.' . $action) }}</th>
        @endforeach
        <th>{{ __('messages.check') }}/{{ __('messages.uncheck') }} {{ __('messages.module') }}</th>
    </tr>
</thead>

<tbody>
    @foreach ($modules as $key => $label)
        <tr data-module="{{ $key }}">
            <td class="text-start">{{ $label }}</td>

            @foreach ($actions as $action)
                @if ($action === 'edit' && in_array($key, $viewOnlyModules))
                    <td></td>
                @else
                    <td>
                        <input type="hidden" name="permissions[{{ $key }}][{{ $action }}]" value="0">
                        <input type="checkbox"
                            class="permission-checkbox"
                            data-row="{{ $key }}"
                            name="permissions[{{ $key }}][{{ $action }}]"
                            value="1"
                            {{ (isset($storedPermissions[$key][$action]) && $storedPermissions[$key][$action] == 1) ? 'checked' : '' }}>
                    </td>
                @endif
            @endforeach

            <td>
                <input type="checkbox" class="row-checkbox" data-row="{{ $key }}">
            </td>
        </tr>
    @endforeach
</tbody>
