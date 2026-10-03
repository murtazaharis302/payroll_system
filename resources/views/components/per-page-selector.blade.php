@props([
    'perPage' => request('per_page', 50),
    'options' => [20, 50, 100, 200]
])

<div class="d-inline-flex align-items-center gap-2 ms-2">
    <span class="small text-muted font-weight-bold" style="font-size: 12px;">Display:</span>
    <select class="form-select form-select-sm font-weight-bold" style="width: auto; padding: 3px 10px; font-size: 12px;" onchange="let url = new URL(window.location.href); url.searchParams.set('per_page', this.value); url.searchParams.set('page', 1); window.location.href = url.href;">
        @foreach($options as $opt)
            <option value="{{ $opt }}" {{ (int)$perPage === (int)$opt ? 'selected' : '' }}>{{ $opt }} records</option>
        @endforeach
    </select>
</div>
