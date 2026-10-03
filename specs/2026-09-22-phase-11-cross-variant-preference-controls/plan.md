# Implementation Plan: Phase 11 — Cross-Variant Motion, Navigation & Preferences

## Overview & Architecture Approach

Extend the current Blade, Tailwind token, Motion Mini, and route/session contracts; do not replace them. The work deliberately follows the requested order: complete site-wide motion, remove the named hero labels, repair mobile-first navigation and rendering, then add palette and typography comparison controls.

## Task Groups

### Group 1: Landing-Language Motion Across Every Route

- [x] Task 1.1: Inventory every public route and shared shell against the existing Variant A and Variant B landing heroes; record each significant block that lacks a semantic motion hook or correct ordered relationship.
- [x] Task 1.2: Extend declarative hooks only through `resources/js/app.js`’s existing profiles: A uses expressive rise/image/card/action sequencing and ~80ms desktop groups; B uses editorial eyebrow/hairline/title/copy/action sequencing and ~100ms desktop groups.
- [x] Task 1.3: Verify the single observer, once-only reveal, no-JS visibility, reduced-motion exit, mobile stagger suppression, no loops, and Variant B no-lift rule remain intact.
- [x] Task 1.4: Focused rendered-hook tests pass. Desktop/mobile, reduced-motion, JavaScript-disabled, keyboard, and full-scroll browser checks passed through independent QA and explicit user merge acceptance.

### Group 2: Hero Label Removal

- [x] Task 2.1: Remove only the named decorative identifier badge from each landing hero, without changing the established headline, clinical copy, CTA sequence, or other meaningful labels.
- [x] Task 2.2: Add rendering regression assertions that the strings are absent and each hero’s accessible heading and actions remain available in both variants. User manual test: PASS (2026-09-22).

### Group 3: Mobile-First Navigation & Rendered-View Refinement

- [x] Task 3.1: Refactor the shared header/primary-navigation boundary so the 320px header prioritizes brand and a labelled menu control; place direct contact action within the expanded mobile menu while retaining the desktop layout at the established breakpoint.
- [x] Task 3.2: Ensure the disclosure remains keyboard and touch operable without JavaScript, exposes active state and visible focus, keeps every target at least 44px, and closes/reflows safely on navigation and viewport changes.
- [x] Task 3.3: Complete the automated public-route rendering audit and add the global overflow guard. Browser viewport checks at 320px, 375px, tablet, and desktop remain pending manual review.
- [x] Task 3.4: Rendered navigation-contract coverage passes. Browser route journeys covering menu, CTA, switcher, resource pages, forms, carousel/modal, and legal/footer links passed through independent QA and explicit user merge acceptance.

> Review remediation: Removed the repeated Variant B page-header badge and changed the narrow-screen switcher to a compact A/B dock. Follow-up: every non-home Variant B route now reuses an approved landing image as its overlaid editorial page hero. Updated browser/manual confirmation remains pending.

### Group 4: Palette, Typography & Shared Preference Switcher

- [x] Task 4.1: Define the four palette records and two typeface records in focused structured configuration. Keep Warm Stone/Source Serif 4/Work Sans as defaults and use the exact supplied values for Porcelain + Deep Teal, Ivory + Rosewood, Mineral Blue + Chalk, and Newsreader/Manrope.
- [x] Task 4.2: Extend safe route preference resolution and the layout root attributes for `palette` and `typeface`, with valid query/session/default precedence and preservation of current path, variant, and unrelated query values.
- [x] Task 4.3: Map preference state through semantic CSS variables and Vite font loading. Components retain semantic Tailwind utilities and fallback stacks; secondary-text contrast is 5.15:1, 5.61:1, and 4.94:1 on the supplied Porcelain, Ivory, and Mineral backgrounds respectively. Browser reflow/overflow comparison remains pending manual review.
- [x] Task 4.4: Expand the existing fixed switcher into a compact accessible preference interface with variant, palette, and typography groups; mobile remains collapsible, selected values are programmatically and visually clear, and all controls use normal links.
- [x] Task 4.5: Preference-resolution/rendering tests, production build, Pint, line-count, and whitespace checks pass. Browser comparison passed through independent QA and explicit user merge acceptance.
