@php
    $reviews = App\Support\ClinicReviews::all();
@endphp

<section
    id="testimonials"
    class="py-24 sm:py-32 bg-stone-warm-50 border-b border-stone-warm-200 overflow-hidden relative"
    data-testimonials-carousel
    data-testid="variant-b-testimonials"
    aria-label="Patient Testimonials and Reviews"
>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Editorial Section Header & Controls -->
        <div class="flex flex-col md:flex-row md:items-end justify-between gap-8 pb-8 mb-12 border-b border-stone-warm-200">
            <div class="max-w-2xl">
                <span class="text-xs font-mono uppercase tracking-widest text-stone-warm-500 block mb-3" data-motion="rise">
                    04 / Perspectives
                </span>
                <div class="h-px w-16 bg-stone-warm-300 mb-4" data-motion="timeline" aria-hidden="true"></div>
                <h2 class="font-serif text-3xl sm:text-5xl font-light text-charcoal-900 tracking-tight" data-motion="headline" data-motion-delay="80">
                    Patient Testimonials &amp; Verified Reviews
                </h2>
                <p class="mt-4 text-stone-warm-700 font-light text-base sm:text-lg leading-relaxed" data-motion="copy" data-motion-delay="160">
                    Reflections on precision care, transparent communication, and clinical tranquility.
                </p>
            </div>

            <!-- Understated Hairline Navigation Controls (>= 44x44px touch targets) -->
            <div class="flex items-center gap-3 shrink-0" data-motion="action" data-motion-delay="220">
                <button
                    type="button"
                    data-carousel-prev
                    class="w-12 h-12 min-w-[44px] min-h-[44px] rounded-full border border-stone-warm-300 bg-stone-warm-50 hover:bg-stone-warm-100 hover:border-stone-warm-400 text-charcoal-900 flex items-center justify-center transition-colors duration-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-charcoal-900 cursor-pointer"
                    aria-label="Previous testimonials"
                    data-motion-interactive
                >
                    <svg class="w-4 h-4 text-charcoal-800" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 19l-7-7 7-7" />
                    </svg>
                </button>
                <button
                    type="button"
                    data-carousel-next
                    class="w-12 h-12 min-w-[44px] min-h-[44px] rounded-full border border-stone-warm-300 bg-stone-warm-50 hover:bg-stone-warm-100 hover:border-stone-warm-400 text-charcoal-900 flex items-center justify-center transition-colors duration-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-charcoal-900 cursor-pointer"
                    aria-label="Next testimonials"
                    data-motion-interactive
                >
                    <svg class="w-4 h-4 text-charcoal-800" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5l7 7-7 7" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- Horizontal Scrollable Carousel Container -->
        <div
            data-testimonials-track
            class="flex gap-6 sm:gap-8 overflow-x-auto snap-x snap-mandatory scroll-smooth pb-6 pt-2 -mx-4 px-4 sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-charcoal-900 [scrollbar-width:none] [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden"
            tabindex="0"
            role="region"
            aria-label="Testimonials carousel items"
            data-motion-stagger="100"
            data-motion-delay="260"
        >
            @foreach($reviews as $review)
                <div class="flex-none w-[300px] sm:w-[380px] lg:w-[420px] snap-start" data-motion="review">
                    <x-variant-b.review-card :review="$review" />
                </div>
            @endforeach
        </div>
    </div>

    <!-- Interactive Popover & Mobile Modal Engine -->
    <x-shared.review-modal />
</section>
