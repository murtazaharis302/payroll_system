@props(['services' => [], 'variant' => 'a'])

<x-variant-a.page-shell>
    <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3" data-motion-stagger="80">
            @foreach($services as $service)
                <a href="{{ url('/services/'.$service['slug']).'?variant='.$variant }}" class="group relative flex min-h-72 flex-col justify-between overflow-hidden border border-stone-warm-300 bg-stone-warm-50 p-6 sm:p-8 focus:outline-none focus-visible:ring-2 focus-visible:ring-charcoal-900" data-motion="card" data-motion-interactive>
                    <span class="absolute right-4 top-3 font-serif text-6xl text-stone-warm-200" aria-hidden="true">0{{ $loop->iteration }}</span>
                    <div class="relative">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-brass-600">Treatment briefing</p>
                        <h2 class="mt-4 max-w-xs font-serif text-3xl font-semibold text-charcoal-900">{{ $service['name'] }}</h2>
                        <p class="mt-4 max-w-md text-sm leading-relaxed text-stone-warm-700">{{ $service['introduction'] }}</p>
                    </div>
                    <span class="relative mt-7 inline-flex min-h-[44px] items-center text-sm font-semibold text-charcoal-900 underline underline-offset-4">Explore the discussion <span aria-hidden="true">→</span></span>
                </a>
            @endforeach
    </div>
</x-variant-a.page-shell>
