@props(['member' => [], 'variant' => 'a'])

@php($isClinicalDirector = $member['slug'] === 'dr-tariq-bhatti')
@php($doctor = config('clinic.doctor'))
<x-variant-a.page-shell>
    <article>
        @if($isClinicalDirector)
            <div class="grid overflow-hidden border border-stone-warm-300 bg-stone-warm-50 lg:grid-cols-12">
                <div class="relative min-h-[25rem] bg-stone-warm-100 lg:col-span-5" data-motion="image">
                    <img src="{{ asset('images/variant-a/dentist-cutout.webp') }}" alt="{{ $doctor['name'] }}" class="absolute inset-0 h-full w-full object-contain object-bottom" />
                    <p class="absolute bottom-4 left-4 border border-stone-warm-300 bg-stone-warm-50 px-3 py-2 text-xs font-semibold uppercase tracking-wider text-stone-warm-700">Portrait study · existing POC asset</p>
                </div>
                <div class="p-7 sm:p-10 lg:col-span-7 lg:p-14">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-brass-600" data-motion="rise">Clinical director</p>
                    <p class="mt-3 text-sm font-semibold uppercase tracking-wider text-stone-warm-600" data-motion="copy">{{ $doctor['title'] }} · {{ $doctor['credentials'] }}</p>
                    <p class="mt-6 leading-relaxed text-stone-warm-700" data-motion="copy">{{ $doctor['bio'] }}</p>
                    <blockquote class="mt-7 border-l-4 border-brass-500 bg-stone-warm-100 px-5 py-4 font-serif text-xl leading-relaxed text-charcoal-900" data-motion="card">“{{ $doctor['philosophy'] }}”</blockquote>
                    <section class="mt-8 border-t border-stone-warm-200 pt-6" data-motion="group"><h2 class="font-serif text-2xl text-charcoal-900">Care areas represented in this prototype</h2><ul class="mt-4 grid gap-3 sm:grid-cols-2" data-motion-stagger="70">@foreach(config('site.services') as $service)<li class="border border-stone-warm-300 bg-stone-warm-100 p-3 text-sm text-stone-warm-700" data-motion="card">{{ $service['name'] }}</li>@endforeach</ul></section>
                    <section class="mt-8 border-t border-stone-warm-200 pt-6" data-motion="group"><h2 class="font-serif text-2xl text-charcoal-900">Accreditations</h2><ul class="mt-4 grid gap-3" data-motion-stagger="70">@foreach($doctor['accreditations'] as $accreditation)<li class="border-l-2 border-brass-500 pl-4 text-sm leading-relaxed text-stone-warm-700" data-motion="rise">{{ $accreditation }}</li>@endforeach</ul></section>
                    <a href="{{ url('/contact').'?variant='.$variant }}" class="mt-8 inline-flex min-h-[44px] items-center bg-charcoal-900 px-5 py-2 text-sm font-semibold text-stone-warm-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-charcoal-900 focus-visible:ring-offset-2" data-motion="action" data-motion-interactive>Begin a consultation dialogue</a>
                </div>
            </div>
        @else
            <div class="max-w-3xl border border-stone-warm-300 bg-stone-warm-50 p-7 sm:p-10 lg:p-14" data-motion="card">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-brass-600">Placeholder profile</p>
                <p class="mt-4 text-sm font-semibold uppercase tracking-wider text-stone-warm-600">{{ $member['role'] }}</p>
                <p class="mt-7 leading-relaxed text-stone-warm-700">{{ $member['details'] }}</p>
                <p class="mt-6 border-l-4 border-brass-500 bg-stone-warm-100 p-4 text-sm font-medium text-charcoal-900">Profile status: client approval required. No portrait, credentials, biography, philosophy, care areas, or accreditations have been supplied.</p>
                <a href="{{ url('/contact').'?variant='.$variant }}" class="mt-7 inline-flex min-h-[44px] items-center border border-charcoal-900 px-5 py-2 text-sm font-semibold text-charcoal-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-charcoal-900" data-motion="action" data-motion-interactive>Discuss with the clinic</a>
            </div>
        @endif
    </article>
</x-variant-a.page-shell>
