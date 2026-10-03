# Feature Requirements: Phase 3 — Dual-Variant Testimonials & Patient Reviews Carousel

## 1. Context & Business Intent
As established in [specs/mission.md](file:///home/zeshan6a/Projects/dental_clinic/specs/mission.md), Dr. Bhatti & Associates (Prestigious Family Dental) requires a digital presence that instills calm healthcare authority and transparent patient reassurance across both design directions:
- **Variant A (Expressive / 2D Cutout)**: Layered 2D cutout surfaces, warm stone depth, and tactile clinical markers.
- **Variant B (Calm / Editorial)**: Restrained architectural typography, generous negative space, hairline rules, and quiet editorial elegance.

Patient testimonials and verified Google Reviews provide vital social proof, easing anxiety for prospective patients while showcasing clinical distinction across preventative, cosmetic, restorative, and pediatric care.

This specification introduces a dedicated, horizontally navigable Testimonials & Patient Reviews carousel to **both** design variants, maintaining content parity via centralized configuration while expressing each variant's distinct visual and interaction language.

---

## 2. Scope

### In-Scope
- [ ] **Centralized Review Data Architecture (`config/clinic.php`)**:
  - Structured placeholder review dataset stored centrally in `config/clinic.php` under `'reviews'`.
  - Each review contains:
    - `id`: Unique identifier (e.g. `'rev-01'`).
    - `patient_name`: Patient full name or attribution.
    - `rating`: Star rating integer (1 to 5).
    - `excerpt`: Concise visible excerpt clamped to fixed lines.
    - `full_text`: Complete patient testimonial narrative.
    - `source`: Source platform label (e.g., `'Google Reviews'`).
    - `source_url`: Real or placeholder Google Reviews URL.
    - `date`: Optional date or relative recency string (e.g., `'October 2025'`).
    - `treatment`: Optional clinical treatment category badge.
- [ ] **Variant A Testimonials Carousel (`<x-variant-a.testimonials-carousel>`)**:
  - Expressive 2D cutout aesthetic with warm stone card elevation (`bg-stone-warm-50`, `border-stone-warm-200`).
  - Tactile star rating presentation and warm accent borders.
  - Horizontally navigable carousel with restrained 2D stone navigation controls.
  - Strict excerpt line clamping (`line-clamp-3` or `line-clamp-4`) maintaining uniform card height.
- [ ] **Variant B Testimonials Carousel (`<x-variant-b.testimonials-carousel>`)**:
  - Calm architectural editorial aesthetic with hairline dividers (`border-stone-warm-300`) and quiet typography.
  - Minimal star rating indicators and generous whitespace.
  - Horizontally navigable carousel with understated, typographic or hairline arrow controls.
  - Strict excerpt line clamping maintaining uniform card height.
- [ ] **Desktop Popover & Secondary Review Panel**:
  - Hovering or keyboard-focusing a truncated review card triggers an independently hoverable secondary popover panel.
  - Pointer can move seamlessly from the card into the popover without the panel dismissing.
  - Internal scrolling (`overflow-y-auto`, constrained `max-h`) for lengthy review narratives.
  - Popover positioning remains within viewport boundaries without causing card reflow or carousel layout shift.
- [ ] **Mobile & Touch Drawer/Modal Interaction**:
  - Tap interaction opens the full-review narrative in an accessible modal or drawer sheet.
  - Clear, accessible dismiss actions: dedicated close button (`×`), backdrop tap, and `Escape` key handling.
- [ ] **External Source Linking**:
  - Google Reviews source link opens destination in a new browser tab with `target="_blank"` and `rel="noopener noreferrer"`.
- [ ] **Visual, Motion & Accessibility Standards**:
  - Zero CSS gradients across all review cards, controls, and popovers.
  - Smooth horizontal scrolling with snap points (`snap-x snap-mandatory`).
  - Full `prefers-reduced-motion: reduce` compliance (transitions disabled or instantaneous).
  - Touch targets >= 44x44px for all carousel navigation and dismiss buttons.

### Out-of-Scope (Non-Goals)
- No dynamic database storage, Eloquent models, or database migrations (frontend-only data in `config/clinic.php`).
- No live Google Places / Google Reviews API integration or real-time OAuth sync.
- No public review submission form or backend comment moderation system.
- No color gradients or looping carousel auto-play animations.

---

## 3. Constraints & Dependencies
- **Modularity Cap**: All tracked PHP, Blade, and CSS files must remain strictly under 300 lines.
- **Data Source**: Exclusively consume reviews from `config('clinic.reviews')`.
- **Zero Gradients**: Strictly no `linear-gradient`, `radial-gradient`, or Tailwind `bg-gradient-*` utilities.
- **Carousel Stability**: Card heights must remain locked; long reviews must never cause layout shift or expand adjacent cards.
- **Framework & Tooling**: Laravel 12 Blade components, Tailwind CSS v4 (`@tailwindcss/vite`), Vite 8, Vanilla JavaScript.

---

## 4. Acceptance Criteria

- [ ] **AC-1**: Given the application configuration, when `config('clinic.reviews')` is inspected, then a structured array of placeholder reviews is returned with `id`, `patient_name`, `rating` (1–5), `excerpt`, `full_text`, `source`, `source_url`, and optional `date` and `treatment`.
- [ ] **AC-2**: Given a visitor navigates to `/?variant=a`, when the Testimonials section renders, then a horizontally scrollable carousel displays review cards styled with Variant A's 2D cutout aesthetic, warm stone surfaces, star ratings, and restrained navigation buttons.
- [ ] **AC-3**: Given a visitor navigates to `/?variant=b`, when the Testimonials section renders, then a horizontally scrollable carousel displays review cards styled with Variant B's calm editorial aesthetic, hairline borders, star ratings, and understated navigation controls.
- [ ] **AC-4**: Given either variant carousel, when navigated via previous/next controls or touch swipe, then the carousel advances smoothly with snap alignment (`snap-x`), keeping card heights uniform and never breaking the horizontal layout.
- [ ] **AC-5**: Given a desktop user hovering or focusing a review card whose excerpt is clamped, when inspected, then an independently hoverable secondary popover panel displays the complete review narrative, scrolls internally for long content, stays within the viewport, and does not cause layout shift in the carousel.
- [ ] **AC-6**: Given a mobile/touch viewport, when a user taps a review card, then the complete review opens in a dedicated modal or drawer with an explicit close button, backdrop dismissal, and `Escape` key support.
- [ ] **AC-7**: Given any review card or full-review panel, when the Google Reviews source link is activated, then the destination opens in a new browser tab with `target="_blank"` and `rel="noopener noreferrer"`.
- [ ] **AC-8**: Given an audit of the Testimonials implementation across both variants, when checked, then zero CSS gradients exist, animations respect `prefers-reduced-motion: reduce`, touch targets meet >= 44px, and all files remain strictly under 300 lines.

---

## 5. Edge Cases & Error Handling
- **Long Patient Narratives**: Excerpts must be strictly clamped using Tailwind `line-clamp-3` or `line-clamp-4` to guarantee fixed card heights; full narratives scroll cleanly within the popover/modal without overflowing viewport edges.
- **Viewport Boundary Collision**: Popovers positioned near viewport edges (left or right extremes of the carousel) must clamp or align to avoid clipping outside the browser window.
- **Pointer Transition Flapping**: Popover hover state must include a proximity buffer or bridge so moving the pointer between the card and the popover does not cause flickering dismissal.
- **Keyboard Navigation**: Focusable review elements must support `Tab`, `Enter`/`Space` to open the full view, and `Escape` to dismiss without trapping focus.
- **Reduced Motion**: Carousel scroll snapping and popover transitions must settle instantaneously when `prefers-reduced-motion: reduce` is active.
