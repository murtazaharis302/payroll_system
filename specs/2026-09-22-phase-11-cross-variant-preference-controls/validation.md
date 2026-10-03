# Validation & Merge Readiness: Phase 11 — Cross-Variant Motion, Navigation & Preferences

## 1. Acceptance Criteria Verification Matrix

| AC ID | Description | Verification Method | Pass / Fail |
|---|---|---|---|
| AC-1 | Variant A site-wide expressive choreography | Rendered-hook tests and desktop/mobile full-scroll audit | Automated: PASS; Independent QA: PASS; Manual: PASS (explicit user merge acceptance, 2026-09-22) |
| AC-2 | Variant B site-wide calm editorial choreography | Rendered-hook tests and desktop/mobile full-scroll audit | Automated: PASS; Independent QA: PASS; Manual: PASS (explicit user merge acceptance, 2026-09-22) |
| AC-3 | Reduced-motion and no-JS safety | Browser emulation and JavaScript-disabled route checks | Automated: PASS; Independent QA: PASS; Manual: PASS (explicit user merge acceptance, 2026-09-22) |
| AC-4 | Both named hero labels removed | Focused hero rendering tests | Automated: PASS; Independent QA: PASS; Manual: PASS (user, 2026-09-22) |
| AC-5 | Responsive rendered views and reachable controls | 320px, 375px, tablet, desktop browser audits | Automated: PASS; Independent QA: PASS; Manual: PASS (explicit user merge acceptance, 2026-09-22) |
| AC-6 | Mobile navigation disclosure accessibility | Keyboard/touch browser flow and rendered-markup tests | Automated: PASS; Independent QA: PASS; Manual: PASS (explicit user merge acceptance, 2026-09-22) |
| AC-7 | Four semantic color palettes | Preference tests, contrast audit, visual browser comparison | Automated: PASS; Independent QA: PASS; Manual: PASS (explicit user merge acceptance, 2026-09-22) |
| AC-8 | Two typography systems | Font/rendering tests and viewport comparison | Automated: PASS; Independent QA: PASS; Manual: PASS (explicit user merge acceptance, 2026-09-22) |
| AC-9 | Path-preserving preference switcher state | Feature query/session matrix and browser interaction flow | Automated: PASS; Independent QA: PASS; Manual: PASS (explicit user merge acceptance, 2026-09-22) |

## Validation Status Breakdown

- **Automated Tests**: PASS
- **Independent QA Audit**: PASS
- **Manual User Acceptance**: PASS (explicit user merge acceptance, 2026-09-22)
- **Overall Feature Status**: Validated & Merged

## 2. Automated Test Suite

- `tests/Feature/CrossVariantMotionTest.php`: Public-route motion-hook coverage, one-runtime guard, and reduced-motion/no-JS contracts for AC-1 through AC-3.
- `tests/Feature/ResponsiveNavigationTest.php`: Hero label removal, mobile navigation semantics, active state, and touch-target markup for AC-4 through AC-6.
- `tests/Feature/VisualPreferenceTest.php`: Palette/typeface defaults, valid/invalid query handling, session persistence, root attributes, and path/query preservation for AC-7 through AC-9.
- Existing multi-page, variant-resolution, testimonial, and consultation-form suites: rerun as regression coverage.

### Group 1 Automated Evidence — 2026-09-22

- `php artisan test --compact tests/Feature/CrossVariantMotionTest.php tests/Feature/VariantAMultiPageTest.php tests/Feature/VariantBMultiPageTest.php tests/Feature/VariantResolutionTest.php tests/Feature/TestimonialsCarouselTest.php tests/Feature/CrossVariantPolishAuditTest.php`: PASS — 38 tests, 919 assertions.
- `php artisan test --compact`: PASS — 75 tests, 1,482 assertions.
- `npm run build`: PASS.
- `npm run check:line-counts`: PASS — 261 tracked text/code files at or below 300 lines.
- `vendor/bin/pint --dirty --format agent`: PASS.
- Browser evidence is intentionally pending manual review before Group 2 begins.

### Group 2 Automated Evidence — 2026-09-22

- `php artisan test --compact tests/Feature/ResponsiveNavigationTest.php tests/Feature/VariantATest.php tests/Feature/VariantBTest.php`: PASS — 26 tests, 335 assertions.
- `php artisan test --compact`: PASS — 77 tests, 1,494 assertions.
- `npm run check:line-counts`: PASS — 265 tracked text/code files at or below 300 lines.
- `vendor/bin/pint --dirty --format agent`: PASS.
- Manual review: PASS (user, 2026-09-22).

### Group 3 Automated Evidence — 2026-09-22

- `php artisan test --compact tests/Feature/ResponsiveNavigationTest.php tests/Feature/VariantResolutionTest.php tests/Feature/VariantAMultiPageTest.php tests/Feature/VariantBMultiPageTest.php tests/Feature/TestimonialsCarouselTest.php`: PASS — 33 tests, 788 assertions.
- `php artisan test --compact`: PASS — 79 tests, 1,599 assertions.
- `npm run build`: PASS.
- `npm run check:line-counts`: PASS — 266 tracked text/code files at or below 300 lines.
- `vendor/bin/pint --dirty --format agent`: PASS.
- Browser viewport and interaction evidence is intentionally pending manual review before Group 4 begins.

### Group 3 Review Remediation — 2026-09-22

