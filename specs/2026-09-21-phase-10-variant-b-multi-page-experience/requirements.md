# Feature Requirements: Phase 10 — Variant B Multi-Page Experience

## 1. Context & Business Intent

With Phase 8 providing the common route and content contract, Variant B must expand its calm editorial homepage into a complete clinic website. New pages must retain the existing full-bleed, restrained, reference-informed visual grammar while using only original project content, assets, and implementation—never copied Dental Design SD layouts, words, or branded material.

**Lifecycle:** Specification: Ready | Implementation: Implemented | Validation: Pending.

## 2. Scope

### In-Scope

- [x] Compose Variant B views for About, Services, Service Detail, Team, Team Detail, Patient Journey, Reviews, Contact, FAQ, Privacy, and Terms using Phase 8’s shared route/data/component contracts.
- [x] Retain the validated Variant B homepage and alter it only for navigation, contextual linking, metadata, and shared integrations required by Phase 8.
- [x] Use spacious editorial pacing, photography, hairline dividers, asymmetric pairings, restrained serif hierarchy, subtle metadata, calm transitions, and premium long-form reading on About and service-detail pages.
- [x] Use reusable Variant B service-detail and team-detail templates rather than duplicate standalone resource pages.
- [x] Feature Dr. Bhatti prominently; present supporting team records as clearly visible placeholders where information has not been provided.
- [x] Reuse the established manual review carousel/modal language on the dedicated reviews page and make review/source/destination placeholders transparent.
- [x] Extend the existing Motion Mini/IntersectionObserver architecture across meaningful headings, images, service rows, profiles, FAQ groups, reviews, form, CTAs, and footer sections with soft, fluid, restrained editorial motion.

### Out-of-Scope (Non-Goals)

- Changing Variant A compositions or creating a separate animation runtime.
- Copying the external reference’s content, layout, source, photography, logos, or branded marks.
- Card-heavy SaaS layouts, gradients, autoplay, marquee, bouncing controls, flashy text splitting, large fly-ins, scroll hijacking, or unnecessary parallax.
- Diverging from Phase 8 shared content, routes, resource semantics, form behavior, or placeholder protections.

## 3. Constraints & Dependencies

- Phase 8’s route, data, navigation, metadata, and shared behavior must be complete before this phase begins.
- Phase 5’s validated editorial motion profile is the sole Variant B motion baseline: smaller/slower than Variant A and immediate when reduced motion or JavaScript-disabled.
- Long-form copy needs deliberate mobile rhythm: preserve useful whitespace without blank-screen gaps, keep details readable, and retain focus visibility, 44px targets, and no horizontal overflow from 320px upward.

## 4. Acceptance Criteria

- [x] **AC-1**: Given any shared public pathname is visited with `?variant=b`, when it renders, then the corresponding Variant B page uses common content and routes while remaining recognizably descended from the validated calm editorial homepage.
- [x] **AC-2**: Given About, Patient Journey, FAQ, Contact, Privacy, or Terms is opened, when content is consumed, then it reads as a clear editorial feature with intentional space, quiet dividers/imagery where appropriate, accessible CTAs, functional contact treatment, and required placeholder/client-approval notices.
- [x] **AC-3**: Given Services or a service-detail page is opened, when a visitor selects a treatment, then a reusable data-driven template presents the required informational sections, related services, consultation route, and no medical guarantees or unsupported claims.
- [x] **AC-4**: Given Team or a team-detail page is opened, when a visitor navigates profiles, then Dr. Bhatti is prominently represented and other unverified records remain visibly placeholder-only; one reusable detail template renders supplied portrait, role, credentials, biography, philosophy, care areas, accreditations, and consultation CTA.
- [x] **AC-5**: Given Reviews is opened, when review controls are operated, then the manual-first carousel/modal remains accessible, source treatment is visible, and all unapproved reviews or review destinations are clearly placeholder or pending.
- [x] **AC-6**: Given a visitor scrolls or interacts on any new Variant B page, when motion runs, then significant blocks use shared semantic hooks with quiet editorial hierarchy, small/smooth reveals, and no loops, layout shifts, or parallel runtime.
- [x] **AC-7**: Given Variant B is evaluated at mobile, tablet, desktop, reduced-motion, keyboard, and JavaScript-disabled states, when routes and controls are exercised, then pages are responsive, accessible, immediate where motion is off, and free of horizontal overflow or excessive empty mobile screens.

## 5. Edge Cases & Error Handling

- Missing imagery must produce a deliberate text-first composition or explicit placeholder—not a broken image or invented visual claim.
- Long service/profile/FAQ content must preserve reading rhythm and accessible heading order at each viewport.
- The contact form must retain Phase 8’s client-only mock success and must not claim an appointment is confirmed.
