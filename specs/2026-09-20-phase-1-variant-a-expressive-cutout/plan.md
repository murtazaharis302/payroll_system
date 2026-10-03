# Implementation Plan: Phase 1 — Variant A: Expressive / 2D Cutout Prototype

## Overview & Architecture Approach
This plan delivers the complete, interactive frontend prototype for **Variant A (Expressive / 2D Cutout)** of Dr. Bhatti & Associates Dental Clinic.

The implementation decomposes the page into 5 dedicated, single-responsibility Blade components in `resources/views/components/variant-a/`, orchestrated cleanly by `resources/views/variants/a/index.blade.php`. Styling adheres strictly to Tailwind CSS v4 design tokens in `resources/css/app.css`, utilizing warm stone surfaces, hairline borders, and zero gradients. All animations are hardware-accelerated, settle within 500ms without looping, and respect `prefers-reduced-motion`.

---

## Task Groups

### Group 1: Component Architecture & Cutout Styling
- [ ] **Task 1.1**: Create `resources/views/components/variant-a/hero-cutout.blade.php`:
  - Full-width hero section with warm stone layered background.
  - Eyebrow badge with status indicator ("Variant A &bull; Expressive 2D Cutout").
  - Clinic headline, description, primary WhatsApp CTA, and secondary Treatments anchor link.
  - Layered 2D cutout visual elements (geometric tooth/arch motifs and warm stone depth planes).
  - Settled entrance animation class (`animate-cutout-settle`).
- [ ] **Task 1.2**: Create `resources/views/components/variant-a/doctor-card.blade.php`:
  - Layered warm stone card structure for Dr. Tariq Bhatti.
  - Leadership subtitle, name, credentials (`DDS, FAGD, FICOI`), and clinical director bio.
  - Emphasized philosophy quote with warm brass accent border.
  - Grid of 4 clinical accreditation markers (`FAGD`, `FICOI`, `AACD`, `Faculty Clinical Advisor`).
- [ ] **Task 1.3**: Create `resources/views/components/variant-a/treatment-tile.blade.php`:
  - Reusable treatment card component accepting title, tagline, description, and procedure highlights.
  - Layered 2D card aesthetic with hairline border (`border-stone-warm-200`).
  - Subtle hover elevation (border color transition and micro-elevation without heavy shadow).
  - List of 4 procedural highlights with custom check/bullet markers.
- [ ] **Task 1.4**: Create `resources/views/components/variant-a/journey-step.blade.php`:
  - Reusable patient journey sequence node accepting step number (01-05), title, description, and active/last flags.
  - Prominent step badge, connecting vertical/horizontal hairline progression track.
  - Trust indicators highlighting diagnostic comfort and personalized dialogue.
- [ ] **Task 1.5**: Create `resources/views/components/variant-a/booking-finale.blade.php`:
  - Clinic location container with formatted address, coordinates, and Google Maps directions link.
  - Complete weekly hours table with 24/7 emergency protocol callout.
  - Final consultation booking card with direct WhatsApp button and phone concierge link.

### Group 2: View Orchestration & Animation Tokens
- [ ] **Task 2.1**: Refactor `resources/views/variants/a/index.blade.php`:
  - Compose `<x-variant-a.hero-cutout>`, `<x-variant-a.doctor-card>`, `<x-variant-a.treatment-tile>`, `<x-variant-a.journey-step>`, and `<x-variant-a.booking-finale>`.
  - Pass data cleanly from `config('clinic')`.
  - Maintain clean section structure and keep total file size under 120 lines.
- [ ] **Task 2.2**: Add animation tokens and reduced-motion rules in `resources/css/app.css`:
  - Define `@keyframes cutout-settle` (subtle translate-y and opacity fade, duration ~500ms, easing `cubic-bezier(0.16, 1, 0.3, 1)`).
  - Define utility class `.animate-cutout-settle`.
  - Enforce `@media (prefers-reduced-motion: reduce)` disabling animation.

### Group 3: Automated Testing & Verification
- [ ] **Task 3.1**: Create `tests/Feature/VariantATest.php`:
  - Test AC-1: Hero cutout renders headline, CTAs, and entrance animation markers.
  - Test AC-2: Doctor card renders Dr. Bhatti credentials, bio, philosophy, and all 4 accreditations.
  - Test AC-3: All 4 treatment categories render with taglines, descriptions, and procedure highlights.
  - Test AC-4: All 5 patient journey steps render with sequence numbers and descriptions.
  - Test AC-5: Booking finale renders address, hours, emergency care, and direct WhatsApp/Phone links.
  - Test AC-6: Touch targets for interactive buttons meet minimum accessibility height (>= 44px).
  - Test AC-7: Reduced motion styles are configured in CSS.
  - Test AC-8: Zero gradients exist across Variant A templates and stylesheets.
- [ ] **Task 3.2**: Audit line counts across all created/edited files to ensure strict compliance with <= 300 lines limit.
- [ ] **Task 3.3**: Run `git diff --check` to ensure no whitespace or formatting defects.
