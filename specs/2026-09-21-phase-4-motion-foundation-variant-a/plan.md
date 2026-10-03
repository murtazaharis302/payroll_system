# Implementation Plan: Phase 4 — Motion Foundation & Variant A

## Overview & Architecture Approach

Build the shared system first, then annotate the Variant A component hierarchy. JavaScript adds initial states only after it is ready; CSS supplies semantic tokens and reduced-motion fallbacks. Motion Mini coordinates supported effects, while one observer owns viewport activation.

## Task Groups

### Group 1: Shared Motion Foundation

- [x] Task 1.1: Add `motion` as a frontend dependency and verify the installed version and Motion Mini import before implementation.
- [x] Task 1.2: Refactor `resources/js/app.js` into the central initializer; retain and integrate existing testimonial behavior rather than duplicating observers or scroll handlers.
- [x] Task 1.3: Define supported semantic hook values, delay/stagger parsing, once-only observer lifecycle, cancellation/reset behavior, and no-JS-safe initialization.
- [x] Task 1.4: Add focused CSS motion variables/primitives and the reduced-motion override in `resources/css/app.css`, splitting focused modules before any file approaches 300 lines.

### Group 2: Variant A Page-Load & Section Choreography

- [x] Task 2.1: Add semantic hooks to the shared header, Variant A hero, layered planes, dentist/anatomy composition, identity plate, metrics, and switcher.
- [x] Task 2.2: Annotate doctor/philosophy, treatment header/grid/cards, and patient journey header/line/nodes/content with ordered group and stagger relationships.
- [x] Task 2.3: Annotate testimonials, review hierarchy, carousel controls, booking/location map, hours, conversion actions, and footer; apply variant-appropriate hover, focus, and press response.
- [x] Task 2.4: Confirm all motion is directional and small, has no infinite/autoplay behavior, and preserves mobile’s simpler vertical journey sequence.

### Group 3: Accessibility, Regression Tests & Interactive QA

- [x] Task 3.1: Extend focused Laravel feature coverage for the rendered motion hooks, maintained accessibility attributes, and reduced-motion-safe markup without asserting implementation-only timing details.
- [x] Task 3.2: Use Playwright on the local Laravel server for desktop and mobile Variant A: reload, full slow scroll, treatment/journey/review/location/footer sequence, carousel, modal, switcher, focus, and reduced-motion checks.
- [x] Task 3.3: Verify JavaScript-disabled/failure-safe rendering, no overflow, no hidden post-animation content, controlled once-only triggering, and source file line limits.
- [x] Task 3.4: Run the narrowest relevant PHPUnit suite, frontend build, Pint for changed PHP, line-count check, and `git diff --check` during implementation.
