@props([
    'name' => 'employee_id',
    'id' => null,
    'value' => null,
    'selected' => null,
    'placeholder' => '-- Search / Select Employee --',
    'required' => false,
    'multiple' => false,
    'class' => '',
    'companyId' => null,
    'employees' => null,
])

@php
    $elementId = $id ?? ('employee_select_' . \Illuminate\Support\Str::random(8));
    $compCid = $companyId ?? session('company_id') ?? getActiveCompanyId();
    $selectedValue = $selected ?? $value;
    
    $selectedIds = [];
    if ($selectedValue) {
        $ids = is_array($selectedValue) || $selectedValue instanceof \Illuminate\Support\Collection
            ? $selectedValue
            : explode(',', (string) $selectedValue);
        $selectedIds = array_map('strval', array_filter($ids));
    }

    $optionsEmployees = collect();
    if ($employees && (is_array($employees) || $employees instanceof \Illuminate\Support\Collection)) {
        $optionsEmployees = collect($employees);
    } elseif (!empty($selectedIds)) {
        $optionsEmployees = \App\Models\EmployeeMaster::with('dept')
            ->whereIn('id', $selectedIds)
            ->get();
    }
    
    $selectName = $name;
    if ($multiple && !str_ends_with($selectName, '[]')) {
        $selectName .= '[]';
    }
@endphp

<div class="employee-select-wrapper w-100">
    <select 
        name="{{ $selectName }}" 
        id="{{ $elementId }}" 
        class="form-select form-select-sm select2-employee-search {{ $class }}"
        data-employee-search="true"
        data-placeholder="{{ $placeholder }}"
        data-company-id="{{ $compCid }}"
        data-search-url="{{ url('/api/employees/search') }}"
        {{ $multiple ? 'multiple' : '' }}
        {{ $required ? 'required' : '' }}
        {{ $attributes }}
    >
        @if(!$multiple)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach($optionsEmployees as $emp)
            @php
                $empCode = $emp->employee_code ?? ('EMP-' . $emp->id);
                $fullName = $emp->last_name . ', ' . $emp->first_name . ($emp->middle_name ? ' ' . substr($emp->middle_name, 0, 1) . '.' : '');
                $deptName = $emp->dept->name ?? $emp->department ?? 'No Department';
                $isSelected = in_array((string) $emp->id, $selectedIds, true);
            @endphp
            <option value="{{ $emp->id }}" {{ $isSelected ? 'selected' : '' }}>
                [{{ $empCode }}] {{ $fullName }} — {{ $deptName }}
            </option>
        @endforeach
    </select>
</div>
