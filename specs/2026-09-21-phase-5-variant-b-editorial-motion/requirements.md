# Feature Requirements: Phase 5 — Variant B Calm Editorial Motion

## 1. Context & Business Intent

Variant B must feel alive throughout the full page without becoming the less animated counterpart to Variant A. Its motion is a distinct, slow-medium editorial language informed by direct interactive observation of `dentaldesignsd.com`, not copied markup or layout.

**Lifecycle:** Specification: Ready | Implementation: In Progress | Validation: Pending.

## 2. Scope

### In-Scope

- [ ] Before code changes, use Playwright to repeatedly scroll and interact with `https://www.dentaldesignsd.com/` at desktop and, where possible, mobile sizes; record an internal motion audit under this specification directory.
- [ ] Record observed duration and distance ranges, easing/stagger character, image and text reveal styles, interaction behavior, thresholds, scroll-image response, and any mobile differences. The audit must describe observations, not reproduce the site.
- [ ] Apply Phase 4’s central semantic motion system to every Variant B and shared presentation element: header, hero, doctor, treatments, journey, testimonials, booking/location, footer, and switcher.
- [ ] Use refined grouped-line mask or 8–14px rise for headlines, gentle hairline growth, soft image masks or scale 1.02 to 1, and slower easing than Variant A.
- [ ] Choreograph Variant B treatment rows, timeline line/nodes, review carousel and full-review panel, map, hours, footer, controls, links, and switcher with light, quiet, manual-first interaction.
- [ ] Preserve `prefers-reduced-motion`, no-JavaScript visibility, responsive behavior, visible focus, no gradients, and no continuous autoplay or marquee.

### Out-of-Scope

- Changing Variant A timing or component composition except for defect fixes in shared motion architecture.
- Copying Dental Design SD’s layout, HTML, assets, or source code.
- New smooth-scroll, parallax, pinning, or external animation packages beyond the Phase 4 Motion Mini dependency.

## 3. Constraints & Dependencies

- Phase 4’s shared motion system must be implemented and verified before this phase begins.
- The reference audit is a blocking implementation prerequisite and must precede Variant B code edits.
- Use one observer, hardware-accelerated properties, compact section groups, and animations that generally run once.
- Editorial movement remains smaller and slower than Variant A: headlines about 8–14px; images use restrained masking/scale, never a right-side-card fly-in.

## 4. Acceptance Criteria

- [ ] **AC-1**: Given Phase 5 begins, when the reference is examined with Playwright before code changes, then a versioned internal audit records the observed motion qualities and how they inform—not copy—Variant B.
- [ ] **AC-2**: Given a visitor loads `/?variant=b`, when the header and hero settle, then the label, headline, copy, CTAs, secondary metadata, and any full-bleed image reveal in a short, refined sequence without a loading screen or per-word animation.
- [ ] **AC-3**: Given any Variant B section enters the viewport, when its choreography runs, then the section number/eyebrow, hairline, title, description, visual/group, and action reveal in the established editorial hierarchy.
- [ ] **AC-4**: Given the doctor, treatment rows, and patient journey render, when scrolled into view, then portraits/images softly unmask, accreditation rows and treatment content gently sequence, and timeline line/nodes progress without jumps or card-style elevation.
- [ ] **AC-5**: Given testimonials and location/booking render, when they enter or are interacted with, then cards and content have quiet hierarchy, carousel movement feels directional and manual-first, the review panel does not disturb layout, and map/hours/emergency/CTA/footer reveal softly.
- [ ] **AC-6**: Given interactive controls are hovered, focused, pressed, or touched, when feedback is shown, then it uses subtle surface, underline, border, or press response; focus remains visible and no control requires hover alone.
- [ ] **AC-7**: Given reduced motion or JavaScript failure, when Variant B is used, then content is immediate and usable, with all transforms, masks, line drawing, and stagger delay disabled for reduced motion.
- [ ] **AC-8**: Given Variant B is evaluated in local Playwright desktop and mobile flows and compared interactively with the reference audit, when fully scrolled and operated, then it remains smoother/slower than Variant A, has no overflow or nuisance retriggers, and reflects the audit’s rhythm without copying the reference.

## 5. Edge Cases & Error Handling

- If the external reference cannot be reached, capture the failure in the audit and pause implementation for direction rather than inventing reference findings.
- The carousel remains manual-first; it must not start a continuous autoplay sequence.
- Mobile keeps timelines and reveals simpler so delays do not obscure content below the fold.

## 6. User-Directed Reference Alignment Amendment (2026-09-21)

### Context & Scope Override

After the Playwright audit, the user directed that Phase 5 should make Variant B substantially closer to the reference's visual composition, in this same phase. This amendment supersedes the earlier narrow “motion rhythm only” interpretation while retaining the project’s clinical content, accessibility, and original implementation.

### In-Scope Additions

- [ ] Recompose Variant B with an original, reference-informed visual grammar: a full-bleed photographic hero, compact overlaid navigation and actions, alternating image/editorial content blocks, a dark information band, restrained service and testimonial sequences, a photographic/location interlude, and a high-contrast footer.
- [ ] Use only this project’s own assets, clinical content, and original Blade/CSS/JavaScript. Do not copy the reference site’s source code, text, logos, photography, or branded marks.
- [ ] Preserve all existing Variant B information and controls: doctor details, treatment highlights, patient journey, reviews, carousel controls, review modal, booking/location details, direct contact methods, and the variant switcher.
- [ ] Make the reference-informed composition responsive: desktop may use editorial pairings and multi-column sequences; mobile must remain an immediately readable single-column flow with concise controls.

### New Acceptance Criteria

- [ ] **AC-9**: Given a visitor opens `/?variant=b`, when the hero loads, then an original full-bleed clinical image composition, compact header, legible overlaid identity, headline, and two direct actions establish a visual hierarchy materially closer to the audited reference without reusing its assets or copy.
- [ ] **AC-10**: Given the visitor scrolls through Variant B, when doctor, treatment, journey, and review content appears, then the page uses a coherent reference-informed sequence of photographic/editorial pairings, hairline-led service content, dark contrast bands, and manual testimonial controls while retaining all original clinic information.
- [ ] **AC-11**: Given booking, location, and footer content appears, when the visitor reaches the final page sections, then an original image/location interlude, direct consultation actions, compact contact details, and a dark high-contrast footer complete the reference-informed visual rhythm without hiding or reordering required information.
- [ ] **AC-12**: Given Variant B is evaluated at desktop and mobile sizes, with reduced motion, keyboard navigation, and JavaScript disabled, when its updated composition is exercised, then it has no horizontal overflow, all controls retain visible focus and touch targets, reviews remain manual-first, and content remains immediate and usable when motion or JavaScript is unavailable.
