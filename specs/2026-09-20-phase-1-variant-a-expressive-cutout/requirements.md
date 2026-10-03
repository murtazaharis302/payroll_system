# Feature Requirements: Phase 1 — Variant A: Expressive / 2D Cutout Prototype

## 1. Context & Business Intent
As established in [specs/mission.md](file:///home/zeshan6a/Projects/dental_clinic/specs/mission.md) and [specs/roadmap.md](file:///home/zeshan6a/Projects/dental_clinic/specs/roadmap.md), the Dr. Bhatti & Associates Dental Clinic POC requires comparing two distinct frontend design directions within a single Laravel application.

Phase 0 established the shared typography, design tokens, central content repository (`config/clinic.php`), master layouts, and persistent variant switcher. Phase 1 brings **Variant A (Expressive / 2D Cutout)** to life as a complete, interactive, high-fidelity frontend prototype.

Variant A embodies an expressive, tactile dental care narrative:
- Layered 2D cutout surfaces and warm stone elevation.
- Directional entrance animations that settle cleanly without looping.
- Warm, reassuring clinical authority paired with transparent care steps.
- Zero gradients, relying entirely on calibrated tonal contrast and hairline rules.

---

## 2. Scope

### In-Scope
- [ ] **Chapter 1: Full-Width 2D Cutout Hero Composition** (`<x-variant-a.hero-cutout>`):
  - Full-width opening composition with clinic value proposition and eyebrow badge.
  - Layered 2D cutout geometric motifs evoking dental precision and architectural warmth.
  - Primary CTA ("Book Initial Consultation" linking to WhatsApp) and secondary CTA ("Explore Treatments" anchor link).
  - Subtle entrance animation (translate/opacity) that settles cleanly within 500ms.
- [ ] **Chapter 2: Clinical Leadership & Philosophy Section** (`<x-variant-a.doctor-card>`):
  - Layered warm stone card layout for Dr. Tariq Bhatti.
  - Clinical credentials (`DDS, FAGD, FICOI`) and leadership title.
  - Prominent philosophy quotation with warm accent border.
  - Full biographical narrative and 4 clinical accreditation badges (`FAGD`, `FICOI`, `AACD`, `Faculty Clinical Advisor`).
- [ ] **Chapter 3: Treatments & Care Landscape Section** (`<x-variant-a.treatment-tile>`):
  - Section header with category framing.
  - 4 expressive 2D treatment cards:
    1. Preventative & Diagnostic Care
    2. Cosmetic Smile Architecture
    3. Restorative & Implant Dentistry
    4. Pediatric & Multi-Generational Dentistry
  - Each card features category title, clinical tagline, description, and 4 highlighted procedures.
  - Subtle hover elevation (shadowless tonal shift or hairline border enhancement).
- [ ] **Chapter 4: The Patient Journey & Stories Section** (`<x-variant-a.journey-step>`):
  - 5 sequential care steps (01 to 05) with step numbers, titles, and reassuring descriptions.
  - Visual progression indicator connecting sequence nodes.
  - Patient trust markers emphasizing unhurried dialogue, low-dose imaging, and transparent planning.
- [ ] **Chapter 5: Location, Hours & Booking Finale** (`<x-variant-a.booking-finale>`):
  - Architectural clinic location card with formatted address and direct Google Maps navigation link.
  - Structured weekly operating schedule and 24/7 emergency protocol callout.
  - Final consultation booking card with direct WhatsApp link and phone concierge contact.
- [ ] **Visual, Motion & Accessibility Standards**:
  - Zero CSS gradients across all backgrounds, text, borders, and buttons.
  - All motion strictly hardware-accelerated (`transform`, `opacity`) and non-looping.
  - Full `prefers-reduced-motion: reduce` compliance (motion disabled or instantaneous).
  - Responsive design across mobile (320px+), tablet (768px+), and desktop (1024px+).
  - Minimum touch target size of 44x44px for all interactive links and buttons.

### Out-of-Scope (Non-Goals)
- No database migrations, Eloquent models, or database seeders.
- No backend appointment booking APIs or third-party CRM integrations.
- No Variant B editorial components (deferred to Phase 2).
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

- [ ] **AC-1**: Given a visitor navigates to `/?variant=a`, when the page loads, then the full-width 2D cutout hero renders with clinic headline, description, primary WhatsApp CTA, secondary Treatments anchor CTA, and settled entrance animation.
- [ ] **AC-2**: Given the Clinical Leadership section renders, when inspected, then Dr. Bhatti's name, credentials, title, philosophy quote, bio, and all 4 accreditation badges are presented in a layered warm stone card layout.
- [ ] **AC-3**: Given the Treatments & Care Landscape section renders, when inspected, then all 4 treatment categories (Preventative, Cosmetic, Restorative, Pediatric) render with their respective taglines, descriptions, and 4 clinical highlights, with subtle hover elevation transitions.
- [ ] **AC-4**: Given The Patient Journey section renders, when inspected, then all 5 care steps (01 through 05) render sequentially with numbered badges, titles, descriptions, and visual connective progression.
- [ ] **AC-5**: Given the Location, Hours & Booking Finale renders, when inspected, then the clinic address, complete weekly operating hours, emergency protocol, map container with directions link, and direct contact CTAs (WhatsApp and phone) are displayed.
- [ ] **AC-6**: Given any viewport from mobile (320px) to desktop (1440px+), when resized, then all Variant A components adapt responsively without horizontal overflow and all interactive elements maintain >= 44px touch targets.
- [ ] **AC-7**: Given a client with `prefers-reduced-motion: reduce` enabled, when viewing Variant A, then all CSS entrance animations and transitions are disabled or instantaneous.
- [ ] **AC-8**: Given the rendered DOM and CSS of Variant A, when audited, then zero CSS gradients (`linear-gradient`, `radial-gradient`, `conic-gradient`, `bg-gradient-*`) are present.

---

## 5. Edge Cases & Error Handling
- **Missing or Partial Clinic Data**: Blade components must handle optional data gracefully using null-coalescing defaults.
- **Mobile Viewport Overflow**: 2D cutout layers and decorative elements must be contained within `overflow-hidden` wrappers to prevent horizontal scrolling.
- **Accessibility & Contrast**: All text on warm stone surfaces must meet WCAG AA contrast (>= 4.5:1 for body text, >= 3:1 for large text).
- **Reduced Motion**: Floating switcher transitions and hero entrance effects must instantaneously settle when `prefers-reduced-motion: reduce` is active.
