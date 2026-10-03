# Implementation Plan: Phase 5 — Variant B Calm Editorial Motion

## Overview & Architecture Approach

Use the Phase 4 semantic observer and tokens; do not create a parallel Variant B runtime. The first implementation artifact is an evidence-based reference audit. Then wire editorial hooks into existing Blade structure and calibrate values against the audit.

## Task Groups

### Group 1: Reference Study & Calibration Contract

- [x] Task 1.1: Open Dental Design SD with Playwright, reload, slowly scroll repeatedly, interact with links/cards, and inspect desktop plus mobile when available.
- [x] Task 1.2: Create `motion-audit.md` in this specification directory before source changes, recording concrete observations and the Variant B translation choices.
- [x] Task 1.3: Reconcile the audit with mission constraints: medical authority, no copied layout/assets, no gradients, no loops, and reduced-motion safety.

### Group 2: Editorial Section Integration

- [x] Task 2.1: Add hooks to the shared header/footer/switcher and Variant B hero for label-to-action sequencing and small grouped headline/image treatment.
- [x] Task 2.2: Add hooks to doctor portrait/content/accreditations, treatment header/hairline/rows, and journey header/line/nodes/assurance block.
- [x] Task 2.3: Add hooks to testimonials/review hierarchy and controls, booking header/map/address/schedule/emergency/actions, preserving existing modal and carousel accessibility.
- [x] Task 2.4: Calibrate variant-specific durations, distances, easing, and interaction CSS so Variant B is consistently softer and slower than Variant A.

### Group 3: Verification & Regression Safety

- [x] Task 3.1: Extend focused `tests/Feature/VariantBTest.php` coverage for stable hooks and retained rendered/accessibility contracts.
- [x] Task 3.2: Run Playwright local desktop/mobile full-scroll, reload, carousel/modal, keyboard, switcher, no-overflow, disabled-JS, and reduced-motion checks.
- [x] Task 3.3: Revisit the external reference with Playwright and compare rhythm while scrolling, not by screenshots alone; append the comparison result to the audit.
- [x] Task 3.4: Run focused tests, build, Pint for changed PHP, line-count, and whitespace checks during implementation.

### Group 4: User-Directed Reference-Informed Composition Alignment

- [x] Task 4.1: Recompose the Variant B hero and shared shell into an original full-bleed, compact, high-legibility clinical introduction using only project assets and content.
- [x] Task 4.2: Rework doctor, treatments, journey, and testimonials into a coherent alternating editorial sequence with restrained photographic blocks, service rows, and dark contrast bands while preserving existing data and controls.
- [x] Task 4.3: Rework booking/location and the shared footer into an original reference-informed finale while retaining maps, hours, direct contacts, and the persistent variant switcher.
- [x] Task 4.4: Extend focused rendering/accessibility tests and repeat local Playwright desktop/mobile, carousel/modal, keyboard, reduced-motion, JavaScript-disabled, and no-overflow checks against AC-9 through AC-12.

## Paused Implementation Checkpoint (2026-09-21)

- The reference audit, motion integration, reference-informed composition, focused regression tests, build, and local Playwright checks are complete on `fix/phase-5-variant-b-editorial-motion`.
- The user manually verified the work and directed that implementation stop here for later continuation.
- Fresh independent QA passed after targeted remediation of the no-JavaScript review fallback, mobile motion-delay range, and post-implementation comparison evidence.
