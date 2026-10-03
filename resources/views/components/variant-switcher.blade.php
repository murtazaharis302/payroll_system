@props(['activeVariant' => 'a', 'activePalette' => 'warm-stone', 'activeTypeface' => 'source-work'])

@php
    $currentVariant = in_array($activeVariant, ['a', 'b'], true) ? $activeVariant : 'a';
    $palettes = config('design-preferences.palettes', []);
    $typefaces = config('design-preferences.typefaces', []);
    $urlFor = fn (string $key, string $value): string => request()->fullUrlWithQuery([$key => $value]);
@endphp

<div class="fixed bottom-3 right-3 z-50 max-w-[calc(100vw-1.5rem)] sm:bottom-6 sm:right-6" data-testid="variant-switcher">
    <details class="group relative">
        <summary class="flex min-h-11 cursor-pointer list-none items-center gap-2 rounded-xl bg-charcoal-900 px-3 text-xs font-semibold text-stone-warm-50 shadow-lg transition-colors motion-reduce:transition-none hover:bg-charcoal-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-charcoal-900 focus-visible:ring-offset-2" aria-label="Open design preferences">
            <span aria-hidden="true">Design</span>
            <span class="hidden text-stone-warm-300 sm:inline">{{ strtoupper($currentVariant) }} · {{ $palettes[$activePalette]['label'] ?? 'Warm Stone' }}</span>
        </summary>

        <div class="absolute bottom-full right-0 mb-2 w-[min(20rem,calc(100vw-1.5rem))] rounded-2xl border border-stone-warm-300 bg-stone-warm-50 p-4 text-charcoal-900 shadow-xl" aria-label="Design preferences">
            <div class="mb-3 flex items-center justify-between gap-3">
                <p class="text-sm font-semibold">Design preferences</p>
                <p class="text-xs text-stone-warm-600">Links preserve this page</p>
            </div>

            <fieldset class="border-t border-stone-warm-200 pt-3">
                <legend class="px-0 text-xs font-semibold uppercase tracking-wider text-stone-warm-600">Variant</legend>
                <div class="mt-2 grid grid-cols-2 gap-2">
                    @foreach (['a' => 'Expressive 2D', 'b' => 'Calm Editorial'] as $value => $label)
                        <a href="{{ $urlFor('variant', $value) }}" @class([
                            'flex min-h-11 items-center justify-center rounded-lg border px-3 text-center text-xs font-semibold transition-colors motion-reduce:transition-none focus:outline-none focus-visible:ring-2 focus-visible:ring-charcoal-900 focus-visible:ring-offset-2',
                            'border-charcoal-900 bg-charcoal-900 text-stone-warm-50' => $currentVariant === $value,
                            'border-stone-warm-300 hover:bg-stone-warm-100' => $currentVariant !== $value,
                        ]) aria-current="{{ $currentVariant === $value ? 'true' : 'false' }}" data-testid="switcher-variant-{{ $value }}">{{ $label }}</a>
                    @endforeach
                </div>
            </fieldset>

            <fieldset class="mt-4 border-t border-stone-warm-200 pt-3">
                <legend class="px-0 text-xs font-semibold uppercase tracking-wider text-stone-warm-600">Palette</legend>
                <div class="mt-2 grid grid-cols-2 gap-2">
                    @foreach ($palettes as $value => $palette)
                        <a href="{{ $urlFor('palette', $value) }}" @class([
                            'flex min-h-11 items-center rounded-lg border px-3 text-xs font-medium transition-colors motion-reduce:transition-none focus:outline-none focus-visible:ring-2 focus-visible:ring-charcoal-900 focus-visible:ring-offset-2',
                            'border-charcoal-900 bg-stone-warm-100 font-semibold' => $activePalette === $value,
                            'border-stone-warm-300 hover:bg-stone-warm-100' => $activePalette !== $value,
                        ]) aria-current="{{ $activePalette === $value ? 'true' : 'false' }}" data-testid="switcher-palette-{{ $value }}">{{ $palette['label'] }}</a>
                    @endforeach
                </div>
            </fieldset>

            <fieldset class="mt-4 border-t border-stone-warm-200 pt-3">
                <legend class="px-0 text-xs font-semibold uppercase tracking-wider text-stone-warm-600">Typography</legend>
                <div class="mt-2 grid gap-2">
                    @foreach ($typefaces as $value => $typeface)
                        <a href="{{ $urlFor('typeface', $value) }}" @class([
                            'flex min-h-11 items-center rounded-lg border px-3 text-xs font-medium transition-colors motion-reduce:transition-none focus:outline-none focus-visible:ring-2 focus-visible:ring-charcoal-900 focus-visible:ring-offset-2',
                            'border-charcoal-900 bg-stone-warm-100 font-semibold' => $activeTypeface === $value,
                            'border-stone-warm-300 hover:bg-stone-warm-100' => $activeTypeface !== $value,
                        ]) aria-current="{{ $activeTypeface === $value ? 'true' : 'false' }}" data-testid="switcher-typeface-{{ $value }}">{{ $typeface['label'] }}</a>
                    @endforeach
                </div>
            </fieldset>
        </div>
    </details>
</div>
