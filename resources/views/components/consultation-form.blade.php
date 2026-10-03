<form data-consultation-form novalidate class="space-y-5 border border-stone-warm-300 bg-stone-warm-50 p-6" aria-describedby="consultation-form-note" data-motion="group">
    <p id="consultation-form-note" class="text-sm leading-relaxed text-stone-warm-700" data-motion="copy">POC only: this form validates in your browser and does not send information, create an appointment, or persist data.</p>
    <div class="grid gap-5 sm:grid-cols-2" data-motion="group" data-motion-delay="80">
        <label class="grid gap-2 text-sm font-medium text-charcoal-900">
            Name
            <input name="name" type="text" autocomplete="name" required class="min-h-[44px] border border-stone-warm-400 bg-white px-3 focus:outline-none focus-visible:ring-2 focus-visible:ring-charcoal-900" aria-describedby="consultation-name-error">
            <span id="consultation-name-error" data-form-error="name" class="text-sm text-red-700" aria-live="polite"></span>
        </label>
        <label class="grid gap-2 text-sm font-medium text-charcoal-900">
            Email
            <input name="email" type="email" autocomplete="email" required class="min-h-[44px] border border-stone-warm-400 bg-white px-3 focus:outline-none focus-visible:ring-2 focus-visible:ring-charcoal-900" aria-describedby="consultation-email-error">
            <span id="consultation-email-error" data-form-error="email" class="text-sm text-red-700" aria-live="polite"></span>
        </label>
    </div>
    <label class="grid gap-2 text-sm font-medium text-charcoal-900" data-motion="copy" data-motion-delay="160">
        What would you like to discuss?
        <textarea name="message" rows="4" required class="border border-stone-warm-400 bg-white p-3 focus:outline-none focus-visible:ring-2 focus-visible:ring-charcoal-900" aria-describedby="consultation-message-error"></textarea>
        <span id="consultation-message-error" data-form-error="message" class="text-sm text-red-700" aria-live="polite"></span>
    </label>
    <button type="submit" class="inline-flex min-h-[44px] items-center justify-center bg-charcoal-900 px-5 py-2 text-sm font-semibold text-stone-warm-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-charcoal-900 focus-visible:ring-offset-2" data-motion="action" data-motion-delay="240">Preview consultation request</button>
    <p data-form-status class="text-sm font-medium text-sage-800" aria-live="polite"></p>
</form>
