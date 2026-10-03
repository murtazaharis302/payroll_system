@php
    $doctor = config('clinic.doctor');
@endphp

<section class="py-24 sm:py-32 bg-stone-warm-100/50 border-b border-stone-warm-200" id="ethos" data-testid="variant-b-doctor">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 lg:gap-16 items-start">
            <!-- Left Portrait Column (Asymmetric 5-column editorial framing) -->
            <div class="lg:col-span-5">
                <div class="border border-stone-warm-200 bg-stone-warm-50 p-3 sm:p-4 rounded-2xl" data-motion="image">
                    <div class="relative aspect-[3/4] rounded-xl overflow-hidden bg-stone-warm-200/60 border border-stone-warm-200">
                        <img
                            src="{{ asset('images/variant-a/doctor-tariq-bhatti.png') }}"
                            alt="{{ $doctor['name'] }}, {{ $doctor['title'] }}"
                            class="w-full h-full object-cover object-top filter grayscale contrast-105"
                        />
                        <div class="absolute bottom-3 left-3 right-3 bg-stone-warm-50/95 border border-stone-warm-200 rounded-lg p-3">
                            <p class="font-serif text-sm font-medium text-charcoal-900">{{ $doctor['name'] }}</p>
                            <p class="text-xs text-stone-warm-600 font-mono mt-0.5">{{ $doctor['credentials'] }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Ethos & Accreditation Column (Asymmetric 7-column) -->
            <div class="lg:col-span-7 space-y-8">
                <div>
                    <span class="text-xs font-mono uppercase tracking-widest text-stone-warm-600 block mb-3" data-motion="rise">01 / Ethos</span>
                    <div class="h-px w-16 bg-stone-warm-300 mb-4" data-motion="timeline" aria-hidden="true"></div>
                    <h2 class="font-serif text-3xl sm:text-4xl lg:text-5xl font-light text-charcoal-900 tracking-tight leading-tight" data-motion="headline" data-motion-delay="80">
                        {{ $doctor['name'] }}
                    </h2>
                    <p class="text-xs font-mono uppercase tracking-wider text-stone-warm-600 mt-2" data-motion="copy" data-motion-delay="140">
                        {{ $doctor['title'] }} &bull; {{ $doctor['credentials'] }}
                    </p>
                </div>

                <!-- Philosophy Quote with Delicate Hairline Divider -->
                <blockquote class="font-serif text-2xl sm:text-3xl font-light italic text-charcoal-900 leading-snug border-l border-stone-warm-300 pl-6 sm:pl-8 py-1 my-6" data-motion="copy" data-motion-delay="200">
                    &ldquo;{{ $doctor['philosophy'] }}&rdquo;
                </blockquote>

                <!-- Biographical Narrative -->
                <div class="space-y-4 text-stone-warm-700 font-light leading-relaxed text-base sm:text-lg" data-motion="copy" data-motion-delay="260">
                    <p>
                        {{ $doctor['bio'] }}
                    </p>
                </div>

                <!-- Clinical Accreditation Markers (AC-2) -->
                <div class="pt-8 border-t border-stone-warm-200" data-motion="group" data-motion-delay="320">
                    <span class="text-xs font-mono uppercase tracking-widest text-stone-warm-600 block mb-4">
                        Clinical Accreditations &amp; Honors
                    </span>

                    <div class="divide-y divide-stone-warm-200 border-y border-stone-warm-200" data-motion-stagger="90">
                        @foreach($doctor['accreditations'] as $accreditation)
                            <div class="py-3.5 flex items-center justify-between text-sm" data-motion="card">
                                <span class="font-normal text-charcoal-900">{{ $accreditation }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
