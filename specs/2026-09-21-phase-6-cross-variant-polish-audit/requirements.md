# Feature Requirements: Phase 6 — Cross-Variant Polish, Accessibility & Presentation Audit

## 1. Context & Business Intent

Both Variant A (Expressive / 2D Cutout) and Variant B (Calm / Editorial) prototypes, their shared foundation, testimonials carousels, and motion choreographies are fully implemented and validated. Before presenting the dual-variant prototype to clinical stakeholders and locking down the global animation guidance skill (Phase 7), the clinic POC requires a comprehensive cross-variant polish, accessibility, and presentation audit.

This audit ensures:
- Full accessibility compliance under WCAG 2.1 AA standards (color contrast, heading hierarchy, keyboard navigation, visible focus indicators).
- Strict adherence to `prefers-reduced-motion: reduce` across both design systems with instantaneous, zero-motion presentation.
- Responsive touch target compliance (>= 44x44px) and zero horizontal overflow across mobile (320px+), tablet, and desktop viewports.
- 100% adherence to the repository's 300-line modularity cap.
- High-quality client presentation and walkthrough documentation demonstrating the contrasting design languages and clinical positioning.

**Lifecycle:** Specification: Ready | Implementation: Implemented | Validation: Validated (Browser Evidence Waived).

## Multi-Page Remediation Addition: Variant A Layout Polish

### Context & Defect Analysis

Phase 9 introduced Variant A public pages beyond the landing page. Manual review found those pages visually inconsistent with the settled landing-page composition: sections can feel crowded or disconnected, and their responsive rhythm does not consistently preserve the intended premium 2D-cutout hierarchy. This addition closes Phase 6 only after the non-landing Variant A layouts have a coherent container, spacing, typography, image, and responsive treatment.

### In-Scope Remediation

- [x] Audit and repair the layout of every non-landing Variant A public route: about, services and service details, team and team details, reviews, contact, FAQ, patient journey, and legal/information pages.
- [x] Establish a consistent expressive page rhythm using reusable layout primitives or components: page-intro-to-content transition, maximum readable line lengths, intentional section gaps, card padding, and responsive image framing.
- [x] Preserve the existing landing page, shared route/content contracts, approved copy, Variant A motion system, and accessible interaction behavior.
- [x] Verify the remediated routes with automated rendering, build, formatting, and modularity evidence. Browser viewport verification was explicitly waived by the user at merge time because no browser harness is configured.

### Out-of-Scope Remediation

- Redesigning the Variant A landing page.
- Adding routes, changing clinic facts/copy, or using unprovided client imagery.
- Changing Variant B page composition except where a shared primitive requires a non-visual regression check.

### New Acceptance Criteria

- [x] **AC-8**: Given a visitor opens any non-landing Variant A public route, when the page renders, then its content uses a consistent expressive layout rhythm with deliberate page-intro spacing, readable measure, section separation, card padding, and balanced image/content compositions. Automated rendering coverage passed; browser evidence was explicitly waived by the user.
- [x] **AC-9**: Given the full Variant A non-landing route map is rendered, then the shared layout uses overflow containment and responsive spacing classes. The required browser viewport sweep was explicitly waived by the user because no browser harness is configured.
- [x] **AC-10**: Given the Variant A multi-page layout correction is applied, when the landing page and shared contracts are verified, then the landing page remains visually and behaviorally unchanged and the existing reduced-motion, keyboard-focus, and touch-target guarantees continue to hold.

## 2. Scope

### In-Scope

