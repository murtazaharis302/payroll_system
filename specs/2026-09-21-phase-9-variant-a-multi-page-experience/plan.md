# Implementation Plan: Phase 9 — Variant A Multi-Page Experience

## Overview & Architecture Approach

Build focused Variant A page compositions over Phase 8’s shared contracts. Keep data, route resolution, accessibility semantics, and form logic shared; keep expressive visual composition and its motion annotations under `components/variant-a` and `variants/a`.

## Task Groups

### Group 1: Editorial & Informational Pages

- [ ] Task 1.1: Build reusable Variant A page-introduction and CTA patterns, then compose About and Patient Journey with warm architectural layers, selective imagery, and existing clinic content.
- [ ] Task 1.2: Compose FAQ, Contact, Privacy, and Terms with typographic clarity, explicit placeholder/legal approval labels, functional map/contact details, and the shared mock form behavior.

### Group 2: Resource Experiences

- [ ] Task 2.1: Build the Services overview plus data-driven service-detail template, including related-treatment navigation, FAQ sections, consultation CTAs, and concise informational treatment copy.
- [ ] Task 2.2: Build the Team overview plus data-driven team-detail template, with a prominent Clinical Director treatment and honest placeholder presentation for unverified colleagues.
- [ ] Task 2.3: Extend the review experience into its own page by composing the established carousel/review-card/modal components with declared placeholder provenance.

### Group 3: Variant A Motion & Responsive Refinement

- [ ] Task 3.1: Annotate each new significant element with the existing semantic motion hooks; calibrate expressive directional timing, depth, and stagger without new observers or loops.
- [ ] Task 3.2: Deliberately reduce visual layering at mobile widths, preserve functional map/form layouts, and test keyboard focus, target sizes, images, headings, and no-overflow states.

### Group 4: Verification

- [ ] Task 4.1: Add focused Variant A rendering tests mapped to every page and resource-detail acceptance criterion while retaining existing homepage coverage.
- [ ] Task 4.2: Run local Playwright desktop/mobile route journeys, service/team detail navigation, reviews/modal, contact form, variant switching, reduced-motion, and JavaScript-disabled checks.
- [ ] Task 4.3: Run the focused PHP test suite, Pint, frontend build, line-count, and whitespace checks.
