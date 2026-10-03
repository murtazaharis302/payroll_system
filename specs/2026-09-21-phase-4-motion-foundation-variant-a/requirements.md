# Feature Requirements: Phase 4 — Motion Foundation & Variant A

## 1. Context & Business Intent

The delivered Variant A page has a settled hero and isolated interaction transitions, but it does not yet provide the continuous, hierarchy-led motion requested across the full experience. This phase establishes the one shared, progressively enhanced motion system and applies the expressive, tactile motion language to Variant A.

**Lifecycle:** Specification: Ready | Implementation: Not Started | Validation: Pending.

## 2. Scope

### In-Scope

- [ ] Add the lightweight vanilla `motion` package; use `animate` from `motion/mini` by default and do not add React or Framer Motion bindings.
- [ ] Centralize one observer-driven initializer in `resources/js/app.js`, with reusable semantic hooks: `data-motion`, `data-motion-delay`, and `data-motion-stagger`.
- [ ] Add reusable motion tokens and no-JavaScript-safe initial-state primitives to `resources/css/app.css`; hidden states may be applied only after successful JavaScript initialization.
- [ ] Use a single `IntersectionObserver` strategy with a pre-viewport root margin. Elements normally reveal once, in section hierarchy: label, heading, copy, visual/group, then action.
- [ ] Apply hooks to every Variant A chapter and shared shell: header, hero, doctor/philosophy, treatment grid, patient journey, testimonials, booking/location, footer, and variant switcher.
- [ ] Give Variant A layered, expressive-but-premium choreography: text 8–20px, cards 12–24px, major image/cutout or mask treatment 15–35px, and related-item staggers of roughly 50–120ms.
- [ ] Animate Variant A journey progression, review-card entrance and directional carousel response, map mask, hours rows, and restrained hover/press/link responses without loops or scroll hijacking.
- [ ] Ensure `prefers-reduced-motion: reduce` shows all content immediately and disables translations, masks, line drawing, and stagger delay while retaining accessible focus states.

### Out-of-Scope

- Variant B’s editorial choreography and its required external reference audit (Phase 5).
- React, Framer Motion React, autoplaying/marquee effects, pinned sections, smooth-scroll libraries, or per-component scroll handlers.
- Backend, content, route, or database changes.

## 3. Constraints & Dependencies

- Retain Blade components and centralized frontend architecture. Do not scatter standalone scripts in templates.
- Favor `transform`, `opacity`, and constrained `clip-path`/overflow masks; avoid recurring layout, blur, or filter animation.
- Preserve the no-gradients rule, 44px touch targets, content parity, no horizontal overflow, and every tracked file below 300 lines.
- Motion Mini is the default. A broader Motion import requires a documented effect that cannot be cleanly delivered by Motion Mini and native APIs.
- Existing `resources/js/testimonials.js` behavior must remain functional while motion ownership is consolidated.

## 4. Acceptance Criteria

- [ ] **AC-1**: Given JavaScript initializes, when either variant page loads, then one centralized observer-driven motion system recognizes the semantic hooks and progressively enhances the rendered content without hiding it when JavaScript is unavailable.
- [ ] **AC-2**: Given a Variant A visitor loads or scrolls `/?variant=a`, when each major content group nears the viewport, then its label, heading, copy, visual/group, and action reveal in a once-only, ordered choreography with no large fly-ins or looping.
- [ ] **AC-3**: Given the Variant A hero and doctor sections render, when motion runs, then their background planes, cutouts, anatomy/image treatment, identity/quote content, biography, and accreditation entries establish restrained layered depth and settle.
- [ ] **AC-4**: Given Variant A treatment, journey, testimonial, booking, footer, and switcher content enters view or is interacted with, when motion runs, then repeated items stagger, the journey reads as progression, visual/map content uses restrained reveal, and interactions provide small responsive feedback without layout shift.
- [ ] **AC-5**: Given carousel navigation, cards, buttons, links, accreditations, map links, or the variant switcher are used, when pointer, keyboard, or touch interaction occurs, then focus remains visible and feedback is limited to subtle lift, border/surface, underline, or press response.
- [ ] **AC-6**: Given reduced motion is enabled, when Variant A is loaded, scrolled, or interacted with, then content is immediately visible and translations, masks, progressive line drawing, and stagger delay do not run.
- [ ] **AC-7**: Given JavaScript is disabled or fails before initialization, when either page renders, then no motion hook leaves content hidden or unusable.
- [ ] **AC-8**: Given desktop and mobile Variant A flows are audited with Playwright, when fully scrolled, reloaded, switched, and keyboard-navigated, then there is no horizontal overflow, no annoying repeat trigger, no hidden content, and all declared motion remains controlled.

## 5. Edge Cases & Error Handling

- Missing hook attributes, missing observer support, or absent Motion APIs must leave content visible and preserve native behavior.
- Large/repeated lists must stagger only the entering group, not create a long queue.
- The initial hero sequence must remain immediately usable and must not introduce a loading screen.
- Animation cancellation or repeated observer notifications must not leave partially transformed elements behind.
