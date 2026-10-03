@props(['page' => [], 'variant' => 'a'])

@php($doctor = config('clinic.doctor'))
<x-variant-a.page-shell>
    <div class="grid gap-12 lg:grid-cols-12 lg:items-center lg:gap-16">
        <div class="relative lg:col-span-5" data-motion="image">
            <div class="absolute inset-4 -z-10 rotate-3 rounded-3xl border border-stone-warm-300 bg-stone-warm-200" aria-hidden="true"></div>
            <figure class="overflow-hidden rounded-3xl border border-stone-warm-300 bg-stone-warm-100">
                <img src="{{ asset('images/variant-a/dentist-cutout.webp') }}" alt="{{ $doctor['name'] }}" class="mx-auto h-80 w-full object-contain object-bottom sm:h-[28rem]" />
                <figcaption class="border-t border-stone-warm-300 bg-stone-warm-50 px-5 py-4 text-sm text-stone-warm-700">Existing POC portrait treatment — detailed biography remains subject to client review.</figcaption>
            </figure>
        </div>
        <div class="lg:col-span-7" data-motion="group" data-motion-delay="100">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-brass-600" data-motion="rise">Clinical direction</p>
            <h2 class="mt-3 font-serif text-3xl font-semibold text-charcoal-900 sm:text-4xl" data-motion="headline" data-motion-delay="80">{{ $doctor['name'] }}</h2>
            <p class="mt-2 text-sm font-semibold uppercase tracking-wider text-stone-warm-600" data-motion="copy" data-motion-delay="140">{{ $doctor['title'] }} · {{ $doctor['credentials'] }}</p>
            <blockquote class="mt-7 border-l-4 border-brass-500 bg-stone-warm-100 px-5 py-4 font-serif text-xl leading-relaxed text-charcoal-900" data-motion="card" data-motion-delay="200">“{{ $doctor['philosophy'] }}”</blockquote>
            <p class="mt-7 max-w-2xl leading-relaxed text-stone-warm-700" data-motion="copy" data-motion-delay="260">{{ $doctor['bio'] }}</p>
            <a href="{{ url('/team/dr-tariq-bhatti').'?variant='.$variant }}" class="mt-8 inline-flex min-h-[44px] items-center border border-charcoal-900 bg-charcoal-900 px-5 py-2 text-sm font-semibold text-stone-warm-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-charcoal-900 focus-visible:ring-offset-2" data-motion="action" data-motion-delay="320" data-motion-interactive>View the clinical director profile</a>
        </div>
    </div>
</x-variant-a.page-shell>
