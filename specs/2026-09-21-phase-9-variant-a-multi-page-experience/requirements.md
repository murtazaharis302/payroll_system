# Feature Requirements: Phase 9 — Variant A Multi-Page Experience

## 1. Context & Business Intent

With Phase 8 providing the common route and content contract, Variant A must turn its expressive 2D Cutout homepage into a complete, coherent clinic website. New pages must feel native to the warm architectural, brass/stone/charcoal, layered, premium medical language—not like a generic template or a redesign of the validated homepage.

**Lifecycle:** Specification: Ready | Implementation: Not Started | Validation: Pending.

## 2. Scope

### In-Scope

- [ ] Compose Variant A views for About, Services, Service Detail, Team, Team Detail, Patient Journey, Reviews, Contact, FAQ, Privacy, and Terms using Phase 8’s shared route/data/component contracts.
- [ ] Retain the existing Variant A home composition and update only its navigation, contextual links, metadata, and shared integrations required by Phase 8.
- [ ] Give each major page or chapter approximately one meaningful strong visual composition: warm architectural surfaces, selective photographic cutouts, typographic hierarchy, layered depth, and clean functional map/contact treatment.
- [ ] Use a reusable Variant A service-detail template and one reusable Variant A team-detail template rather than standalone resource pages.
- [ ] Feature Dr. Bhatti prominently in the team experience; show unverified supporting-team records only as clearly labelled placeholders without invented claims.
- [ ] Reuse the established review carousel/modal language on the dedicated review page while clearly labelling placeholder reviews and preserving manual-first interaction.
- [ ] Extend the existing Motion Mini/IntersectionObserver hooks to each significant page heading, section, image, list, profile, FAQ, review, form, CTA, and footer block with directional, tactile, settled Variant A choreography.

### Out-of-Scope (Non-Goals)

- Altering Variant B compositions or building a second motion runtime.
- Filling every page with cutouts, decorative loops, bouncing arrows, text splitting, large fly-ins, parallax, scroll hijacking, gradients, or scrapbook-like visual clutter.
- Altering Phase 8’s shared facts, routes, form semantics, or resource data to make Variant A content diverge from Variant B.

## 3. Constraints & Dependencies

- Phase 8’s route, data, navigation, metadata, and shared behavior must be complete before this phase begins.
- All wording is sourced from shared configuration. Services remain informational; team/review/legal placeholders retain their Phase 8 honesty flags.
- On mobile, simplify layered compositions deliberately rather than merely stacking desktop elements; preserve 320px+ usability, no overflow, keyboard access, focus visibility, and 44px targets.
- Reduced motion and JavaScript-disabled rendering must show immediate, readable content. Existing homepage motion remains compatible.

## 4. Acceptance Criteria

- [ ] **AC-1**: Given any shared public pathname is visited with `?variant=a`, when it renders, then the corresponding Variant A page uses the common content and route contract while retaining an expressive 2D Cutout composition recognizably descended from the existing homepage.
- [ ] **AC-2**: Given About, Patient Journey, FAQ, Contact, Privacy, or Terms is opened, when content is consumed, then it has clear hierarchy, one purposeful visual system where appropriate, functional and uncluttered contact treatment, accessible CTAs, and required placeholder/client-approval notices.
- [ ] **AC-3**: Given Services or a service detail page is opened, when a visitor selects a treatment, then reusable data-driven presentation provides the required informational sections, related treatments, consultation route, and no medical guarantees or unsupported claims.
- [ ] **AC-4**: Given Team or a team detail page is opened, when a visitor navigates profiles, then Dr. Bhatti is prominently represented and other unverified records remain visibly placeholder-only; one reusable detail template renders portrait, role, credentials, biography, philosophy, care areas, accreditations, and consultation CTA where supplied.
- [ ] **AC-5**: Given Reviews is opened, when cards/carousel/modal controls are operated, then established manual-first review interaction remains accessible, source treatment is shown, and every unapproved review/destination is explicitly identified as placeholder or pending.
- [ ] **AC-6**: Given a visitor scrolls or interacts on any new Variant A page, when motion runs, then all significant blocks use the shared semantic hooks with controlled directional depth and settle without looping, layout shift, or a parallel animation system.
- [ ] **AC-7**: Given Variant A is evaluated at mobile, tablet, desktop, reduced-motion, keyboard, and JavaScript-disabled states, when routes and controls are exercised, then pages remain responsive, accessible, immediately usable where motion is off, and free of horizontal overflow.

## 5. Edge Cases & Error Handling

- Long service, FAQ, and profile content must preserve readable line lengths and never overlap layered visual elements.
- A resource with no portrait or clinical details renders its explicit placeholder treatment rather than a deceptive invented fallback.
- The contact form retains Phase 8’s client-only behavior and must not imply a submitted booking.
