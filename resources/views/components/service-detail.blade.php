@props(['service' => [], 'variant' => 'a'])

<x-variant-a.page-shell>
    <article>
        <div class="grid gap-10 lg:grid-cols-12 lg:gap-14">
            <aside class="relative overflow-hidden border border-stone-warm-300 bg-stone-warm-100 p-6 sm:p-8 lg:col-span-4 lg:sticky lg:top-8" data-motion="image">
                <img src="{{ asset('images/variant-a/tooth-anatomy.webp') }}" alt="Illustrative tooth anatomy study" class="absolute inset-0 h-full w-full object-cover opacity-20" />
                <div class="relative">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-brass-600">Information, not a guarantee</p>
                    <p class="mt-5 font-serif text-2xl leading-relaxed text-charcoal-900">Suitability is considered with a clinician after an individual assessment.</p>
                </div>
            </aside>
            <div class="max-w-3xl space-y-10 lg:col-span-8">
                <section class="border-l-4 border-brass-500 pl-5" data-motion="copy"><h2 class="font-serif text-3xl text-charcoal-900">Suitability</h2><p class="mt-3 leading-relaxed text-stone-warm-700">{{ $service['suitability'] }}</p></section>
                <section class="border-t border-stone-warm-200 pt-7" data-motion="copy"><h2 class="font-serif text-3xl text-charcoal-900">The consultation process</h2><p class="mt-3 leading-relaxed text-stone-warm-700">{{ $service['process'] }}</p></section>
                <section class="border-t border-stone-warm-200 pt-7" data-motion="group"><h2 class="font-serif text-3xl text-charcoal-900">Benefits and considerations</h2><ul class="mt-4 grid gap-3 sm:grid-cols-2" data-motion-stagger="70">@forelse($service['benefits'] as $benefit)<li class="border border-stone-warm-300 bg-stone-warm-100 p-4 text-sm text-stone-warm-700" data-motion="card">{{ $benefit }}</li>@empty<li class="border border-stone-warm-300 bg-stone-warm-100 p-4 text-sm text-stone-warm-700" data-motion="card">Benefits and considerations require clinician confirmation.</li>@endforelse</ul></section>
                <section class="border-t border-stone-warm-200 pt-7" data-motion="copy"><h2 class="font-serif text-3xl text-charcoal-900">Technology and materials</h2><p class="mt-3 leading-relaxed text-stone-warm-700">{{ $service['technology'] }}</p></section>
                @if($service['faqs'])<section class="border-t border-stone-warm-200 pt-7" data-motion="group"><h2 class="font-serif text-3xl text-charcoal-900">Frequently asked questions</h2><div class="mt-4 grid gap-3">@foreach($service['faqs'] as $faq)<details class="border border-stone-warm-300 bg-stone-warm-50 p-4" data-motion="rise"><summary class="min-h-[44px] cursor-pointer font-medium text-charcoal-900">{{ $faq['question'] }}</summary><p class="pt-3 leading-relaxed text-stone-warm-700">{{ $faq['answer'] }}</p></details>@endforeach</div></section>@endif
                @if($service['related'])<section class="border-t border-stone-warm-200 pt-7" data-motion="group"><h2 class="font-serif text-3xl text-charcoal-900">Related treatments</h2><div class="mt-4 flex flex-wrap gap-3">@foreach($service['related'] as $related)<a href="{{ url('/services/'.$related).'?variant='.$variant }}" class="inline-flex min-h-[44px] items-center border border-stone-warm-300 px-4 text-sm font-semibold text-charcoal-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-charcoal-900" data-motion="action" data-motion-interactive>{{ str($related)->replace('-', ' ')->title() }}</a>@endforeach</div></section>@endif
                <a href="{{ url($service['cta']['path']).'?variant='.$variant }}" class="inline-flex min-h-[44px] items-center bg-charcoal-900 px-5 py-2 text-sm font-semibold text-stone-warm-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-charcoal-900 focus-visible:ring-offset-2" data-motion="action" data-motion-interactive>{{ $service['cta']['label'] }}</a>
            </div>
        </div>
    </article>
</x-variant-a.page-shell>
