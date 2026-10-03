@props(['page' => [], 'variant' => 'a'])

@php($key = $page['key'])
<x-variant-a.page-shell>
        @if($key === 'patient-journey')
            <ol class="grid gap-5 sm:grid-cols-2 xl:grid-cols-5" data-motion-stagger="80">
                @foreach(config('clinic.journey') as $step)
                    <li class="relative border border-stone-warm-300 bg-stone-warm-50 p-5" data-motion="card"><p class="font-serif text-5xl text-brass-600">{{ $step['step'] }}</p><h2 class="mt-8 font-serif text-2xl text-charcoal-900">{{ $step['title'] }}</h2><p class="mt-4 text-sm leading-relaxed text-stone-warm-700">{{ $step['description'] }}</p></li>
                @endforeach
            </ol>
        @elseif($key === 'faq')
            <div class="mx-auto max-w-4xl space-y-3" data-motion-stagger="70">
                @foreach(config('site.faqs') as $faq)
                    <details class="border border-stone-warm-300 bg-stone-warm-50 p-5" data-motion="card"><summary class="min-h-[44px] cursor-pointer font-serif text-xl text-charcoal-900">{{ $faq['question'] }}</summary><p class="border-t border-stone-warm-200 pt-4 leading-relaxed text-stone-warm-700">{{ $faq['answer'] }}</p></details>
                @endforeach
            </div>
        @elseif($key === 'contact')
            @php($clinic = config('clinic'))
            <div class="grid items-start gap-8 lg:grid-cols-2 lg:gap-12">
                <div class="max-w-2xl" data-motion="group"><x-shared.consultation-form /></div>
                <address class="not-italic overflow-hidden border border-stone-warm-300 bg-stone-warm-50" data-motion="card"><img src="{{ asset('images/variant-a/clinic-map.webp') }}" alt="Map of {{ $clinic['contact']['address']['formatted'] }}" class="h-56 w-full object-cover sm:h-72" /><div class="p-6 sm:p-8"><h2 class="font-serif text-3xl text-charcoal-900">Visit or call the clinic</h2><p class="mt-4 max-w-lg leading-relaxed text-stone-warm-700">{{ $clinic['contact']['address']['formatted'] }}</p><a class="mt-5 inline-flex min-h-[44px] items-center font-semibold text-charcoal-900 underline underline-offset-4 focus:outline-none focus-visible:ring-2 focus-visible:ring-charcoal-900" href="tel:{{ $clinic['contact']['phone_raw'] }}" data-motion-interactive>{{ $clinic['contact']['phone'] }}</a><a class="mt-2 flex min-h-[44px] items-center font-semibold text-charcoal-900 underline underline-offset-4 focus:outline-none focus-visible:ring-2 focus-visible:ring-charcoal-900" href="mailto:{{ $clinic['contact']['email'] }}" data-motion-interactive>{{ $clinic['contact']['email'] }}</a><p class="mt-5 text-xs font-semibold uppercase tracking-wider text-stone-warm-600">Functional map and contact treatment · POC location</p></div></address>
            </div>
        @else
            <div class="mx-auto max-w-4xl border border-stone-warm-300 bg-stone-warm-50 p-7 sm:p-10" data-motion="card"><p class="border-l-4 border-brass-500 bg-stone-warm-100 p-5 leading-relaxed text-charcoal-900">{{ config('site.legal.notice') }}</p><p class="mt-6 text-sm leading-relaxed text-stone-warm-700">{{ $page['intro'] }}</p><a href="{{ url('/contact').'?variant='.$variant }}" class="mt-8 inline-flex min-h-[44px] items-center border border-charcoal-900 px-5 py-2 text-sm font-semibold text-charcoal-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-charcoal-900" data-motion="action" data-motion-interactive>Contact the clinic</a></div>
        @endif
</x-variant-a.page-shell>
