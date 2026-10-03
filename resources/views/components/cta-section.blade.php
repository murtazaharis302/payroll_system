@php
    $clinic = config('clinic');
@endphp

<section class="border-t border-stone-warm-200 bg-stone-warm-100/40 py-16 sm:py-24" data-motion="group">
    <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 text-center sm:text-left">
        <span class="text-xs font-mono uppercase tracking-widest text-stone-warm-600 block mb-3" data-motion="rise">
            Consultation Dialogue
        </span>
        <div class="h-px w-16 bg-stone-warm-300 mb-6 mx-auto sm:mx-0" data-motion="timeline" aria-hidden="true"></div>

        <h2 class="font-serif text-2xl sm:text-4xl font-light text-charcoal-900 tracking-tight leading-tight" data-motion="headline" data-motion-delay="80">
            Begin your care with an unhurried conversation.
        </h2>

        <p class="mt-4 max-w-2xl text-sm sm:text-base font-light leading-relaxed text-stone-warm-700 mx-auto sm:mx-0" data-motion="copy" data-motion-delay="160">
            Every care plan begins with a relaxed dialogue in our private consultation suite. We discuss your oral health goals, answer questions thoroughly, and outline transparent options without pressure.
        </p>

        <div class="mt-8 flex flex-wrap items-center justify-center sm:justify-start gap-4" data-motion="action" data-motion-delay="240">
            <a
                href="{{ url('/contact?variant=b') }}"
                class="inline-flex min-h-[44px] items-center justify-center px-7 py-3 bg-charcoal-900 text-stone-warm-50 text-xs font-mono uppercase tracking-wider hover:bg-stone-warm-800 transition-colors duration-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-charcoal-900 focus-visible:ring-offset-2"
                data-motion-interactive
            >
                Start Consultation Dialogue
            </a>
            <a
                href="{{ $clinic['contact']['whatsapp_url'] }}"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex min-h-[44px] items-center justify-center px-6 py-3 border border-stone-warm-300 bg-stone-warm-50 text-charcoal-900 text-xs font-mono uppercase tracking-wider hover:bg-stone-warm-100 transition-colors duration-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-charcoal-900"
                data-motion-interactive
            >
                WhatsApp Concierge &rarr;
            </a>
        </div>
    </div>
</section>
