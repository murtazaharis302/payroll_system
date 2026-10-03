@props(['members' => [], 'variant' => 'a'])

@php($doctor = config('clinic.doctor'))
<x-variant-a.page-shell>
    <div class="space-y-10 sm:space-y-14">
        <a href="{{ url('/team/dr-tariq-bhatti').'?variant='.$variant }}" class="group grid overflow-hidden border border-charcoal-900 bg-charcoal-900 text-stone-warm-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-charcoal-900 focus-visible:ring-offset-4 lg:grid-cols-12" data-motion="group" data-motion-interactive>
            <div class="relative min-h-72 overflow-hidden bg-stone-warm-200 lg:col-span-5" data-motion="image">
                <img src="{{ asset('images/variant-a/dentist-cutout.webp') }}" alt="{{ $doctor['name'] }}" class="absolute inset-0 h-full w-full object-contain object-bottom" />
            </div>
            <div class="flex flex-col justify-center p-7 sm:p-10 lg:col-span-7 lg:p-14">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-brass-400" data-motion="rise">Clinical director</p>
                <h2 class="mt-3 font-serif text-4xl font-semibold" data-motion="headline" data-motion-delay="80">{{ $doctor['name'] }}</h2>
                <p class="mt-2 text-sm font-semibold uppercase tracking-wider text-stone-warm-200" data-motion="copy" data-motion-delay="140">{{ $doctor['title'] }} · {{ $doctor['credentials'] }}</p>
                <p class="mt-6 max-w-xl leading-relaxed text-stone-warm-100" data-motion="copy" data-motion-delay="200">{{ $doctor['bio'] }}</p>
                <span class="mt-7 inline-flex min-h-[44px] items-center text-sm font-semibold underline underline-offset-4" data-motion="action" data-motion-delay="260">Read the profile <span aria-hidden="true">→</span></span>
            </div>
        </a>
        <div class="grid gap-5 md:grid-cols-2" data-motion-stagger="80">
            @foreach($members as $member)
                @continue($member['slug'] === 'dr-tariq-bhatti')
                <a href="{{ url('/team/'.$member['slug']).'?variant='.$variant }}" class="border border-stone-warm-300 bg-stone-warm-50 p-6 focus:outline-none focus-visible:ring-2 focus-visible:ring-charcoal-900" data-motion="card" data-motion-interactive>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-brass-600">Placeholder profile</p>
                    <h2 class="mt-4 font-serif text-3xl text-charcoal-900">{{ $member['name'] }}</h2>
                    <p class="mt-3 text-sm font-semibold text-stone-warm-700">{{ $member['role'] }}</p>
                    <p class="mt-5 text-sm leading-relaxed text-stone-warm-700">{{ $member['details'] }}</p>
                    <p class="mt-5 text-xs font-semibold uppercase tracking-wider text-stone-warm-600">Credentials and biography pending client approval</p>
                </a>
            @endforeach
        </div>
    </div>
</x-variant-a.page-shell>
