@props([
    'review' => [],
])

@php
    $id = $review['id'] ?? '';
    $name = $review['patient_name'] ?? 'Patient Review';
    $rating = (int) ($review['rating'] ?? 5);
    $excerpt = $review['excerpt'] ?? '';
    $fullText = $review['full_text'] ?? $excerpt;
    $source = $review['source'] ?? 'Placeholder review — client approval required';
    $sourceUrl = $review['source_url'] ?? '';
    $date = $review['date'] ?? '';
    $treatment = $review['treatment'] ?? 'Clinical Care';
@endphp

<article
    class="group relative flex flex-col justify-between rounded-2xl bg-stone-warm-100/40 border border-stone-warm-200 p-6 sm:p-8 transition-colors duration-200 hover:bg-stone-warm-100/80 hover:border-stone-warm-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-charcoal-900 focus-within:ring-2 focus-within:ring-charcoal-900 cursor-pointer h-full select-none"
    data-review-card
    data-review-id="{{ $id }}"
    data-patient-name="{{ $name }}"
    data-rating="{{ $rating }}"
    data-treatment="{{ $treatment }}"
    data-date="{{ $date }}"
    data-source-url="{{ $sourceUrl }}"
    data-source-label="{{ $source }}"
    data-testid="variant-b-review-card-{{ $id }}"
    data-motion="review"
    data-motion-interactive
    tabindex="0"
    role="article"
    aria-label="Patient review by {{ $name }}"
>
    <details class="mt-4 text-sm text-stone-warm-700 leading-relaxed" data-review-fallback>
        <summary class="cursor-pointer text-xs font-mono text-stone-warm-600 hover:text-charcoal-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-charcoal-900 rounded-sm">
            Read complete review
        </summary>
        <div class="mt-3" data-full-text-content>{!! nl2br(e($fullText)) !!}</div>
    </details>

    <div>
        <!-- Editorial Header: Minimal Stars & Discipline -->
        <div class="flex items-center justify-between gap-3 mb-5 pb-3 border-b border-stone-warm-200/60">
            <x-shared.star-rating :rating="$rating" size="xs" />
            <span class="text-[11px] font-mono uppercase tracking-wider text-stone-warm-500">
                {{ $treatment }}
            </span>
        </div>

        <!-- Clamped Excerpt (locked card height) -->
        <p class="text-sm sm:text-base text-stone-warm-700 font-light leading-relaxed line-clamp-3">
            &ldquo;{{ $excerpt }}&rdquo;
        </p>
    </div>

    <!-- Editorial Card Footer / Attribution -->
    <div class="mt-8 pt-4 border-t border-stone-warm-200/80 flex items-end justify-between gap-2">
        <div>
            <h4 class="font-serif text-base font-light text-charcoal-900">
                {{ $name }}
            </h4>
            <span class="block text-[10px] font-semibold uppercase tracking-wider text-stone-warm-600">Placeholder review — client approval required</span>
            @if($date)
                <span class="text-xs font-mono text-stone-warm-500 block mt-0.5">
                    {{ $date }}
                </span>
            @endif
        </div>

        <div class="flex items-center gap-1">
            <button
                type="button"
                data-review-trigger
                class="min-h-[44px] min-w-[44px] p-2 text-xs font-mono text-stone-warm-600 hover:text-charcoal-900 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-charcoal-900 rounded"
                aria-label="Read complete review by {{ $name }}"
                data-motion-interactive
            >
                Read &rarr;
            </button>
            @if($sourceUrl)
                <a
                href="{{ $sourceUrl }}"
                target="_blank"
                rel="noopener noreferrer"
                class="min-h-[44px] min-w-[44px] p-2 inline-flex items-center justify-center text-stone-warm-400 hover:text-charcoal-900 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-charcoal-900 rounded-lg"
                title="Verified Google Review"
                aria-label="Open review on {{ $source }}"
                data-motion-interactive
            >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                </svg>
                </a>
            @else
                <span class="text-xs font-medium text-stone-warm-600">Destination pending approval</span>
            @endif
        </div>
    </div>
</article>
