<section {{ $attributes->merge(['class' => 'relative px-4 py-12 sm:px-6 sm:py-16 lg:px-8 lg:py-20']) }} data-testid="variant-a-page-shell">
    <div class="pointer-events-none absolute inset-x-0 top-0 h-px bg-stone-warm-200" aria-hidden="true"></div>

    <div class="mx-auto max-w-7xl">
        {{ $slot }}
    </div>
</section>
