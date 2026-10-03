@props(['page' => []])

<section class="relative isolate overflow-hidden border-b border-stone-warm-200 bg-stone-warm-100 px-4 py-12 sm:px-6 sm:py-16 lg:px-8 lg:py-20" data-motion="fade">
    <div class="pointer-events-none absolute inset-0 -z-10" aria-hidden="true">
        <div class="absolute -right-20 -top-24 h-72 w-72 rounded-[3rem] border border-stone-warm-300 bg-stone-warm-200/70"></div>
        <div class="absolute -bottom-20 left-[8%] h-40 w-40 rotate-12 border border-brass-500/40 bg-stone-warm-50"></div>
    </div>
    <div class="mx-auto max-w-7xl">
        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-stone-warm-600" data-motion="rise">Expressive 2D foundation · clinic field notes</p>
        <div class="mt-5 max-w-4xl border-l-4 border-brass-500 pl-5 sm:pl-7">
            <h1 class="font-serif text-4xl font-semibold tracking-tight text-charcoal-900 sm:text-5xl lg:text-6xl" data-testid="page-heading" data-motion="headline" data-motion-delay="80">{{ $page['heading'] }}</h1>
            <p class="mt-6 max-w-3xl text-base leading-relaxed text-stone-warm-700 sm:text-lg" data-motion="copy" data-motion-delay="160">{{ $page['intro'] }}</p>
        </div>
    </div>
</section>
