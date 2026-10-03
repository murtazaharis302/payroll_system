# Project Roadmap & Implementation Phases

## Phasing Strategy
The development of the Dr. Bhatti & Associates Dental Clinic POC follows a Spec-Driven Development sequence. Each phase delivers an incremental, verified slice of functionality, prioritizing frontend architectural isolation, content equivalence, and client presentation readiness.

## Phase Overview

| Phase | Title | Specification Path | Acceptance Criteria | Specification Status | Implementation Status | Validation Status |
|---|---|---|---|---|---|---|
| Phase 0 | Foundation, Shared IA & Switcher Scaffolding | [Phase 0 Spec](file:///home/zeshan6a/Projects/dental_clinic/specs/2026-09-20-phase-0-foundation-ia-switcher/) | AC-1 to AC-6 | Ready | Implemented | Validated |
| Phase 1 | Variant A: Expressive / 2D Cutout Prototype | [Phase 1 Spec](file:///home/zeshan6a/Projects/dental_clinic/specs/2026-09-20-phase-1-variant-a-expressive-cutout/) | AC-1 to AC-8 | Ready | Implemented | Validated |
| Phase 2 | Variant B: Calm / Editorial Prototype | [Phase 2 Spec](file:///home/zeshan6a/Projects/dental_clinic/specs/2026-09-20-phase-2-variant-b-calm-editorial/) | AC-1 to AC-8 | Ready | Implemented | Validated |
| Phase 3 | Dual-Variant Testimonials & Patient Reviews Carousel | [Phase 3 Spec](file:///home/zeshan6a/Projects/dental_clinic/specs/2026-09-21-phase-3-testimonials-carousel/) | AC-1 to AC-8 | Ready | Implemented | Validated |
| Phase 4 | Motion Foundation & Variant A Site-Wide Choreography | [Phase 4 Spec](2026-09-21-phase-4-motion-foundation-variant-a/) | AC-1 to AC-8 | Ready | Implemented | Validated |
| Phase 5 | Variant B Calm Editorial Site-Wide Motion | [Phase 5 Spec](2026-09-21-phase-5-variant-b-editorial-motion/) | AC-1 to AC-12 | Ready | Implemented | Validated |
| Phase 6 | Cross-Variant Polish, Accessibility & Presentation Audit | [Phase 6 Spec](2026-09-21-phase-6-cross-variant-polish-audit/) | AC-1 to AC-10 | Ready | Implemented | Validated (Browser Evidence Waived) |
| Phase 7 | Global Animation Guidance Skill | [Phase 7 Spec](2026-09-21-phase-7-global-animation-guidance-skill/) | AC-1 to AC-6 | Ready | Implemented | Validated |
| Phase 8 | Multi-Page Shared Foundation | [Phase 8 Spec](2026-09-21-phase-8-multi-page-shared-foundation/) | AC-1 to AC-8 | Ready | Implemented | Pending |
| Phase 9 | Variant A Multi-Page Experience | [Phase 9 Spec](2026-09-21-phase-9-variant-a-multi-page-experience/) | AC-1 to AC-7 | Ready | Implemented | Validated |
| Phase 10 | Variant B Multi-Page Experience | [Phase 10 Spec](2026-09-21-phase-10-variant-b-multi-page-experience/) | AC-1 to AC-7 | Ready | Implemented | Validated |
| Phase 11 | Cross-Variant Motion, Navigation & Preferences | [Phase 11 Spec](2026-09-22-phase-11-cross-variant-preference-controls/) | AC-1 to AC-9 | Ready | Implemented | Validated |

---

### Phase 0: Foundation, Shared IA & Switcher Scaffolding
- **Specification**: [specs/2026-09-20-phase-0-foundation-ia-switcher/](file:///home/zeshan6a/Projects/dental_clinic/specs/2026-09-20-phase-0-foundation-ia-switcher/)
- **Acceptance Criteria**: AC-1 through AC-6
- **Status**: Specification: Ready | Implementation: Implemented | Validation: Validated
- [x] **Task 0.1**: Configure typography (Source Serif 4, Work Sans) and core color palette in Tailwind CSS v4 (`resources/css/app.css` & `vite.config.js`).
- [x] **Task 0.2**: Create central clinical content repository (`config/clinic.php`) containing all clinic copy, doctor credentials, treatment details, patient journey steps, contact info, and clinic hours.
- [x] **Task 0.3**: Create master layout shell (`resources/views/layouts/app.blade.php`) and persistent floating variant switcher (`resources/views/components/shared/variant-switcher.blade.php`) supporting `?variant=a|b` and session persistence.
- [x] **Task 0.4**: Implement web route in `routes/web.php` to resolve active variant and route to appropriate view.
- **Deliverable / Verification**: Navigating to `/?variant=a` and `/?variant=b` renders basic scaffolded layout shells with working switcher toolbar and shared header/footer.

---

### Phase 1: Variant A: Expressive / 2D Cutout Prototype
- **Specification**: [specs/2026-09-20-phase-1-variant-a-expressive-cutout/](file:///home/zeshan6a/Projects/dental_clinic/specs/2026-09-20-phase-1-variant-a-expressive-cutout/)
- **Acceptance Criteria**: AC-1 through AC-8
- **Status**: Specification: Ready | Implementation: Implemented | Validation: Validated
- [x] **Task 1.1**: Build full-width 2D cutout hero composition with clinic value proposition, primary CTAs, and entrance motion that settles cleanly.
- [x] **Task 1.2**: Implement Dr. Bhatti philosophy & credentials section utilizing layered warm stone cards and clinical accreditation markers.
- [x] **Task 1.3**: Implement Treatments & Care Landscape section featuring expressive 2D treatment cards with calm hover elevations.
- [x] **Task 1.4**: Implement The Patient Journey & Stories section with numbered sequence nodes and patient trust indicators.
- [x] **Task 1.5**: Implement Clinic Location, Hours, and Booking Finale with architectural map integration and direct WhatsApp/Phone CTAs.
- **Deliverable / Verification**: Variant A is fully interactive, responsive across all breakpoints, contains zero gradients, and all animations settle without looping.

---

### Phase 2: Variant B: Calm / Editorial Prototype
- **Specification**: [specs/2026-09-20-phase-2-variant-b-calm-editorial/](file:///home/zeshan6a/Projects/dental_clinic/specs/2026-09-20-phase-2-variant-b-calm-editorial/)
- **Acceptance Criteria**: AC-1 through AC-8
- **Status**: Specification: Ready | Implementation: Implemented | Validation: Validated
- [x] **Task 2.1**: Build full-bleed architectural editorial hero composition inspired by `dentaldesignsd.com`, featuring serene photography and high-contrast typography.
- [x] **Task 2.2**: Implement Dr. Bhatti editorial profile section pairing portraiture with clinical director ethos in an asymmetric grid.
- [x] **Task 2.3**: Implement Treatments & Care Landscape using restrained hairline rows, subtle dividers, and minimal typographic emphasis.
- [x] **Task 2.4**: Implement The Patient Journey & Stories using a quiet vertical timeline and architectural quote blocks.
- [x] **Task 2.5**: Implement Clinic Location, Hours, and Editorial Booking Finale with understated consultation inquiry drawer and direct WhatsApp action.
- **Deliverable / Verification**: Variant B delivers a distinctly calmer, high-end editorial experience using identical clinical content to Variant A.

---

### Phase 3: Dual-Variant Testimonials & Patient Reviews Carousel
- **Specification**: [specs/2026-09-21-phase-3-testimonials-carousel/](file:///home/zeshan6a/Projects/dental_clinic/specs/2026-09-21-phase-3-testimonials-carousel/)
- **Acceptance Criteria**: AC-1 through AC-8
- **Status**: Specification: Ready | Implementation: Implemented | Validation: Validated
- [x] **Task 3.1**: Define structured placeholder reviews schema and dataset in `config/clinic.php` with patient name, rating, excerpt, full text, source, source URL, date, and treatment.
- [x] **Task 3.2**: Build Variant A Testimonials Carousel component (`<x-variant-a.testimonials-carousel>`) featuring 2D cutout warm stone cards, star ratings, and restrained navigation buttons.
- [x] **Task 3.3**: Build Variant B Testimonials Carousel component (`<x-variant-b.testimonials-carousel>`) featuring calm editorial styling, hairline borders, and understated navigation controls.
- [x] **Task 3.4**: Implement interactive popover (desktop hover/focus with internal scrolling) and accessible modal (mobile tap with explicit close button) for complete review narratives.
- [x] **Task 3.5**: Integrate carousels into both variant index views between Patient Journey and Booking Finale, and create automated feature test suite covering AC-1 through AC-8.
- **Deliverable / Verification**: Both variants feature interactive, horizontally scrollable testimonials carousels with uniform card heights, locked excerpts, desktop popovers, mobile modals, and external Google Reviews links.

---

### Phase 4: Motion Foundation & Variant A Site-Wide Choreography
- **Specification**: [specs/2026-09-21-phase-4-motion-foundation-variant-a/](2026-09-21-phase-4-motion-foundation-variant-a/)
- **Acceptance Criteria**: AC-1 through AC-8
- **Status**: Specification: Ready | Implementation: Implemented | Validation: Validated
- [x] **Task 4.1**: Add Motion Mini, one progressively enhanced observer-driven semantic hook system, and reusable motion CSS primitives.
- [x] **Task 4.2**: Apply expressive, layered choreography to every Variant A and shared page block, including review, booking, footer, and switcher interactions.
- [x] **Task 4.3**: Verify Variant A with focused tests and local Playwright across desktop, mobile, reduced-motion, keyboard, and JavaScript-disabled flows.
- **Deliverable / Verification**: Variant A has complete controlled site-wide motion while shared content remains visible without JavaScript.

### Phase 5: Variant B Calm Editorial Site-Wide Motion
- **Specification**: [specs/2026-09-21-phase-5-variant-b-editorial-motion/](2026-09-21-phase-5-variant-b-editorial-motion/)
- **Acceptance Criteria**: AC-1 through AC-12
- **Status**: Specification: Ready | Implementation: Implemented | Validation: Validated
- [x] **Task 5.1**: Perform and record the required Playwright-based Dental Design SD motion audit before source changes.
- [x] **Task 5.2**: Apply the central motion system to every Variant B and shared page block using a distinct calm editorial profile, then recompose Variant B into an original reference-informed full-bleed editorial hierarchy.
- [x] **Task 5.3**: Complete the fresh targeted independent QA audit and lifecycle evidence before Phase 5 can be marked implemented.
- **Deliverable / Verification**: Variant B is continuously but quietly animated, reference-informed without copying, and visibly slower/softer than Variant A.

### Phase 6: Cross-Variant Polish, Accessibility & Presentation Audit
- **Specification**: [specs/2026-09-21-phase-6-cross-variant-polish-audit/](2026-09-21-phase-6-cross-variant-polish-audit/)
- **Acceptance Criteria**: AC-1 through AC-10
- **Status**: Specification: Ready | Implementation: Implemented | Validation: Validated (Browser Evidence Waived)
- [x] **Task 6.1**: Validate strict `prefers-reduced-motion: reduce` behavior across both variants, verifying instantaneous or disabled animations and transitions.
- [x] **Task 6.2**: Audit color contrast ratios to ensure WCAG 2.1 AA compliance across all text, interactive buttons, stone cards, and dark contrast bands.
- [x] **Task 6.3**: Verify responsive performance, zero horizontal overflow, and touch targets (>= 44px) across mobile, tablet, and desktop viewports.
- [x] **Task 6.4**: Ensure 100% of tracked text and code files strictly adhere to the <= 300-line modularity constraint.
- [x] **Task 6.5**: Prepare client walkthrough documentation demonstrating key design differences, interaction principles, and clinical positioning.
- [x] **Task 6.6**: Repair the non-landing Variant A multi-page layout rhythm and verify responsive visual integrity without changing the landing page.
- [x] **Task 6.7**: Complete the initial independent QA audit. QA-F1 browser-harness evidence was explicitly accepted by user instruction; no targeted re-audit was requested.
- **Deliverable / Verification**: Both variants pass recorded accessibility, modularity, and responsiveness checks, with the Phase 6 Variant A multi-page browser-evidence limitation explicitly waived by user merge instruction.

### Phase 7: Global Animation Guidance Skill
- **Specification**: [specs/2026-09-21-phase-7-global-animation-guidance-skill/](2026-09-21-phase-7-global-animation-guidance-skill/)
- **Acceptance Criteria**: AC-1 through AC-6
- **Status**: Specification: Ready | Implementation: Implemented | Validation: Validated
- [x] **Task 7.1**: Inspect the validated motion system and create a globally available Codex skill through `skill-creator`.
- [x] **Task 7.2**: Encode distinct expressive and calm profiles, safe shared-component handling, and centralized architecture rules.
- [x] **Task 7.3**: Validate discovery and dry-run guidance for Variant A, Variant B, and ambiguous shared components.
- **Deliverable / Verification**: Future Codex work can discover a global, inspect-first skill that selects the correct motion profile and enforces the validated safeguards.

### Phase 8: Multi-Page Shared Foundation
- **Specification**: [specs/2026-09-21-phase-8-multi-page-shared-foundation/](2026-09-21-phase-8-multi-page-shared-foundation/)
- **Acceptance Criteria**: AC-1 through AC-8
- **Status**: Specification: Ready | Implementation: Implemented | Validation: Pending
- [x] **Task 8.1**: Establish the shared public route map, resource-slug lookup, route-preserving `?variant=a|b` switching, and per-page metadata contract.
- [x] **Task 8.2**: Centralize editable content and placeholder/approval flags; implement reusable shared navigation, footer, metadata, detail, FAQ, review, contact, and client-only form contracts.
- [x] **Task 8.3**: Add route, metadata, content, navigation, form, no-JS, reduced-motion, and accessibility verification for both variants.
- **Deliverable / Verification**: Every required pathname is routeable in both variants with shared content, honest placeholders, accessible IA, and frontend-only consultation behavior.

### Phase 9: Variant A Multi-Page Experience
- **Specification**: [specs/2026-09-21-phase-9-variant-a-multi-page-experience/](2026-09-21-phase-9-variant-a-multi-page-experience/)
- **Acceptance Criteria**: AC-1 through AC-7
- **Status**: Specification: Ready | Implementation: Implemented | Validation: Validated
- [x] **Task 9.1**: Compose all required informational, utility, service, team, review, and legal routes with reusable expressive Variant A components and the Phase 8 shared contracts.
- [x] **Task 9.2**: Extend settled expressive Motion Mini choreography, responsive treatment, and accessibility across every new Variant A page.
- [x] **Task 9.3**: Verify complete Variant A journeys, detail templates, reviews, mock contact form, variant switching, mobile, reduced-motion, and JavaScript-disabled flows (automated/QA evidence and manual acceptance complete).
- **Deliverable / Verification**: Variant A is a complete expressive, layered, premium medical website without redesigning its existing homepage or diverging from shared facts.

### Phase 10: Variant B Multi-Page Experience
- **Specification**: [specs/2026-09-21-phase-10-variant-b-multi-page-experience/](2026-09-21-phase-10-variant-b-multi-page-experience/)
- **Acceptance Criteria**: AC-1 through AC-7
- **Status**: Specification: Ready | Implementation: Implemented | Validation: Validated
- [x] **Task 10.1**: Compose all required informational, utility, service, team, review, and legal routes with reusable calm Variant B components and the Phase 8 shared contracts.
- [x] **Task 10.2**: Extend Phase 5’s restrained editorial Motion Mini profile, responsive treatment, and accessibility across every new Variant B page.
- [x] **Task 10.3**: Verify complete Variant B journeys, detail templates, reviews, mock contact form, variant switching, mobile, reduced-motion, and JavaScript-disabled flows.
- **Deliverable / Verification**: Variant B is a complete calm editorial clinic website, original and reference-informed without copying or diverging from shared facts.

### Phase 11: Cross-Variant Motion, Navigation & Preferences
- **Specification**: [specs/2026-09-22-phase-11-cross-variant-preference-controls/](2026-09-22-phase-11-cross-variant-preference-controls/)
- **Acceptance Criteria**: AC-1 through AC-9
- **Status**: Specification: Ready | Implementation: Implemented | Validation: Validated
- [x] **Task 11.1**: Extend the existing landing-page motion languages across all public views using the one centralized Motion Mini runtime, while retaining no-JS and reduced-motion safety.
- [x] **Task 11.2**: Remove the two named landing-hero identifier labels without changing meaningful content or controls.
- [x] **Task 11.3**: Make shared navigation and every rendered view deliberately mobile-first, accessible, and free of overflow from 320px through desktop.
- [x] **Task 11.4**: Add path-preserving palette and typography preference state plus an accessible shared visual switcher for the current and supplied design systems.
- **Deliverable / Verification**: Stakeholders can compare both visual directions, four palettes, and two type systems on every responsive route while the site remains accessible, stable, and content-equivalent.
