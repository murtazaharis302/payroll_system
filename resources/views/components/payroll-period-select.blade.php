@props([
    'name' => 'payroll_period_id',
    'id' => null,
    'selected' => null,
    'value' => null,
    'placeholder' => '-- Select Payroll Period --',
    'required' => false,
    'multiple' => false,
    'class' => '',
    'companyId' => null,
])

@php
    $elementId = $id ?? ('payroll_period_select_' . \Illuminate\Support\Str::random(8));
    $compCid = $companyId ?? session('company_id') ?? getActiveCompanyId();
    $selectedValue = $selected ?? $value;

    $query = \App\Models\PayrollPeriod::orderBy('period_end', 'desc')->orderBy('period_start', 'desc');
    if ($compCid) {
        $query->where('company_id', $compCid);
    }
    $periods = $query->get();

    // Default selection: automatically set to the most recent payroll period if no selection provided
    $latestPeriod = $periods->first();
    $defaultSelectedId = $selectedValue ?? ($latestPeriod?->id);

    // Group options by Year in descending order
    $groupedByYear = $periods->groupBy(function ($p) {
        $date = $p->period_end ?? $p->period_start;
        return $date ? \Carbon\Carbon::parse($date)->format('Y') : 'Other';
    })->sortKeysDesc();

    $selectName = $name;
    if ($multiple && !str_ends_with($selectName, '[]')) {
        $selectName .= '[]';
    }
@endphp

<div class="payroll-period-select-wrapper w-100">
    <select 
        name="{{ $selectName }}" 
        id="{{ $elementId }}" 
        class="form-select form-select-sm select2-payroll-period {{ $class }}"
        data-period-search="true"
        data-placeholder="{{ $placeholder }}"
        {{ $multiple ? 'multiple' : '' }}
        {{ $required ? 'required' : '' }}
        {{ $attributes }}
    >
        @if(!$multiple)
            <option value="">{{ $placeholder }}</option>
        @endif
        
        @foreach($groupedByYear as $year => $yearPeriods)
            <optgroup label="{{ $year }}">
                @foreach($yearPeriods as $p)
                    @php
                        $startDate = $p->period_start ? \Carbon\Carbon::parse($p->period_start) : null;
                        $endDate   = $p->period_end ? \Carbon\Carbon::parse($p->period_end) : null;

                        $startFmt = $startDate ? $startDate->format('F d, Y') : '';
                        $endFmt   = $endDate ? $endDate->format('F d, Y') : '';
                        
                        $optionText = $p->period_name . ($startFmt ? " ({$startFmt} - {$endFmt})" : '');
                        
                        $isSelected = false;
                        if (is_array($defaultSelectedId) || $defaultSelectedId instanceof \Illuminate\Support\Collection) {
                            $isSelected = in_array((string)$p->id, array_map('strval', is_array($defaultSelectedId) ? $defaultSelectedId : $defaultSelectedId->toArray()));
                        } else {
                            $isSelected = (string)$p->id === (string)$defaultSelectedId;
                        }

                        $searchKeywords = strtolower(implode(' ', array_filter([
                            $p->period_name,
                            $year,
                            $startDate ? $startDate->format('F f M d j Y') : '',
                            $endDate ? $endDate->format('F f M d j Y') : '',
                        ])));
                    @endphp
                    <option 
                        value="{{ $p->id }}" 
                        data-search="{{ $searchKeywords }}"
                        {{ $isSelected ? 'selected' : '' }}
                    >
                        {{ $optionText }}
                    </option>
                @endforeach
            </optgroup>
        @endforeach
    </select>
</div>
