@php
    $clinic = config('clinic');
    $heroImages = [
        ['path' => 'images/variant-b/landing-1.jpg', 'alt' => 'Overhead view of a calm clinical treatment suite'],
        ['path' => 'images/variant-b/landing-2.jpg', 'alt' => 'A prepared private dental treatment room'],
        ['path' => 'images/variant-b/landing-3.jpg', 'alt' => 'A patient receiving calm clinical care'],
    ];
@endphp

<section class="relative min-h-[620px] sm:min-h-[700px] flex items-end overflow-hidden bg-charcoal-900" data-testid="variant-b-hero" data-hero-gallery>
    <div class="absolute inset-0" data-motion="image">
        @foreach($heroImages as $image)
            <img
                src="{{ asset($image['path']) }}"
                alt="{{ $image['alt'] }}"
                @class([
                    'absolute inset-0 h-full w-full object-cover transition-opacity duration-700 motion-reduce:transition-none' => true,
                    'opacity-100' => $loop->first,
                    'opacity-0' => ! $loop->first,
                ])
                data-testid="variant-b-hero-image-{{ $loop->iteration }}"
                data-hero-slide
                aria-hidden="{{ $loop->first ? 'false' : 'true' }}"
            />
        @endforeach
        <div class="absolute inset-0 bg-charcoal-900/60" aria-hidden="true"></div>
    </div>

    <div class="relative z-10 w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-36 pb-16 sm:pt-48 sm:pb-24 text-center sm:text-left">
        <!-- Single Strong Display Headline -->
        <h1 class="font-serif text-4xl sm:text-6xl lg:text-7xl font-light tracking-tight text-stone-warm-50 leading-[1.04] max-w-4xl sm:mx-0 mx-auto" data-motion="headline" data-motion-delay="80">
            Restorative, invisible, and <span class="italic font-normal">utterly calm</span>.
        </h1>

        <!-- Restrained Supporting Copy -->
        <p class="mt-6 text-base sm:text-xl text-stone-warm-100 font-light leading-relaxed max-w-2xl sm:mx-0 mx-auto" data-motion="copy" data-motion-delay="160">
            {{ $clinic['description'] }}
        </p>

        <!-- Unaggressive, Clearly Visible CTAs (Min 44px Touch Targets) -->
        <div class="mt-9 flex flex-wrap items-center justify-center sm:justify-start gap-4" data-motion="group" data-motion-delay="240">
            <a
                href="{{ $clinic['contact']['whatsapp_url'] }}"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex items-center justify-center min-h-[44px] px-7 py-3.5 bg-stone-warm-50 text-charcoal-900 text-xs font-mono uppercase tracking-wider hover:bg-stone-warm-200 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-stone-warm-50 focus-visible:ring-offset-2 focus-visible:ring-offset-charcoal-900"
                data-motion="action"
                data-motion-interactive
            >
                Begin Consultation Dialogue
            </a>
            <a
                href="#treatments"
                class="inline-flex items-center justify-center min-h-[44px] px-6 py-3.5 text-xs font-mono uppercase tracking-wider text-stone-warm-50 hover:text-stone-warm-200 transition-colors underline underline-offset-8 decoration-stone-warm-200 hover:decoration-stone-warm-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-stone-warm-50"
                data-motion="action"
                data-motion-delay="40"
                data-motion-interactive
            >
                View Clinical Disciplines &rarr;
            </a>
        </div>

        <div class="mt-12 flex items-center justify-center sm:justify-start gap-3" data-motion="action" data-motion-delay="320">
            <span class="text-xs font-mono tracking-widest text-stone-warm-200" data-hero-index aria-live="polite">01 / 03</span>
            <button type="button" class="min-h-[44px] min-w-[44px] border border-stone-warm-100/50 text-stone-warm-50 hover:bg-stone-warm-50 hover:text-charcoal-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-stone-warm-50" data-hero-prev data-motion-interactive aria-label="Show previous hero image">
                &larr;
            </button>
            <button type="button" class="min-h-[44px] min-w-[44px] border border-stone-warm-100/50 text-stone-warm-50 hover:bg-stone-warm-50 hover:text-charcoal-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-stone-warm-50" data-hero-next data-motion-interactive aria-label="Show next hero image">
                &rarr;
            </button>
        </div>
    </div>
</section>
