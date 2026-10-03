@php
    $doctor = config('clinic.doctor');
@endphp
<section class="py-16 sm:py-24 bg-stone-warm-100/60 border-b border-stone-warm-200" id="philosophy" data-testid="variant-a-doctor">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Section Header -->
        <div class="mb-12">
            <span class="text-xs font-semibold uppercase tracking-widest text-stone-warm-600 block mb-2" data-motion="rise">
                Clinical Leadership &amp; Philosophy
            </span>
            <h2 class="font-serif text-3xl sm:text-4xl font-bold text-charcoal-900 tracking-tight" data-motion="headline" data-motion-delay="80">
                Architectural Precision, Unhurried Empathy
            </h2>
        </div>

        <!-- Layered 2D Stone Card Composition -->
        <div class="relative">
            <!-- Background Layered Cutout Backplate -->
            <div class="hidden sm:block absolute -top-4 -left-4 w-full h-full rounded-3xl bg-stone-warm-200/90 border border-stone-warm-300" aria-hidden="true"></div>

            <!-- Foreground Main Card -->
            <div class="relative rounded-3xl bg-stone-warm-50 border border-stone-warm-300 p-8 sm:p-12 lg:p-14" data-motion="group" data-motion-delay="160">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 lg:gap-14 items-start">
                    <!-- Doctor Identity Column: Clinical Monograph & Anatomical Study Plate -->
                    <div class="lg:col-span-5">
                        <!-- Architectural Monograph Plate: Dentist Layered Over Tooth Anatomy Diagram -->
                        <div class="relative w-full max-w-sm mx-auto lg:mx-0 rounded-2xl bg-stone-warm-100 border border-stone-warm-300 overflow-hidden shadow-none" data-motion="mask" data-motion-delay="240">
                            <!-- Monograph Header Strip -->
                            <div class="px-4 py-2.5 bg-stone-warm-200/90 border-b border-stone-warm-300 flex items-center justify-between z-20 relative">
                                <div class="flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-sm bg-brass-500"></span>
                                    <span class="text-[10px] font-mono uppercase tracking-widest text-stone-warm-900 font-semibold">Plate 01 &bull; Biomimetic Study</span>
                                </div>
                                <span class="text-[10px] font-mono text-stone-warm-600">Fig. 1.0</span>
                            </div>

                            <!-- Layered Composition: Tooth Anatomy Diagram + Dentist Cutout -->
                            <div class="relative h-80 sm:h-96 w-full bg-stone-warm-200/50 flex items-end justify-center overflow-hidden">
                                <!-- Technical Anatomical Plate (Desaturated Background Layer) -->
                                <div class="absolute inset-0 z-0 opacity-35 filter grayscale contrast-125 pointer-events-none">
                                    <img src="{{ asset('images/variant-a/tooth-anatomy.webp') }}" alt="Biomimetic Tooth Anatomy Diagram" class="w-full h-full object-cover object-center" />
                                </div>

                                <!-- Architectural Grid Overlay & Callout Crosshairs -->
                                <div class="absolute inset-0 z-5 pointer-events-none" aria-hidden="true">
                                    <div class="absolute top-4 left-4 text-[9px] font-mono text-stone-warm-700 bg-stone-warm-100/90 px-1.5 py-0.5 rounded border border-stone-warm-300/80">
                                        + Enamel Boundary
                                    </div>
                                    <div class="absolute top-16 right-4 text-[9px] font-mono text-stone-warm-700 bg-stone-warm-100/90 px-1.5 py-0.5 rounded border border-stone-warm-300/80">
                                        + Dentin Matrix
                                    </div>
                                    <div class="absolute bottom-12 left-4 text-[9px] font-mono text-stone-warm-700 bg-stone-warm-100/90 px-1.5 py-0.5 rounded border border-stone-warm-300/80">
                                        + Pulp Vitality
                                    </div>
                                </div>

                                <!-- Photographic Cutout: Doctor Layered Over Anatomical Diagram -->
                                <div class="relative z-10 w-full flex justify-center" data-motion="image" data-motion-delay="340">
                                    <img src="{{ asset('images/variant-a/dentist-cutout.webp') }}" alt="{{ $doctor['name'] }}" class="w-auto h-72 sm:h-80 object-contain drop-shadow-[0_12px_24px_rgba(0,0,0,0.08)] filter" />
                                </div>
                            </div>

                            <!-- Integrated Architectural Monograph Footer (No Duplicated Floating Badge) -->
                            <div class="p-4 bg-stone-warm-50 border-t border-stone-warm-300 z-20 relative">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h3 class="font-serif text-base sm:text-lg font-bold text-charcoal-900">
                                            {{ $doctor['name'] }}
                                        </h3>
                                        <p class="text-xs font-medium text-stone-warm-700">
                                            {{ $doctor['title'] }} &bull; {{ $doctor['credentials'] }}
                                        </p>
                                    </div>
                                    <span class="text-[10px] font-mono uppercase tracking-wider text-stone-warm-600 bg-stone-warm-200 px-2 py-1 rounded border border-stone-warm-300">
                                        Clinical Ethos
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Philosophy & Accreditations Column -->
                    <div class="lg:col-span-7 space-y-8">
                        <!-- Philosophy Quote with Warm Accent Border -->
                        <div class="border-l-4 border-brass-500 pl-6 py-2 bg-stone-warm-100/50 rounded-r-2xl border-y border-r border-stone-warm-200" data-motion="rise" data-motion-delay="280">
                            <span class="text-xs font-semibold uppercase tracking-widest text-stone-warm-600 block mb-2">
                                The Clinical Ethos
                            </span>
                            <blockquote class="font-serif text-lg sm:text-xl italic text-charcoal-900 leading-relaxed">
                                &ldquo;{{ $doctor['philosophy'] }}&rdquo;
                            </blockquote>
                        </div>

                        <!-- Biographical Narrative -->
                        <div class="space-y-4 text-stone-warm-800 text-sm sm:text-base leading-relaxed" data-motion="copy" data-motion-delay="360">
                            <p>
                                {{ $doctor['bio'] }}
                            </p>
                            <p class="text-xs sm:text-sm text-stone-warm-700">
                                Dedicated to minimizing treatment invasiveness through biomimetic restorations and precision diagnostics, Dr. Bhatti ensures every patient receives an individualized plan rooted in long-term physiology.
                            </p>
                        </div>

                        <!-- Clinical Accreditation Badges (AC-2) -->
                        <div class="pt-6 border-t border-stone-warm-200">
                            <span class="text-xs font-semibold uppercase tracking-widest text-charcoal-900 block mb-4">
                                Board Honors &amp; Accreditations
                            </span>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3" data-motion-stagger="70" data-motion-delay="440">
                                @foreach($doctor['accreditations'] as $accreditation)
                                    <div class="flex items-start gap-3 p-3.5 rounded-xl bg-stone-warm-100 border border-stone-warm-200/90 hover:border-stone-warm-300 transition-colors" data-motion="card" data-motion-interactive>
                                        <div class="w-5 h-5 rounded-md bg-stone-warm-200 border border-stone-warm-300 flex items-center justify-center shrink-0 mt-0.5">
                                            <svg class="w-3.5 h-3.5 text-brass-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4.5 12.75l6 6 9-13.5" />
                                            </svg>
                                        </div>
                                        <span class="text-xs font-medium text-charcoal-800 leading-tight">
                                            {{ $accreditation }}
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
