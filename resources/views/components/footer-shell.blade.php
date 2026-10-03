@props(['variant' => null])
@php
    $clinic = config('clinic');
    $isEditorial = $variant === 'b';
@endphp
<footer @class([
    'w-full border-t mt-auto' => true,
    'bg-charcoal-900 border-charcoal-700' => $isEditorial,
    'bg-stone-warm-100 border-stone-warm-200' => ! $isEditorial,
]) @if($isEditorial) data-variant-footer="editorial" @endif data-motion-stagger="80">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 lg:py-16">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 lg:gap-12">
            <!-- Brand & Bio -->
            <div class="md:col-span-2 lg:col-span-2 space-y-4" data-motion="group">
                <div>
                    <span class="font-serif text-2xl font-semibold tracking-tight text-charcoal-900 block">
                        {{ $clinic['name'] }}
                    </span>
                    <span class="text-xs uppercase tracking-widest text-stone-warm-600 font-medium block mt-0.5">
                        {{ $clinic['tagline'] }}
                    </span>
                </div>
                <p class="text-sm text-stone-warm-700 leading-relaxed max-w-md">
                    {{ $clinic['description'] }}
                </p>
                <div class="pt-2">
                    <p class="text-xs font-semibold uppercase tracking-wider text-stone-warm-800">
                        {{ $clinic['doctor']['title'] }}
                    </p>
                    <p class="text-sm text-stone-warm-700 mt-0.5">
                        {{ $clinic['doctor']['name'] }} ({{ $clinic['doctor']['credentials'] }})
                    </p>
                </div>
            </div>

            <!-- Clinic Hours & Emergency -->
            <div class="space-y-4" data-motion="group">
                <h3 class="text-xs font-semibold uppercase tracking-widest text-charcoal-900">
                    Clinical Hours
                </h3>
                <ul class="space-y-2 text-sm text-stone-warm-700">
                    @foreach($clinic['hours']['schedule'] as $item)
                        <li class="flex flex-col sm:flex-row sm:justify-between gap-1 border-b border-stone-warm-200 pb-1.5">
                            <span class="font-medium text-charcoal-800">{{ $item['days'] }}</span>
                            <span class="text-stone-warm-600">{{ $item['hours'] }}</span>
                        </li>
                    @endforeach
                </ul>
                <div class="rounded-lg bg-stone-warm-200/60 p-3 border border-stone-warm-300">
                    <p class="text-xs text-stone-warm-800 leading-relaxed">
                        <strong class="font-semibold text-charcoal-900">Emergency Protocol:</strong>
                        {{ $clinic['hours']['emergency'] }}
                    </p>
                </div>
            </div>

            <!-- Contact & Location -->
            <div class="space-y-4 min-w-0" data-motion="group">
                <h3 class="text-xs font-semibold uppercase tracking-widest text-charcoal-900">
                    Location & Dialogue
                </h3>
                <address class="not-italic text-sm text-stone-warm-700 space-y-2">
                    <p class="leading-relaxed">
                        {{ $clinic['contact']['address']['formatted'] }}
                    </p>
                    <div class="pt-2 flex flex-col gap-1.5 min-w-0">
                        <a href="tel:{{ $clinic['contact']['phone_raw'] }}" class="inline-flex items-center gap-2 min-h-[44px] py-1 max-w-full hover:text-charcoal-900 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-charcoal-900 rounded px-1" data-motion-interactive>
                            <span class="text-xs uppercase tracking-wider text-stone-warm-600 shrink-0">Tel:</span>
                            <span class="font-medium truncate">{{ $clinic['contact']['phone'] }}</span>
                        </a>
                        <a href="mailto:{{ $clinic['contact']['email'] }}" class="inline-flex items-center gap-2 min-h-[44px] py-1 max-w-full hover:text-charcoal-900 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-charcoal-900 rounded px-1" data-motion-interactive>
                            <span class="text-xs uppercase tracking-wider text-stone-warm-600 shrink-0">Email:</span>
                            <span class="truncate">{{ $clinic['contact']['email'] }}</span>
                        </a>
                        <a href="{{ $clinic['contact']['whatsapp_url'] }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 min-h-[44px] py-1 max-w-full text-charcoal-900 font-medium hover:text-charcoal-700 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-charcoal-900 rounded px-1" data-motion-interactive>
                            <span class="text-xs uppercase tracking-wider text-stone-warm-600 shrink-0">WhatsApp:</span>
                            <span class="truncate">{{ $clinic['contact']['whatsapp'] }}</span>
                        </a>
                    </div>
                </address>
            </div>
        </div>

        <!-- Copyright & Legal -->
        <div class="mt-12 pt-6 border-t border-stone-warm-200 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-stone-warm-600" data-motion="fade">
            <p>&copy; {{ date('Y') }} {{ $clinic['name'] }}. All rights reserved.</p>
            <nav aria-label="Legal navigation" class="flex items-center gap-3">
                <a href="{{ url('/privacy').'?variant='.($variant ?? 'a') }}" class="min-h-[44px] inline-flex items-center focus:outline-none focus-visible:underline">Privacy</a>
                <a href="{{ url('/terms').'?variant='.($variant ?? 'a') }}" class="min-h-[44px] inline-flex items-center focus:outline-none focus-visible:underline">Terms</a>
                <a href="{{ url('/faq').'?variant='.($variant ?? 'a') }}" class="min-h-[44px] inline-flex items-center focus:outline-none focus-visible:underline">FAQ</a>
            </nav>
        </div>
    </div>
</footer>
