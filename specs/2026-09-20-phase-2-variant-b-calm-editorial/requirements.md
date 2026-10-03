# Feature Requirements: Phase 2 — Variant B: Calm / Editorial Prototype

## 1. Context & Business Intent
As established in [specs/mission.md](file:///home/zeshan6a/Projects/dental_clinic/specs/mission.md) and [specs/roadmap.md](file:///home/zeshan6a/Projects/dental_clinic/specs/roadmap.md), the Dr. Bhatti & Associates Dental Clinic POC requires evaluating two distinct frontend design directions within a single Laravel application.

Phase 0 established shared typography, design tokens, central content repository (`config/clinic.php`), master layouts, and persistent variant switcher. Phase 1 delivered Variant A (Expressive / 2D Cutout). Phase 2 delivers **Variant B (Calm / Editorial)** as a complete, interactive, high-fidelity frontend prototype.

Variant B embodies a calm, high-end editorial aesthetic referencing `dentaldesignsd.com`:
- Restrained, architectural typography with high contrast (large Source Serif 4 headlines, delicate italics, Work Sans body/labels).
- Generous negative space and subtle hairline dividers (`border-stone-warm-200`, `#E6E1D8`).
- Serene photography and architectural framing that breathes.
- Soft, fluid transitions that settle cleanly without looping.
- Zero gradients, relying entirely on calibrated tonal contrast and hairline rules.
- Complete content parity with Variant A, consuming identical data from `config/clinic.php`.

---

## 2. Scope

### In-Scope
- [x] **Chapter 1: Full-Bleed Architectural Editorial Hero Composition** (`<x-variant-b.hero-editorial>`):
  - Full-bleed opening composition with clinic value proposition and eyebrow badge ("Variant B • Calm Editorial Direction").
  - High-contrast typography with generous negative space and serene architectural framing.
  - Primary CTA ("Begin Consultation Dialogue" linking to WhatsApp) and secondary anchor link ("View Clinical Disciplines").
  - Fluid entrance transition (subtle fade/translate) settling cleanly within 600ms.
- [x] **Chapter 2: Clinical Director & Ethos Section** (`<x-variant-b.doctor-portrait>`):
  - Asymmetric 12-column editorial grid for Dr. Tariq Bhatti.
  - Section index marker (`01 / Ethos`).
  - Portraiture paired with clinical credentials (`DDS, FAGD, FICOI`) and leadership title.
  - Prominent philosophy quote block with delicate hairline border/divider.
  - Biographical narrative and 4 clinical accreditation markers (`FAGD`, `FICOI`, `AACD`, `Faculty Clinical Advisor`).
- [x] **Chapter 3: Treatments & Care Landscape Section** (`<x-variant-b.treatment-row>`):
  - Section index marker (`02 / Disciplines`).
  - Restrained hairline row layout (editorial list structure with subtle dividers rather than card grid).
  - 4 treatment categories:
    1. Preventative & Diagnostic Care
    2. Cosmetic Smile Architecture
    3. Restorative & Implant Dentistry
    4. Pediatric & Multi-Generational Dentistry
  - Each row presents category title, clinical tagline, description, and 4 procedural highlights.
  - Quiet hover interaction (subtle tone shift or hairline border focus).
- [x] **Chapter 4: The Patient Journey & Stories Section** (`<x-variant-b.journey-timeline>`):
  - Section index marker (`03 / Protocol` or `03 / Journey`).
  - Quiet vertical timeline with hairline connectors and architectural sequence numerals.
  - 5 sequential care steps (01 to 05) with step numbers, titles, and reassuring descriptions.
  - Architectural quote blocks / assurance callouts emphasizing unhurried dialogue, low-dose imaging, and transparent planning.
- [x] **Chapter 5: Clinic Location, Hours & Editorial Booking Finale** (`<x-variant-b.booking-finale>`):
  - Section index marker (`04 / Engagement` or `04 / Inquiries & Location`).
  - Architectural clinic location card with formatted address and direct Google Maps navigation link.
  - Structured weekly operating schedule and 24/7 emergency protocol callout.
  - Understated consultation inquiry drawer / modal trigger or expandable dialogue alongside direct WhatsApp and telephone concierge links.
- [x] **Visual, Motion & Accessibility Standards**:
  - Zero CSS gradients across all backgrounds, text, borders, and buttons.
  - All motion strictly hardware-accelerated (`transform`, `opacity`) and non-looping.
  - Full `prefers-reduced-motion: reduce` compliance (motion disabled or instantaneous).
  - Responsive design across mobile (320px+), tablet (768px+), and desktop (1024px+).
  - Minimum touch target size of 44x44px for all interactive links and buttons.

### Out-of-Scope (Non-Goals)
- No database migrations, Eloquent models, or database seeders.
- No backend appointment booking APIs or third-party CRM integrations.
- No modifications to Variant A components or styling.
- No color gradients on any element.
- No infinite looping or decorative animations.

---

## 3. Constraints & Dependencies
- **Modularity Cap**: All tracked PHP, Blade, and CSS files must remain strictly under 300 lines.
- **Data Source**: Exclusively consume clinical content from `config('clinic')`.
- **Zero Gradients**: Strictly no `linear-gradient`, `radial-gradient`, or Tailwind `bg-gradient-*` utilities.
- **Motion Restraint**: Animations must enter and settle; zero infinite loops.
- **Framework & Tooling**: Laravel 12 Blade components, Tailwind CSS v4 (`@tailwindcss/vite`), Vite 8.

---

## 4. Acceptance Criteria

- [x] **AC-1**: Given a visitor navigates to `/?variant=b`, when the page loads, then the full-bleed architectural editorial hero renders with the eyebrow badge ("Variant B • Calm Editorial Direction"), clinic headline, description, primary WhatsApp CTA ("Begin Consultation Dialogue"), secondary anchor link ("View Clinical Disciplines"), serene architectural imagery framing, and fluid entrance transition that settles cleanly.
- [x] **AC-2**: Given the Clinical Director & Ethos section renders, when inspected, then Dr. Tariq Bhatti's portraiture, name, credentials (`DDS, FAGD, FICOI`), title, philosophy quote, bio, and all 4 accreditation markers (`FAGD`, `FICOI`, `AACD`, `Faculty Clinical Advisor`) are presented in an asymmetric editorial grid layout.
- [x] **AC-3**: Given the Treatments & Care Landscape section renders, when inspected, then all 4 treatment categories (Preventative, Cosmetic, Restorative, Pediatric) render as restrained hairline rows with subtle dividers, category titles, taglines, full descriptions, and procedural highlights, with quiet hover interactions.
- [x] **AC-4**: Given The Patient Journey section renders, when inspected, then all 5 care steps (01 through 05) render sequentially along a quiet vertical timeline with architectural quote blocks / assurance callouts and step descriptions.
- [x] **AC-5**: Given the Clinic Location, Hours & Editorial Booking Finale renders, when inspected, then the clinic address, map container with directions link, complete weekly operating hours, emergency protocol notice, and consultation inquiry dialogue/drawer with direct WhatsApp and telephone concierge CTAs are displayed.
- [x] **AC-6**: Given any viewport from mobile (320px) to desktop (1440px+), when resized, then all Variant B components adapt responsively with generous whitespace, without horizontal overflow, and all interactive elements maintain >= 44px touch targets.
- [x] **AC-7**: Given a client with `prefers-reduced-motion: reduce` enabled, when viewing Variant B, then all CSS transitions and entrance animations are disabled or instantaneous.
- [x] **AC-8**: Given the rendered DOM and CSS of Variant B, when audited, then zero CSS gradients (`linear-gradient`, `radial-gradient`, `conic-gradient`, `bg-gradient-*`) are present.

---

## 5. Edge Cases & Error Handling
- **Missing or Partial Clinic Data**: Blade components must handle optional data gracefully using null-coalescing defaults.
- **Mobile Viewport Overflow**: Full-bleed sections and editorial imagery must be contained within `overflow-hidden` wrappers to prevent horizontal scrolling.
- **Accessibility & Contrast**: High-contrast typography on stone-warm surfaces must meet WCAG AA contrast (>= 4.5:1 for body text, >= 3:1 for large display text).
- **Reduced Motion**: All editorial transitions and hero entrance effects must instantaneously settle when `prefers-reduced-motion: reduce` is active.
