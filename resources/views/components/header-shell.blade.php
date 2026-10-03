@props(['variant' => null])
@php
    $clinic = config('clinic');
    $isEditorial = $variant === 'b';
@endphp
<header @class([
    'w-full border-b' => true,
    'absolute inset-x-0 top-0 z-20 border-white/20 bg-charcoal-900/45 backdrop-blur-sm' => $isEditorial,
    'bg-stone-warm-50 border-stone-warm-200' => ! $isEditorial,
]) data-motion="fade">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-20">
            <!-- Brand -->
            <div class="flex flex-col">
                <a href="{{ url('/') }}" @class([
                    'group flex flex-col justify-center min-h-[44px] rounded-sm focus:outline-none focus-visible:ring-2' => true,
                    'focus-visible:ring-stone-warm-50' => $isEditorial,
                    'focus-visible:ring-charcoal-900' => ! $isEditorial,
                ]) data-motion="rise" data-motion-interactive>
                    <span @class([
                        'font-serif text-xl sm:text-2xl font-semibold tracking-tight transition-colors' => true,
                        'text-stone-warm-50 group-hover:text-stone-warm-200' => $isEditorial,
                        'text-charcoal-900 group-hover:text-charcoal-700' => ! $isEditorial,
                    ])>
                        {{ $clinic['name'] }}
                    </span>
                    <span @class([
                        'text-xs uppercase tracking-widest font-medium' => true,
                        'text-stone-warm-200' => $isEditorial,
                        'text-stone-warm-600' => ! $isEditorial,
                    ])>
                        {{ $clinic['tagline'] }}
                    </span>
                </a>
            </div>

            <div class="flex items-center gap-2 lg:gap-4">
                <x-shared.primary-navigation :variant="$variant ?? 'a'" />
                <!-- Phone Direct Link -->
                <a href="tel:{{ $clinic['contact']['phone_raw'] }}" @class([
                    'hidden lg:inline-flex items-center gap-2 text-sm font-medium transition-colors focus:outline-none focus-visible:ring-2 rounded px-2 py-1 min-h-[44px]' => true,
                    'text-stone-warm-100 hover:text-stone-warm-50 focus-visible:ring-stone-warm-50' => $isEditorial,
                    'text-charcoal-800 hover:text-charcoal-900 focus-visible:ring-charcoal-900' => ! $isEditorial,
                ]) data-motion="action" data-motion-delay="80" data-motion-interactive>
                    <svg @class(['w-4 h-4' => true, 'text-stone-warm-200' => $isEditorial, 'text-stone-warm-700' => ! $isEditorial]) fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z" />
                    </svg>
                    <span>{{ $clinic['contact']['phone'] }}</span>
                </a>

                <!-- Primary Consultation CTA -->
                <a href="{{ $clinic['contact']['whatsapp_url'] }}" target="_blank" rel="noopener noreferrer" @class([
                    'hidden lg:inline-flex items-center justify-center min-h-[44px] px-5 py-2.5 rounded-full text-sm font-medium transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2' => true,
                    'bg-stone-warm-50 text-charcoal-900 hover:bg-stone-warm-200 focus-visible:ring-stone-warm-50 focus-visible:ring-offset-charcoal-900' => $isEditorial,
                    'bg-charcoal-900 text-stone-warm-50 hover:bg-charcoal-800 focus-visible:ring-charcoal-900' => ! $isEditorial,
                ]) data-motion="action" data-motion-delay="160" data-motion-interactive>
                    <span>Inquire via WhatsApp</span>
                </a>
            </div>
        </div>
    </div>
</header>
