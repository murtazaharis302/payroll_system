@props([
    'chapter' => null,
    'heading' => '',
    'intro' => '',
    'heroImage' => 'images/variant-b/landing-1.jpg',
])

<section class="relative isolate overflow-hidden border-b border-stone-warm-200 bg-charcoal-900 px-4 py-20 sm:px-6 sm:py-28 lg:px-8" data-testid="variant-b-page-hero" data-motion-profile="editorial" data-motion="fade">
    <img src="{{ asset($heroImage) }}" alt="" class="absolute inset-0 -z-20 h-full w-full object-cover grayscale contrast-125" data-testid="variant-b-page-hero-image" aria-hidden="true">
    <div class="absolute inset-0 -z-10 bg-charcoal-900/75" aria-hidden="true"></div>

    <div class="mx-auto max-w-4xl">
        @if($chapter)
            <div class="mb-4">
                <span class="mb-2 block text-xs font-mono uppercase tracking-widest text-stone-warm-200" data-motion="rise">{{ $chapter }}</span>
                <div class="h-px w-16 bg-stone-warm-300" data-motion="timeline" aria-hidden="true"></div>
            </div>
        @endif

        <h1 class="font-serif text-3xl font-light leading-[1.08] tracking-tight text-stone-warm-50 sm:text-5xl lg:text-6xl" data-testid="page-heading" data-motion="headline" data-motion-delay="80">
            {{ $heading }}
        </h1>

        @if($intro)
            <p class="mt-6 max-w-3xl text-base font-light leading-relaxed text-stone-warm-200 sm:text-xl" data-motion="copy" data-motion-delay="160">
                {{ $intro }}
            </p>
        @endif
    </div>
</section>
