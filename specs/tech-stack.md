# Technology Stack & Architectural Constraints

## 1. Agreed Technologies & Frameworks
- **Language / Runtime**: PHP 8.3+, Laravel 12 (framework `^13.17`)
- **Frontend Engine**: Laravel Blade templating with component architecture (`<x-...>`)
- **Styling**: Tailwind CSS v4 via `@tailwindcss/vite` plugin
- **Build Tooling & Asset Pipeline**: Vite 8 with `laravel-vite-plugin`
- **Typography & Fonts**: Source Serif 4 (display/headlines) & Work Sans (body/labels) via Bunny Fonts / Google Fonts
- **Client Scripting & Motion**: Vanilla JavaScript with native CSS transitions and keyframes, adhering strictly to `prefers-reduced-motion`
- **Data Stores**: None for this POC (stateless frontend prototypes; mock data organized in static PHP arrays or Blade view data; MySQL deferred to future production phases)

## 2. Architectural Decisions & Patterns

### Blade Directory & View Hierarchy
To maintain strict isolation between variants while maximizing code reuse for shared utilities:
```
resources/views/
├── components/
│   ├── shared/            # Shared UI components across variants
│   │   ├── variant-switcher.blade.php  # Persistent comparison toolbar
│   │   ├── icon.blade.php              # Curated SVG icons (dental, arrows, contact)
│   │   ├── header-shell.blade.php      # Base navigation container
│   │   └── footer-shell.blade.php      # Base footer container
│   ├── variant-a/         # Variant A specific UI components (2D Cutouts)
│   │   ├── hero-cutout.blade.php
│   │   ├── doctor-card.blade.php
│   │   ├── treatment-tile.blade.php
│   │   └── journey-step.blade.php
│   └── variant-b/         # Variant B specific UI components (Editorial)
│       ├── hero-editorial.blade.php
│       ├── doctor-portrait.blade.php
│       ├── treatment-row.blade.php
│       └── journey-timeline.blade.php
├── layouts/
│   ├── app.blade.php      # Master HTML shell (meta tags, fonts, switcher)
│   ├── variant-a.blade.php# Layout shell for Variant A
│   └── variant-b.blade.php# Layout shell for Variant B
├── variants/
│   ├── a/                 # Variant A page composition
│   │   └── index.blade.php
│   └── b/                 # Variant B page composition
│       └── index.blade.php
└── home.blade.php         # Entry controller view resolving active variant
```

### Shared Information Architecture & Content Source
All clinical content is stored centrally in a structured PHP configuration or helper (`config/clinic.php` or `app/Support/ClinicContent.php`), ensuring both variants render identical doctor bios, treatments, patient journey steps, contact details, and clinic hours.

### Variant Switching Mechanism
- The active variant is resolved in the web route or middleware from:
  1. URL Query Parameter: `?variant=a` or `?variant=b`
  2. Session State: `session('variant')`
  3. Default Fallback: `variant=a`
- A persistent, high-contrast floating switchbar (`<x-shared.variant-switcher />`) allows instantaneous toggling between Variant A and Variant B on any screen without losing scroll or context.

## 3. Engineering Constraints & Rules

### File Modularity & Size
- **Strict 300-Line Limit**: No tracked code or markdown file may exceed 300 lines. Components approaching this threshold must be decomposed into focused sub-components.

### Visual & Styling Constraints
- **Strictly No Gradients**: Gradients on backgrounds, text fills, borders, or shadows are banned. Depth must be achieved through calibrated tonal contrast and hairline rules (`#E6E1D8`, `#1E2229`).
- **Full-Width Hero Requirement**: Avoid standard split-hero layouts (text on left, small image on right). Instead, utilize immersive, full-width opening compositions with immediate value clarity.
- **No SaaS Clutter**: Avoid repetitive card grids, fake metric counters, and generic stock illustrations.

### Animation & Motion Constraints
- **Purposeful & Settled**: Animations must enter, establish hierarchy or context, and settle. Endless looping, spinning icons, and bouncing pointers are strictly prohibited.
- **Performance**: Motion must maintain 60fps utilizing hardware-accelerated CSS properties (`transform`, `opacity`).
- **Reduced Motion Support**: All animations must respect `prefers-reduced-motion: reduce` by disabling transitions or utilizing instantaneous opacity cuts.

### Responsive Breakpoints
- **Mobile (< 768px)**: Single-column full-width flow, 16px edge padding, touch targets >= 44px.
- **Tablet (768px – 1023px)**: Balanced 8-column flow, 24px gutters.
- **Desktop (1024px – 1320px+)**: Architectural 12-column grid, max-width bounded at 1320px, 40px gutters.

## 4. Deployment & Infrastructure
- Run locally via `php artisan serve` and `npm run dev`.
- Static asset compilation via `npm run build`.
- Zero database or external cache dependencies required for runtime execution.

## 5. Decision Log
- **2026-09-20**: Selected dual-variant Blade structure inside a single Laravel app with query param / session switching for immediate client evaluation.
- **2026-09-20**: Enforced frontend-only scope; deferred MySQL, migrations, and booking APIs to subsequent backend phases.
- **2026-09-20**: Adopted native CSS transitions and lightweight vanilla JS over heavy JavaScript animation runtimes for optimal performance.
