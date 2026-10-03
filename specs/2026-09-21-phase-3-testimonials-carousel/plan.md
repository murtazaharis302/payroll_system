# Implementation Plan: Phase 3 — Dual-Variant Testimonials & Patient Reviews Carousel

## Overview & Architecture Approach
This plan introduces a dedicated Testimonials & Patient Reviews carousel to both Variant A and Variant B of the Dr. Bhatti & Associates Dental Clinic POC.

The architecture centers on:
1. **Central Data Manifest**: Structured placeholder reviews in `config/clinic.php`.
2. **Variant A Carousel (`<x-variant-a.testimonials-carousel>`)**: Expressive 2D cutout styling, warm stone surfaces, tactile navigation controls, and clamped excerpts.
3. **Variant B Carousel (`<x-variant-b.testimonials-carousel>`)**: Calm architectural editorial styling, hairline borders, understated navigation controls, and clamped excerpts.
4. **Interaction Engine**:
   - Desktop: Popover with hover bridge, internal scrolling, and viewport boundary safety without layout shift.
   - Mobile: Accessible modal/drawer sheet triggered on tap with explicit close button and backdrop dismissal.
5. **View Integration**: Inserting the Testimonials section between Chapter 4 (Patient Journey) and Chapter 5 (Location & Booking Finale) in `resources/views/variants/a/index.blade.php` and `resources/views/variants/b/index.blade.php`.

---

## Task Groups

### Group 1: Review Data Architecture & Configuration
- [x] **Task 1.1**: Define structured placeholder review schema and records in `config/clinic.php`:
  - Add `'reviews'` array containing at least 5 diverse patient reviews across preventative, cosmetic, restorative, and pediatric care.
  - Populate each record with `id`, `patient_name`, `rating` (integer 1-5), `excerpt`, `full_text`, `source` ("Google Reviews"), `source_url` (with valid target or placeholder), `date`, and `treatment`.
- [x] **Task 1.2**: Define review helper or view data methods ensuring clean consumption across Blade components with null-safe fallbacks.

### Group 2: Variant A Expressive 2D Testimonials Carousel
- [x] **Task 2.1**: Create `resources/views/components/variant-a/testimonials-carousel.blade.php`:
  - Section header with category badge ("Patient Reassurance & Stories") and expressive headline.
  - Horizontal scroll container with CSS snap points (`snap-x snap-mandatory`).
  - Warm stone review cards (`bg-stone-warm-50`, `border-stone-warm-200`) with star ratings, patient attribution, and date.
  - Line-clamped excerpt (`line-clamp-3`) ensuring locked card heights.
- [x] **Task 2.2**: Implement restrained 2D stone navigation controls (previous/next buttons) with >= 44px touch targets and accessible `aria-label` attributes.

### Group 3: Variant B Calm Editorial Testimonials Carousel
- [x] **Task 3.1**: Create `resources/views/components/variant-b/testimonials-carousel.blade.php`:
  - Section header with quiet editorial framing and high-contrast typography.
  - Horizontal scroll container with CSS snap points (`snap-x snap-mandatory`).
  - Restrained editorial review cards with hairline borders (`border-stone-warm-300`), subtle star ratings, patient attribution, and date.
  - Line-clamped excerpt (`line-clamp-3`) ensuring locked card heights.
- [x] **Task 3.2**: Implement understated editorial navigation controls (hairline arrows or text controls) with >= 44px touch targets.

### Group 4: Interactive Popover & Mobile Modal Engine
- [x] **Task 4.1**: Create shared or modular review detail component / script (`resources/views/components/shared/review-modal.blade.php` or dedicated component script):
  - Desktop popover: Appears on card hover or focus, remains open when hovering into the popover, scrolls internally (`max-h-64 overflow-y-auto`), and clamps within viewport without shifting carousel cards.
  - Mobile modal/drawer: Opens on tap, provides clear `×` close button, backdrop tap to dismiss, and `Escape` key handler.
- [x] **Task 4.2**: Verify Google Reviews source link opens in a new tab (`target="_blank" rel="noopener noreferrer"`).

### Group 5: View Orchestration & Regression Testing
- [x] **Task 5.1**: Integrate `<x-variant-a.testimonials-carousel>` into `resources/views/variants/a/index.blade.php` and `<x-variant-b.testimonials-carousel>` into `resources/views/variants/b/index.blade.php`.
- [x] **Task 5.2**: Create `tests/Feature/TestimonialsCarouselTest.php`:
  - Test AC-1: `config('clinic.reviews')` returns valid structured review records.
  - Test AC-2: Variant A renders testimonials carousel with 2D cutout classes and controls.
  - Test AC-3: Variant B renders testimonials carousel with editorial hairline classes and controls.
  - Test AC-4: Review cards contain line-clamp classes and maintain horizontal carousel structure.
  - Test AC-5 & AC-6: Full-review narrative triggers and modal/popover markup exist with accessible dismiss attributes.
  - Test AC-7: Google Reviews source links contain `target="_blank"` and `rel="noopener noreferrer"`.
  - Test AC-8: Zero gradients exist, reduced-motion styles apply, touch targets >= 44px, and file size <= 300 lines.
- [x] **Task 5.3**: Verify existing test suites (`tests/Feature/VariantATest.php`, `tests/Feature/VariantBTest.php`, `tests/Feature/VariantResolutionTest.php`) continue to pass without regressions.