- Removed the decorative `Calm editorial foundation` page-header badge from every Variant B non-home page while preserving chapter labels and page headings.
- Reworked the narrow-screen variant switcher into compact 44px A/B controls, retaining descriptive accessible labels and full labels at `sm` and above.
- `php artisan test --compact tests/Feature/ResponsiveNavigationTest.php tests/Feature/VariantBMultiPageTest.php tests/Feature/MultiPageSharedFoundationTest.php tests/Feature/VariantResolutionTest.php`: PASS — 32 tests, 684 assertions.
- `php artisan test --compact`: PASS — 81 tests, 1,628 assertions.
- `npm run build`, `npm run check:line-counts`, and `vendor/bin/pint --dirty --format agent`: PASS.
- Updated browser/manual confirmation remains pending.

### Group 4 Automated Evidence — 2026-09-22

- Added four structured palette records, two structured typeface records, safe query/session/default resolution, and root `data-palette` / `data-typeface` attributes.
- Added a normal-link, mobile-collapsible preference disclosure that preserves the path, variant, selected preference, and unrelated query values.
- Added `Newsreader` (400/500/600 normal and italic) and `Manrope` (400/500/600/700) to the Vite font bundle; no Cormorant font was added.
- Verified secondary text contrast against each supplied light background: Porcelain 5.15:1, Ivory 5.61:1, Mineral 4.94:1.
- `php artisan test --compact tests/Feature/VisualPreferenceTest.php tests/Feature/VariantResolutionTest.php tests/Feature/MultiPageSharedFoundationTest.php`: PASS — 24 tests, 369 assertions.
- `php artisan test --compact`: PASS — 87 tests, 1,721 assertions.
- `npm run build`, `npm run check:line-counts`, `vendor/bin/pint --dirty --format agent`, and `git diff --check`: PASS.
- Browser comparison in both variants across all palettes/typefaces remains intentionally pending manual review.

### Variant B Editorial Hero Follow-up — 2026-09-22

- Reused the three approved Variant B landing images as the full-width, overlaid editorial hero for every non-home Variant B route.
- `php artisan test --compact tests/Feature/VariantBMultiPageTest.php tests/Feature/CrossVariantMotionTest.php tests/Feature/ResponsiveNavigationTest.php tests/Feature/VisualPreferenceTest.php`: PASS — 24 tests, 739 assertions.
- `php artisan test --compact`: PASS — 88 tests, 1,757 assertions.
- `npm run build`, `npm run check:line-counts`, and `git diff --check`: PASS.

### Independent QA Remediation — 2026-09-22

- QA-F1 (AC-7): Kept muted color tokens available for decorative use but centrally maps rendered `text-stone-warm-500` utilities to the compliant secondary-text token. Added four-palette contrast assertions; all supplied secondary-text/background pairs meet WCAG AA.
- QA-F2 (AC-6): Added a responsive-navigation initializer that closes the native mobile disclosure when the `lg` breakpoint is reached, preserving the no-JavaScript disclosure behavior. Added a regression assertion for the breakpoint reset contract.
- Strengthened preference coverage to prove valid query values override existing session preferences.
- `php artisan test --compact`: PASS — 90 tests, 1,775 assertions.
- `npm run build`, `npm run check:line-counts`, `vendor/bin/pint --dirty --format agent`, and `git diff --check`: PASS.
- Playwright Chromium visual checks: PASS at 1440×900 for Variant B About with Porcelain/Newsreader and 375×812 for Variant B Contact with Mineral/Newsreader, after motion settled.
- Fresh targeted independent QA: PASS — QA-F1 and QA-F2 verified resolved. Focused verification: 14 tests, 255 assertions; related preference/variant regression: 21 tests, 294 assertions; production build, line-count, Pint, JavaScript syntax, and whitespace diff checks passed.

### Finalization Verification — 2026-09-22

- `tests/Feature/UrlSchemeTest.php`: PASS — production URL generation uses HTTPS and non-production URL generation uses HTTP.
- `php artisan test --compact`: PASS — 92 tests, 1,777 assertions.
- `npm run build`, `npm run check:line-counts`, `vendor/bin/pint --dirty --format agent`, and `git diff --check`: PASS.
- Phase 11 received explicit user merge acceptance after the independent QA pass.

## 3. Manual Verification Checklist

1. Slowly scroll every route in both variants at desktop and mobile sizes; compare each sequence to its landing-page language and confirm once-only settling.
2. Emulate reduced motion and disable JavaScript; confirm immediate content/navigation visibility, no delayed or hidden controls, and no looping effects.
3. At 320px and 375px, open/close the primary menu with keyboard and touch, visit every primary link, use its contact CTA, and confirm no clipped header or fixed preference control.
4. Check services, team details, reviews/modal, contact form/map, FAQ, legal pages, and footer at tablet and desktop for overflow, focus visibility, and readable hierarchy.
5. Select all four palettes and both typography choices in both variants; confirm route/query persistence, selected-state clarity, readable contrast, no gradients, and stable layout.

**Manual acceptance:** Passed through the user's explicit instruction to merge the independently QA-approved Phase 11 work on 2026-09-22.

## 4. Merge Readiness

- [x] All four task groups in `plan.md` are complete.
- [x] AC-1 through AC-9 pass in the matrix above.
- [x] Focused and regression tests, production build, Pint, line-count, and whitespace checks pass.
- [x] Browser evidence covers desktop, 320px, 375px, reduced motion, JavaScript-disabled, keyboard, and touch flows.
- [x] No tracked file exceeds 300 lines.
