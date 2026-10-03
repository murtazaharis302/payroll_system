# Implementation Plan: Phase 0 — Foundation, Shared IA & Switcher Scaffolding

## Overview & Architecture Approach
This plan establishes the core architecture for the Dr. Bhatti & Associates Dental Clinic POC in Laravel. It sets up Tailwind CSS v4 design tokens and Bunny Fonts, implements a central clinical content repository in `config/clinic.php`, builds the master Blade layout and persistent variant switcher, and configures route resolution to switch between Variant A and Variant B seamlessly.

---

## Task Groups

### Group 1: Typography & Core Tokens
- [x] **Task 1.1**: Update `vite.config.js` to register Bunny Fonts for `Source Serif 4` (weights: 400, 600, 700) and `Work Sans` (weights: 400, 500, 600).
- [x] **Task 1.2**: Update `resources/css/app.css` to define `@theme` font families (`--font-serif`, `--font-sans`) and warm stone color palette tokens (`--color-stone-warm-50` through `--color-stone-warm-900`, `--color-charcoal-900`, `--color-brass-500`, etc.) ensuring no gradients.

### Group 2: Central Clinical Content Repository
- [x] **Task 2.1**: Create `config/clinic.php` with structured arrays:
  - `name`: "Dr. Bhatti & Associates"
  - `tagline`: "Prestigious Family Dental"
  - `contact`: phone, WhatsApp number, email, address, coordinates.
  - `hours`: Monday–Saturday schedule, emergency care note.
  - `doctor`: Dr. Bhatti's name, credentials, clinical director bio, philosophy, accreditations.
  - `treatments`: 4 key categories (Preventative, Cosmetic, Restorative, Pediatric) with titles, descriptions, and highlights.
  - `journey`: 5-step sequence (Initial Consultation, Comprehensive Diagnostics, Personalized Care Plan, Gentle Treatment, Lifelong Wellness).
- [x] **Task 2.2**: Ensure all copy is editorial-grade, clinically accurate, and adheres to the "calm, cool, memorable, trust-building" tone.

### Group 3: Master Layout Shell & Variant Switcher
- [x] **Task 3.1**: Create `resources/views/layouts/app.blade.php` with HTML5 structure, title/meta tags, Bunny Font preconnects, `@vite(['resources/css/app.css', 'resources/js/app.js'])`, content slot, and persistent floating switcher component.
- [x] **Task 3.2**: Create `resources/views/components/shared/header-shell.blade.php` displaying clinic branding, contact phone, and consultation CTA.
- [x] **Task 3.3**: Create `resources/views/components/shared/footer-shell.blade.php` with clinic address, hours, quick links, and copyright.
- [x] **Task 3.4**: Create `resources/views/components/shared/variant-switcher.blade.php`:
  - Fixed floating positioning (e.g. bottom-6 right-6 or bottom-center).
  - High-contrast pill container with hairline border.
  - Toggles between Variant A ("Variant A: Expressive 2D") and Variant B ("Variant B: Calm Editorial").
  - Active state pill indicator.
  - Keyboard accessible and respects `prefers-reduced-motion`.

### Group 4: Web Route Resolution & Scaffolded Views
- [x] **Task 4.1**: Update `routes/web.php` to:
  - Check request for `?variant=a` or `?variant=b`.
  - Validate against allowed variants (`['a', 'b']`), falling back to `'a'`.
  - Persist valid variant in session: `session(['variant' => $variant])`.
  - Resolve active variant from query param, then session, defaulting to `'a'`.
  - Render `variants.{$variant}.index` with clinic data.
- [x] **Task 4.2**: Create initial scaffold views:
  - `resources/views/variants/a/index.blade.php`: Extends `layouts.app`, renders header shell, placeholder hero for Variant A, and footer shell.
  - `resources/views/variants/b/index.blade.php`: Extends `layouts.app`, renders header shell, placeholder hero for Variant B, and footer shell.

### Group 5: Automated Testing & Modularity Verification
- [x] **Task 5.1**: Create feature tests in `tests/Feature/VariantResolutionTest.php`:
  - Test default route resolves Variant A and sets session.
  - Test `?variant=b` resolves Variant B and updates session.
  - Test `?variant=a` resolves Variant A and updates session.
  - Test invalid variant param (e.g. `?variant=xyz`) safely falls back to Variant A.
  - Test `config('clinic')` contains required structure and keys.
- [x] **Task 5.2**: Audit line counts across all created/edited files to ensure strict compliance with <= 300 lines limit.
