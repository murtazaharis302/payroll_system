# Validation & Merge Readiness: Phase 0 — Foundation, Shared IA & Switcher Scaffolding

## Validation Status Breakdown
- **Automated Tests**: PASS
- **Independent QA Audit**: PASS (Verdict: QA VERDICT: PASSED)
- **Manual User Acceptance**: Passed (User explicit merge instruction)
- **Overall Feature Status**: Validated & Merged

---

## 1. Acceptance Criteria Verification Matrix

| AC ID | Description | Verification Method | Pass / Fail |
|---|---|---|---|
| AC-1 | Default visit to `/` resolves Variant A and persists `variant => 'a'` in session | Automated Feature Test (`VariantResolutionTest::test_default_route_renders_variant_a`) | [x] PASS |
| AC-2 | Visiting `/?variant=b` or `/?variant=a` resolves correct variant and updates session | Automated Feature Test (`VariantResolutionTest::test_query_parameter_switches_to_variant_b`, `test_query_parameter_switches_to_variant_a`) | [x] PASS |
| AC-3 | Invalid variant query param falls back safely to Variant A | Automated Feature Test (`VariantResolutionTest::test_invalid_variant_parameter_defaults_to_variant_a`) | [x] PASS |
| AC-4 | Floating variant switcher `<x-shared.variant-switcher />` renders on page with active indicator | Automated Feature Test (`VariantResolutionTest::test_switcher_component_rendered_in_response`) | [x] PASS |
| AC-5 | Central clinical content repository `config('clinic')` exists with all required keys and structure | Automated Feature Test (`VariantResolutionTest::test_clinic_config_has_complete_structure`) | [x] PASS |
| AC-6 | Typography and Tailwind CSS v4 design tokens configured without gradients | Static inspection of `vite.config.js` and `resources/css/app.css` & `npm run build` | [x] PASS |

---

## 2. Automated Test Suite
- `tests/Feature/VariantResolutionTest.php`:
  - `test_default_route_renders_variant_a()`: Asserts 200 OK, sees Variant A marker, asserts `session('variant') === 'a'`.
  - `test_query_parameter_switches_to_variant_b()`: Asserts 200 OK, sees Variant B marker, asserts `session('variant') === 'b'`.
  - `test_query_parameter_switches_to_variant_a()`: Asserts 200 OK, sees Variant A marker, asserts `session('variant') === 'a'`.
  - `test_invalid_variant_parameter_defaults_to_variant_a()`: Asserts 200 OK, sees Variant A marker, asserts `session('variant') === 'a'`.
  - `test_session_persists_across_subsequent_requests()`: Asserts session variant persists across requests.
  - `test_switcher_component_rendered_in_response()`: Asserts switcher elements and links exist in response HTML.
  - `test_clinic_config_has_complete_structure()`: Asserts `config('clinic')` contains `name`, `tagline`, `contact`, `hours`, `doctor`, `treatments`, and `journey`.

---

## 3. Manual Verification Checklist
1. Start local dev server via `php artisan serve` and `npm run dev`.
2. Navigate to `http://127.0.0.1:8000/` in browser:
   - Confirm Variant A scaffold renders.
   - Confirm floating switcher pill appears (bottom or top).
   - Confirm "Variant A" is highlighted as active.
3. Click "Variant B" on the floating switcher:
   - Confirm page switches to `/?variant=b`.
   - Confirm Variant B scaffold renders.
   - Confirm "Variant B" is highlighted as active.
4. Navigate to `http://127.0.0.1:8000/` without query parameters:
   - Confirm session persists and Variant B remains active.
5. Inspect CSS and console:
   - Confirm Source Serif 4 and Work Sans fonts are loaded.
   - Confirm zero console errors.
   - Confirm no gradients are present in styles.

---

## 4. Merge Readiness (Definition of Done)
- [x] All task groups in `plan.md` marked complete.
- [x] All ACs in `requirements.md` verified in matrix above.
- [x] Automated tests in `tests/Feature/VariantResolutionTest.php` pass without errors.
- [x] Independent QA investigator audit completed with verdict `QA VERDICT: PASSED`.
- [x] Every tracked text and code file strictly under 300 lines.
- [x] `git diff --check` passes cleanly.
