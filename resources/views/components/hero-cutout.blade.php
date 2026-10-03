@php
    $clinic = config('clinic');
@endphp
<section class="relative overflow-hidden bg-stone-warm-50 border-b border-stone-warm-200 py-16 sm:py-24 lg:py-28" data-testid="variant-a-hero">
    <!-- 2D Cutout Background Plane Geometry (Zero Gradients) -->
    <div class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
        <div class="absolute -top-12 right-0 w-96 h-96 rounded-3xl bg-stone-warm-100/60 border border-stone-warm-200/70 rotate-6 translate-x-1/4" data-motion="fade" data-motion-delay="280"></div>
        <div class="absolute top-1/2 -right-16 w-80 h-80 rounded-full bg-stone-warm-200/40 border border-stone-warm-300/60 -translate-y-1/2" data-motion="fade" data-motion-delay="360"></div>
        <div class="absolute bottom-0 left-1/3 w-64 h-32 rounded-t-3xl bg-stone-warm-100/40 border-t border-x border-stone-warm-200/50" data-motion="fade" data-motion-delay="420"></div>
    </div>

    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 lg:gap-8 items-center">
            <!-- Hero Content Column -->
            <div class="lg:col-span-7">
                <!-- Headline -->
                <h1 class="font-serif text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight text-charcoal-900 leading-[1.12]" data-motion="headline" data-motion-delay="120">
                    Calm, architectural dentistry crafted for lifelong wellness.
                </h1>

                <!-- Clinical Value Proposition -->
                <p class="mt-6 text-lg sm:text-xl text-stone-warm-700 leading-relaxed max-w-2xl font-normal" data-motion="copy" data-motion-delay="200">
                    {{ $clinic['description'] }}
                </p>

                <!-- Call To Actions -->
                <div class="mt-8 sm:mt-10 flex flex-wrap items-center gap-4" data-motion="action" data-motion-delay="280">
                    <a href="{{ $clinic['contact']['whatsapp_url'] }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center justify-center min-h-[44px] px-7 py-3 rounded-full bg-charcoal-900 text-stone-warm-50 text-sm font-semibold hover:bg-charcoal-800 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-charcoal-900" data-motion-interactive>
                        Book Initial Consultation
                    </a>
                    <a href="#treatments" class="inline-flex items-center justify-center min-h-[44px] px-6 py-3 rounded-full border border-stone-warm-300 bg-stone-warm-100 text-charcoal-800 text-sm font-semibold hover:bg-stone-warm-200 hover:border-stone-warm-400 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-charcoal-900" data-motion-interactive>
                        Explore Treatments
                    </a>
                </div>

                <!-- Key Metrics & Trust Callouts -->
                <div class="mt-10 pt-8 border-t border-stone-warm-200/80 grid grid-cols-3 gap-4 max-w-lg" data-motion-stagger="70" data-motion-delay="340">
                    <div data-motion="rise">
                        <span class="block font-serif text-2xl font-bold text-charcoal-900">20+</span>
                        <span class="block text-xs uppercase tracking-wider text-stone-warm-600 mt-0.5">Years Clinical Excellence</span>
                    </div>
                    <div data-motion="rise">
                        <span class="block font-serif text-2xl font-bold text-charcoal-900">100%</span>
                        <span class="block text-xs uppercase tracking-wider text-stone-warm-600 mt-0.5">Digital 3D Diagnostics</span>
                    </div>
                    <div data-motion="rise">
                        <span class="block font-serif text-2xl font-bold text-charcoal-900">24/7</span>
                        <span class="block text-xs uppercase tracking-wider text-stone-warm-600 mt-0.5">Emergency Concierge</span>
                    </div>
                </div>
            </div>

            <!-- Layered 2D Cutout Composition with Dentist as Sole Primary Photographic Cutout -->
            <div class="lg:col-span-5 flex justify-center lg:justify-end">
                <div class="relative w-full max-w-md">
                    <!-- Layer 1: Warm Stone Cutout Backplate -->
                    <div class="absolute -top-4 -left-4 w-full h-[95%] rounded-3xl bg-stone-warm-200 border border-stone-warm-300 rotate-1" data-motion="fade" data-motion-delay="320"></div>

                    <!-- Layer 2: Main Architectural Surface with Subtle Anatomical Line Drawing & Natural Overlap -->
                    <div class="relative rounded-3xl bg-stone-warm-100 border border-stone-warm-300 pt-6 px-6 sm:px-8 pb-0 overflow-hidden flex flex-col items-center" data-motion="card" data-motion-delay="300">
                        <!-- Subtle Oversized Dental Anatomical Line Drawing Behind Composition -->
                        <svg class="absolute -right-10 -top-6 w-80 h-80 text-stone-warm-400/25 stroke-current pointer-events-none z-0" viewBox="0 0 200 200" fill="none" aria-hidden="true">
                            <!-- Molar Crown & Enamel Contour -->
                            <path d="M40 70 C40 35, 70 25, 100 30 C130 25, 160 35, 160 70 C160 100, 150 120, 140 170 C135 180, 125 180, 120 160 C115 140, 110 110, 100 110 C90 110, 85 140, 80 160 C75 180, 65 180, 60 170 C50 120, 40 100, 40 70 Z" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                            <!-- Internal Dentin & Pulp Chamber Contour -->
                            <path d="M60 75 C60 50, 80 45, 100 48 C120 45, 140 50, 140 75 C140 95, 132 110, 125 145 C122 152, 118 152, 115 138 C110 120, 108 95, 100 95 C92 95, 90 120, 85 138 C82 152, 78 152, 75 145 C68 110, 60 95, 60 75 Z" stroke-width="1" stroke-dasharray="3 3" />
                            <!-- Architectural Calibration Crosshairs & Axis Marks -->
                            <line x1="20" y1="70" x2="180" y2="70" stroke-width="0.75" stroke-dasharray="4 4" />
                            <line x1="100" y1="15" x2="100" y2="185" stroke-width="0.75" stroke-dasharray="4 4" />
                            <circle cx="100" cy="70" r="3" stroke-width="1" />
                        </svg>

                        <!-- Top Accent Tag -->
                        <div class="w-full flex items-center justify-between pb-4 border-b border-stone-warm-200 z-10">
                            <div class="flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-sm bg-brass-500"></span>
                                <span class="text-xs uppercase tracking-widest text-stone-warm-800 font-semibold">Clinical Director</span>
                            </div>
                            <span class="text-xs font-mono text-stone-warm-600">{{ $clinic['contact']['address']['city'] }}, {{ $clinic['contact']['address']['state'] }}</span>
                        </div>

                        <!-- Full-Height Cutout of Confident Dentist with Arms Crossed (Natural Architectural Overlap) -->
                        <div class="relative z-10 w-full flex justify-center mt-2" data-motion="image" data-motion-delay="420">
                            <img src="{{ asset('images/variant-a/dentist-cutout.webp') }}" alt="Dr. Tariq Bhatti standing with arms crossed" class="w-auto h-80 sm:h-96 object-contain filter drop-shadow-[0_8px_16px_rgba(0,0,0,0.06)]" />
                        </div>

                        <!-- Overlapping Identity Badge -->
                        <div class="absolute bottom-4 left-4 right-4 z-20 rounded-xl bg-stone-warm-50/95 border border-stone-warm-300 p-3 shadow-none" data-motion="rise" data-motion-delay="540">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="font-serif text-sm font-bold text-charcoal-900">
                                        {{ $clinic['doctor']['name'] }}
                                    </p>
                                    <p class="text-[11px] font-medium text-stone-warm-700">
                                        {{ $clinic['doctor']['title'] }} &bull; {{ $clinic['doctor']['credentials'] }}
                                    </p>
                                </div>
                                <span class="w-2 h-2 rounded-full bg-brass-500"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
