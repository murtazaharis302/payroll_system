# Implementation Plan: Phase 10 — Variant B Multi-Page Experience

## Overview & Architecture Approach

Build Variant B’s focused presentational layer over Phase 8’s shared contracts. The new Blade compositions belong under `components/variant-b` and `variants/b`; data, routes, semantics, and client-only form behavior remain shared.

## Task Groups

### Group 1: Long-Form Editorial & Utility Pages

- [x] Task 1.1: Build reusable Variant B page-introduction, media, divider, and CTA patterns, then compose About and Patient Journey with original photographic/editorial pairings and calm reading rhythm.
- [x] Task 1.2: Compose FAQ, Contact, Privacy, and Terms with quiet information hierarchy, explicit placeholder/legal approval labels, functional map/contact content, and shared mock-form behavior.

### Group 2: Resource Experiences

- [x] Task 2.1: Build the Services overview and reusable data-driven service-detail template with restrained rows, related service navigation, FAQs, consultation CTAs, and informational copy.
- [x] Task 2.2: Build the Team overview and reusable team-detail template with an editorial Clinical Director feature and honest, visibly labelled placeholders for unsupported team information.
- [x] Task 2.3: Compose the dedicated reviews page from existing carousel/review-card/modal components with clear placeholder provenance and source/destination treatment.

### Group 3: Editorial Motion & Responsive Refinement

- [x] Task 3.1: Apply existing semantic hooks to every significant new element and calibrate Phase 5’s smaller, slower editorial timing without adding observers, autoplay, or reference copying.
- [x] Task 3.2: Refine desktop pairings and mobile single-column flow to prevent horizontal overflow and excessive empty space; validate headings, images, focus, touch targets, map, carousel, and form interaction.

### Group 4: Verification

- [x] Task 4.1: Add focused Variant B rendering tests mapped to every page and resource-detail acceptance criterion while preserving homepage coverage.
- [x] Task 4.2: Run local Playwright desktop/mobile route journeys, service/team detail navigation, reviews/modal, contact form, variant switching, reduced-motion, and JavaScript-disabled checks.
- [x] Task 4.3: Run the focused PHP test suite, Pint, frontend build, line-count, and whitespace checks.
