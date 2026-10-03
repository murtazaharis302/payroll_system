# Feature Requirements: Phase 11 — Cross-Variant Motion, Navigation & Preferences

## 1. Context & Business Intent

The completed multi-page website needs a final presentation pass that makes every rendered route feel native to its landing-page direction, works deliberately on small screens, and lets stakeholders compare approved visual systems without changing content or routes. This phase extends the existing Motion Mini architecture, removes two explicit hero labels, corrects the shared navigation, and adds visual-preference controls.

**Lifecycle:** Specification: Ready | Implementation: Implemented | Validation: Validated.

## 2. Scope

### In-Scope

- [x] Audit every public route in both variants against its landing-page choreography, then extend the existing single Motion Mini/IntersectionObserver system to each missing significant heading, copy block, image, list, form, CTA, navigation, and footer element.
- [x] Preserve Variant A’s expressive 2D Cutout profile (directional layered reveals, 50–120ms desktop staggers, small tactile feedback) and Variant B’s calm editorial profile (soft 8–12px reveals, 60–140ms desktop staggers, hairline/image masks, no hover lift).
- [x] Remove only the two decorative landing-hero labels `Variant A • Expressive 2D Cutout` and `Variant B • Calm Editorial Direction`; retain clinical, legal, accessibility, approval, and placeholder information.
- [x] Rebuild the shared header/navigation mobile-first so 320px+ widths show an unclipped brand and a labelled menu control; the expanded menu contains the primary links and direct-contact CTA, preserves active state, keyboard operation, visible focus, and 44px targets.
- [x] Audit all public rendered views at 320px, 375px, tablet, and desktop widths for overflow, clipped fixed controls, unusable controls, and broken information hierarchy.
- [x] Add visual preference state for the existing Warm Stone palette plus Porcelain + Deep Teal, Ivory + Rosewood, and Mineral Blue + Chalk. Map each supplied color value to shared semantic CSS tokens rather than rewriting per-component utilities.
- [x] Add a second typography option, Newsreader for display/serif and Manrope for body/UI, alongside the existing Source Serif 4 and Work Sans pairing. Load the documented weights and Newsreader italic used by the interface.
- [x] Extend the persistent switcher into an accessible, compact design-preference control containing variant, palette, and typography choices. Choices preserve pathname and unrelated query parameters, use query-over-session-over-default precedence, and default safely on invalid values.

### Out-of-Scope (Non-Goals)

- New content, routes, data persistence, authentication, booking behavior, databases, CMS work, or a separate theme/motion library.
- Removing status, placeholder, client-approval, legal, or clinically meaningful labels beyond the two named hero labels.
- Gradients, loops, autoplay, scroll hijacking, parallax, per-template observers, or card elevation in Variant B.
- The optional Cormorant Garamond alternative; this phase implements only the supplied Newsreader/Manrope pair.

## 3. Architecture & Preference Contract

- `resources/js/app.js` remains the only motion runtime and owns one once-only observer. Templates declare only the existing semantic hooks; reduced motion and no-JavaScript rendering remain immediately visible.
- Root state uses `data-motion-profile`, `data-palette`, and `data-typeface`. Palette overrides use semantic custom properties consumed by the existing Tailwind token utilities.
- Valid palette identifiers are `warm-stone`, `porcelain-teal`, `ivory-rosewood`, and `mineral-blue`; valid typeface identifiers are `source-work` and `newsreader-manrope`.
- Preference queries are `palette` and `typeface`. They use the same safe resolution pattern as `variant`: valid query value, then session value, then defaults `warm-stone` and `source-work`; invalid values fall back to those defaults.
- The switcher updates only the selected preference key while retaining path, variant, and unrelated query parameters. It remains operable with normal links and without JavaScript.
- Palette token mapping: background/surface/elevated/border map to the warm-stone tonal slots; primary/secondary/muted text map to semantic charcoal/stone slots; primary/hover, secondary/soft, and warm/champagne/sand accents map to shared dark, secondary, and brass slots. No component may hardcode an alternate palette hex value.

## 4. Acceptance Criteria

- [x] **AC-1**: Given any public route in Variant A, when it enters view with motion enabled, then its significant blocks follow the established expressive landing-page hierarchy using the existing runtime, settle once, and have no loops or layout shifts.
- [x] **AC-2**: Given any public route in Variant B, when it enters view with motion enabled, then its significant blocks follow the established calm editorial landing-page hierarchy, remain slower/shorter than A, and never use card-lift hover behavior.
- [x] **AC-3**: Given reduced motion is enabled or JavaScript is unavailable, when any variant route loads, then all content, navigation, and controls are immediately visible, usable, and free of motion delays or hidden states.
- [x] **AC-4**: Given either homepage renders, when its hero is inspected, then the named Variant A and Variant B decorative labels are absent while the H1, description, CTAs, and all required information remain present.
- [x] **AC-5**: Given the site is viewed from 320px through desktop, when the shared header/menu, fixed preference control, and page content are used, then no horizontal overflow or clipping occurs and primary navigation/contact actions remain reachable by keyboard and touch.
- [x] **AC-6**: Given the mobile menu is opened, when a visitor navigates or changes viewport, then it presents the shared IA, active-page state, direct-contact CTA, visible focus, and 44px controls without relying on JavaScript.
- [x] **AC-7**: Given a valid palette is selected, when either variant is rendered, then the current Warm Stone or one of the three supplied palettes is applied consistently through semantic tokens with readable contrast and no hardcoded per-view alternate colors.
- [x] **AC-8**: Given a typography option is selected, when either variant renders, then `source-work` uses Source Serif 4/Work Sans and `newsreader-manrope` uses Newsreader/Manrope at the documented display, body, UI, and italic roles without layout breakage.
- [x] **AC-9**: Given a visitor changes variant, palette, or typography from the shared switcher, when the next response renders, then the pathname and unrelated query values persist, the selected state is visible, valid preferences persist in session, and invalid preference values use safe defaults.

## 5. Edge Cases & Error Handling

- Mobile menus and fixed controls must not obscure focus targets, page headings, the browser viewport, or one another.
- A palette’s muted color may not be used for essential normal-size text unless the rendered pairing meets WCAG AA; a compliant semantic token must be used instead.
- Font loading failure must retain readable system fallbacks and must not hide content or block interaction.
- Existing review carousel controls remain manual-first; visual preference and motion changes may not add autoplay.
