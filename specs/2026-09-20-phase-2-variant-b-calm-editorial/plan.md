# Implementation Plan: Phase 2 — Variant B: Calm / Editorial Prototype

## Overview & Architecture Approach
This plan delivers the complete, interactive frontend prototype for **Variant B (Calm / Editorial)** of Dr. Bhatti & Associates Dental Clinic.

The implementation decomposes the page into 5 dedicated, single-responsibility Blade components in `resources/views/components/variant-b/`, orchestrated cleanly by `resources/views/variants/b/index.blade.php`. Styling adheres strictly to Tailwind CSS v4 design tokens in `resources/css/app.css`, utilizing serene typography, generous negative space, hairline dividers, and zero gradients. All animations are hardware-accelerated, settle within 600ms without looping, and respect `prefers-reduced-motion`.

---

## Task Groups

### Group 1: Component Architecture & Editorial Layouts
- [x] **Task 1.1**: Create `resources/views/components/variant-b/hero-editorial.blade.php`:
  - Full-bleed architectural editorial hero layout with generous negative space.
  - Eyebrow badge ("Variant B &bull; Calm Editorial Direction").
  - Display headline, description, primary WhatsApp CTA ("Begin Consultation Dialogue"), secondary anchor link ("View Clinical Disciplines &rarr;").
  - Serene photographic / architectural framing with calm, subtle entrance animation (`animate-editorial-settle`).
- [x] **Task 1.2**: Create `resources/views/components/variant-b/doctor-portrait.blade.php`:
  - Asymmetric 12-column architectural grid pairing portraiture with clinical director ethos.
  - Section index marker (`01 / Ethos`).
  - Dr. Tariq Bhatti name, credentials (`DDS, FAGD, FICOI`), and leadership title.
  - Prominent philosophy quote block with delicate hairline border/divider.
  - Full biographical narrative and 4 clinical accreditation markers (`FAGD`, `FICOI`, `AACD`, `Faculty Clinical Advisor`).
- [x] **Task 1.3**: Create `resources/views/components/variant-b/treatment-row.blade.php`:
  - Section index marker (`02 / Disciplines`).
  - Restrained hairline row / list-style editorial layout with subtle dividers (`border-stone-warm-200`).
  - Renders 4 treatment categories with titles, taglines, full descriptions, and procedural highlights.
  - Quiet hover interactions without heavy drop shadows.
- [x] **Task 1.4**: Create `resources/views/components/variant-b/journey-timeline.blade.php`:
  - Section index marker (`03 / Protocol` or `03 / Journey`).
  - Quiet vertical timeline with delicate hairline markers and sequence numerals (01-05).
  - 5 sequential care steps with titles and reassuring descriptions.
  - Architectural quote blocks / patient assurance callouts emphasizing unhurried dialogue, low-dose imaging, and transparent planning.
- [x] **Task 1.5**: Create `resources/views/components/variant-b/booking-finale.blade.php`:
  - Section index marker (`04 / Engagement` or `04 / Inquiries & Location`).
  - Architectural clinic location card with formatted address, coordinates, and Google Maps directions link.
  - Structured weekly operating schedule and 24/7 emergency protocol callout.
  - Understated consultation inquiry drawer / modal trigger or expandable dialogue alongside direct WhatsApp and telephone concierge links.

### Group 2: View Orchestration & Animation Tokens
- [x] **Task 2.1**: Refactor `resources/views/variants/b/index.blade.php`:
  - Compose `<x-variant-b.hero-editorial>`, `<x-variant-b.doctor-portrait>`, `<x-variant-b.treatment-row>`, `<x-variant-b.journey-timeline>`, and `<x-variant-b.booking-finale>`.
  - Pass data cleanly from `config('clinic')`.
  - Maintain clean section structure and keep total file size under 120 lines.
- [x] **Task 2.2**: Add animation tokens and reduced-motion rules in `resources/css/app.css`:
  - Define `@keyframes editorial-settle` (subtle translate-y and opacity fade, duration ~600ms, easing `cubic-bezier(0.16, 1, 0.3, 1)`).
  - Define utility class `.animate-editorial-settle`.
  - Enforce `@media (prefers-reduced-motion: reduce)` disabling animation.

### Group 3: Automated Testing & Verification
- [x] **Task 3.1**: Create `tests/Feature/VariantBTest.php`:
  - Test AC-1: Hero editorial renders headline, eyebrow badge, CTAs, and entrance animation markers.
  - Test AC-2: Doctor portrait renders Dr. Bhatti credentials, bio, philosophy quote, and all 4 accreditations in asymmetric grid.
  - Test AC-3: Treatments section renders all 4 categories as hairline rows with taglines, descriptions, and procedure highlights.
  - Test AC-4: Patient journey renders all 5 steps along vertical timeline with sequence numerals and assurance callouts.
  - Test AC-5: Booking finale renders location, hours, emergency protocol, and direct WhatsApp/Phone links.
  - Test AC-6: Touch targets for interactive buttons meet minimum accessibility height (>= 44px).
  - Test AC-7: Reduced motion styles are configured in CSS.
  - Test AC-8: Zero gradients exist across Variant B templates and stylesheets.
- [x] **Task 3.2**: Audit line counts across all created/edited files to ensure strict compliance with <= 300 lines limit.
- [x] **Task 3.3**: Run `git diff --check` to ensure no whitespace or formatting defects.
