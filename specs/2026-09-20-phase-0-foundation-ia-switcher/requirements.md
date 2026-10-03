# Feature Requirements: Phase 0 — Foundation, Shared IA & Switcher Scaffolding

## 1. Context & Business Intent
As established in [specs/mission.md](file:///home/zeshan6a/Projects/dental_clinic/specs/mission.md), the Dr. Bhatti & Associates Dental Clinic POC requires comparing two distinct frontend design directions (Variant A: Expressive 2D Cutout vs. Variant B: Calm Editorial) within a single Laravel application.

To ensure genuine, side-by-side comparative evaluation without content disparity, Phase 0 establishes the foundational architectural scaffolding:
- Configured typography (Source Serif 4, Work Sans) and core color palette in Tailwind CSS v4 without gradients.
- A centralized clinical content repository (`config/clinic.php`) serving as the single source of truth for both variants.
- Master layout shells and a persistent floating variant switcher supporting `?variant=a|b` and session persistence.
- Clean routing resolution to render the active variant view.

---

## 2. Scope

### In-Scope
- [ ] **Typography & Palette Tokens**: Configure Bunny Fonts for Source Serif 4 (display/headlines) and Work Sans (body/labels) in `vite.config.js` and design tokens in `resources/css/app.css` adhering strictly to a warm stone / calm healthcare palette with zero gradients.
- [ ] **Central Clinical Content Repository**: Create `config/clinic.php` with complete, structured data:
  - Clinic identity (name, tagline, address, phone, WhatsApp number).
  - Clinical Director credentials, bio, and philosophy for Dr. Bhatti.
  - Treatments & Care Landscape (Preventative, Cosmetic, Restorative, Pediatric).
  - Patient Journey (5 sequential steps).
  - Clinic hours and emergency protocol.
- [ ] **Master Layout & Shared Shells**:
  - `resources/views/layouts/app.blade.php`: Base HTML document with meta tags, font links, Vite directives, and persistent switcher.
  - `resources/views/components/shared/header-shell.blade.php`: Base navigation header container.
  - `resources/views/components/shared/footer-shell.blade.php`: Base footer container.
- [ ] **Persistent Variant Switcher Component**:
  - `resources/views/components/shared/variant-switcher.blade.php`: High-contrast, floating toolbar pinned to the bottom/top of the screen.
  - Supports instantaneous toggling between Variant A and Variant B.
  - Highlights currently active variant.
  - Accessible touch targets (>= 44px) and keyboard navigation.
- [ ] **Route Resolution & Session Persistence**:
  - Update `routes/web.php` to resolve `?variant=a|b`.
  - Store selected variant in Laravel session (`session(['variant' => $variant])`).
  - Default to Variant A when no query or session exists.
  - Gracefully fallback to Variant A on invalid parameters.
  - Scaffold initial view entries `resources/views/variants/a/index.blade.php` and `resources/views/variants/b/index.blade.php`.

### Out-of-Scope (Non-Goals)
- No database migrations, Eloquent models, or database seeders.
- No Variant A specific cutout animations, layered cards, or illustrations (deferred to Phase 1).
- No Variant B specific editorial typography grids, portraits, or drawers (deferred to Phase 2).
- No backend appointment booking APIs or external third-party integrations beyond direct WhatsApp links.
- No color gradients on any background, border, or text element.

---

## 3. Constraints & Dependencies
- **Modularity Cap**: All tracked PHP, Blade, and CSS files must remain strictly under 300 lines.
- **Zero Gradients**: No CSS gradients permitted across any component.
- **No Looping Animations**: Animations must enter and settle; zero infinite loops.
- **Framework & Tooling**: Laravel 12, Blade templating, Vite 8, Tailwind CSS v4 (`@tailwindcss/vite`).

---

## 4. Acceptance Criteria

- [ ] **AC-1**: Given a user visits `/` without query parameters or prior session, when the request is processed, then the application defaults to Variant A, stores `variant => 'a'` in session, and renders the Variant A scaffolded view.
- [ ] **AC-2**: Given a user visits `/?variant=b` (or `/?variant=a`), when the request is processed, then the application sets the session variant accordingly and renders the corresponding variant scaffolded view.
- [ ] **AC-3**: Given a user visits with an invalid variant (e.g. `/?variant=invalid`), when the request is processed, then the application safely falls back to Variant A without throwing an exception.
- [ ] **AC-4**: Given any rendered page view, when inspecting the DOM, then the persistent floating variant switcher (`<x-shared.variant-switcher />`) is visible with clear active indicators for the current variant and direct toggle links preserving current path.
- [ ] **AC-5**: Given the application is running, when accessing `config('clinic')`, then all clinical copy (clinic metadata, Dr. Bhatti profile, 4 treatment categories, 5 patient journey steps, hours, contact info) is accessible, structured, and complete.
- [ ] **AC-6**: Given the compiled stylesheet, when inspecting font definitions and utility classes, then `font-serif` maps to Source Serif 4, `font-sans` maps to Work Sans, and design tokens adhere to warm stone palette with zero gradients.

---

## 5. Edge Cases & Error Handling
- **Invalid Query Parameter**: Any `variant` value other than `a` or `b` defaults to `a`.
- **Session vs. Query Parameter Precedence**: Query parameter explicitly overrides existing session and updates session state.
- **Reduced Motion**: Floating switcher transitions respect `prefers-reduced-motion: reduce`.