- [x] Audit and enforce strict `prefers-reduced-motion: reduce` behavior across both `/?variant=a` and `/?variant=b`: verify immediate content visibility, 0s transitions, disabled transforms, and disabled stagger delays.
- [x] Audit color contrast ratios across all text, links, buttons, badges, stone cards, and dark contrast bands against WCAG 2.1 AA standards (>= 4.5:1 for normal text, >= 3:1 for large text and interactive UI components).
- [x] Audit and verify interactive touch targets (>= 44x44px) and tap clearance for all CTAs, navigation links, phone/WhatsApp links, carousel arrows, modal close buttons, and switcher buttons on mobile viewports.
- [x] Verify keyboard navigation and visible focus rings (`:focus-visible`) across all interactive elements with no focus traps.
- [x] Audit responsive viewport layouts across 320px, 375px, 768px, 1024px, 1280px, and 1440px viewports ensuring zero horizontal overflow (`scrollWidth <= clientWidth`).
- [x] Audit 100% of tracked text and code files in the repository to guarantee strict adherence to the <= 300-line modularity constraint (`pnpm check:line-counts`).
- [x] Prepare comprehensive client presentation walkthrough documentation (`docs/client-presentation-walkthrough.md`) detailing the design philosophy, component comparison (Variant A vs Variant B), interaction principles, accessibility proof, and clinical brand positioning.

### Out-of-Scope (Non-Goals)

- Redesigning or fundamentally rewriting components already validated in Phases 1–5.
- Adding new multi-page routes or backend database features (deferred to Phases 8–10).
- Introducing third-party accessibility overlay widgets or non-standard CSS frameworks.
- Modifying clinical copy or factual data in `config/clinic.php`.

## 3. Constraints & Dependencies

- Phase 0 through Phase 5 must be completed and validated before this audit is conducted.
- All checks must pass on the existing Laravel 12 + Tailwind CSS v4 + Alpine.js stack.
- Modularity rule: Every tracked text and code file must remain strictly under 300 lines (enforced by `pnpm check:line-counts`).
- Automated regression coverage and Playwright-based interaction testing are required for verification.

## 4. Acceptance Criteria

- [x] **AC-1**: Given a visitor with `prefers-reduced-motion: reduce` visits `/?variant=a` or `/?variant=b`, when the page loads and is scrolled, then all CSS transitions, animations, transforms, and stagger delays are disabled, content reveals immediately, and all interactive controls remain fully usable.
- [x] **AC-2**: Given any text, badge, or interactive control across both variants (including light backgrounds, dark contrast bands, and stone cards), when audited for color contrast, then all text meets WCAG 2.1 AA (>= 4.5:1 for normal text, >= 3:1 for large text and interactive UI controls).
- [x] **AC-3**: Given mobile or touch device viewports (320px to 414px), when interactive elements (CTAs, phone/WhatsApp links, carousel arrows, modal close buttons, switcher tabs) are inspected, then all touch targets measure at least 44x44px with adequate tap spacing.
- [x] **AC-4**: Given keyboard navigation across both variants, when navigating via `Tab` and `Shift+Tab`, then every focusable element displays an unambiguous, high-contrast `:focus-visible` focus ring without focus traps.
- [x] **AC-5**: Given viewports from 320px up to 1440px+, when either variant is rendered and scrolled, then the layout maintains visual integrity with zero horizontal page overflow (`scrollWidth <= clientWidth`).
- [x] **AC-6**: Given the complete repository codebase, when audited with `pnpm check:line-counts`, then 100% of tracked text and code files strictly adhere to the <= 300-line modularity constraint.
- [x] **AC-7**: Given the dual-variant POC is prepared for stakeholder review, when the presentation walkthrough guide is reviewed, then it clearly documents the design systems, interaction principles, audience positioning, and feature parity between Variant A and Variant B.

## 5. Edge Cases & Error Handling

- If any third-party asset (e.g. Google Maps iframe) introduces minor contrast or overflow quirks, it must be contained within accessible wrapper boundaries.
- If a touch target's visual label is smaller than 44px, an invisible padding or hit-area expansion (`p-2.5`, `min-h-[44px] min-w-[44px]`, or negative margin/pseudo-element) must be used to ensure the physical hit target is >= 44x44px.
- When JavaScript is disabled, reduced-motion behavior must remain intact and content must be immediately readable without layout distortion.
