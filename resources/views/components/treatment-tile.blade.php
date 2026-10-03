@props([
    'treatment' => [],
    'index' => null,
])

@php
    $title = $treatment['title'] ?? '';
    $tagline = $treatment['tagline'] ?? '';
    $description = $treatment['description'] ?? '';
    $highlights = $treatment['highlights'] ?? [];
    $id = $treatment['id'] ?? '';

    $categoryLabels = [
        'preventative' => 'Diagnostic & Preventative',
        'cosmetic' => 'Aesthetic Dentistry',
        'restorative' => 'Biomimetic Restoration',
        'pediatric' => 'Pediatric & Family',
    ];

    $label = $categoryLabels[$id] ?? 'Clinical Care';
@endphp

<article class="group relative flex flex-col justify-between rounded-2xl bg-stone-warm-100/90 border border-stone-warm-200 p-7 sm:p-8 hover:border-stone-warm-400 hover:-translate-y-1 hover:bg-stone-warm-100 transition-all duration-200 focus-within:ring-2 focus-within:ring-charcoal-900" data-motion="card" data-motion-interactive data-testid="treatment-tile-{{ $id }}">
    <!-- Card Header -->
    <div>
        <!-- Category & Architectural Index -->
        <div class="flex items-center justify-between gap-2 mb-5">
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-stone-warm-200/80 border border-stone-warm-300 text-[11px] font-semibold uppercase tracking-wider text-stone-warm-800">
                <span class="w-1.5 h-1.5 rounded-full bg-brass-500"></span>
                {{ $label }}
            </span>
            @if($index)
                <span class="text-xs font-mono font-medium text-stone-warm-500">0{{ $index }}</span>
            @endif
        </div>

        <!-- Title & Tagline -->
        <h3 class="font-serif text-xl sm:text-2xl font-bold text-charcoal-900 group-hover:text-charcoal-950 transition-colors leading-snug">
            {{ $title }}
        </h3>
        <p class="text-xs sm:text-sm font-medium text-stone-warm-700 mt-2.5 leading-snug">
            {{ $tagline }}
        </p>

        <!-- Description -->
        <p class="text-xs sm:text-sm text-stone-warm-600 mt-4 leading-relaxed">
            {{ $description }}
        </p>
    </div>

    <!-- Highlights Section -->
    <div class="mt-8 pt-5 border-t border-stone-warm-200/90">
        <span class="text-[11px] font-semibold uppercase tracking-wider text-charcoal-900 block mb-3">
            Highlighted Procedures
        </span>
        <ul class="space-y-2.5" role="list">
            @foreach($highlights as $highlight)
                <li class="flex items-start gap-2.5 text-xs text-stone-warm-800">
                    <span class="w-4 h-4 rounded-full bg-stone-warm-200 border border-stone-warm-300 flex items-center justify-center shrink-0 mt-0.5" aria-hidden="true">
                        <svg class="w-2.5 h-2.5 text-charcoal-800" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M4.5 12.75l6 6 9-13.5" />
                        </svg>
                    </span>
                    <span class="font-medium">{{ $highlight }}</span>
                </li>
            @endforeach
        </ul>
    </div>
</article>
