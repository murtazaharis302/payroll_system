# Feature Requirements: Phase 8 — Multi-Page Shared Foundation

## 1. Context & Business Intent

The POC has two validated homepages but must become one complete client-facing clinic website with two visual interpretations. This phase establishes the single information architecture, route contract, editable content source, navigation, SEO basics, and frontend-only interaction foundations that both Variant A and Variant B consume. It preserves the existing homepages except where shared navigation or integration requires an adjustment.

**Lifecycle:** Specification: Ready | Implementation: Not Started | Validation: Pending.

## 2. Scope

### In-Scope

- [ ] Define one equivalent public route map: `/`, `/about`, `/services`, `/services/{service}`, `/team`, `/team/{member}`, `/patient-journey`, `/reviews`, `/contact`, `/faq`, `/privacy`, and `/terms`.
- [ ] Extend the existing variant resolver so every equivalent route accepts `?variant=a|b`, retains session fallback, safely defaults invalid variants to `a`, and makes the switcher change only the variant while preserving the current path and non-variant query parameters.
- [ ] Centralize all editable site content in structured Laravel configuration: clinic/contact/hours, navigation, page metadata, team, services, FAQs, reviews, legal placeholders, and client-approval or placeholder flags.
- [ ] Define reusable, data-driven contracts for service detail, team detail, FAQ item, review presentation, contact information, metadata, and the frontend-only consultation form.
- [ ] Replace the homepage-only header/footer actions with shared, concise primary navigation: Home, About, Treatments, Team, Patient Journey, Reviews, Contact. Detail pages remain contextual destinations rather than primary items.
- [ ] Provide accessible desktop and mobile navigation, consistent active-page state, visible focus, keyboard operation, and 44px minimum interactive targets in both variants.
- [ ] Provide unique page titles, meta descriptions, canonical-ready paths, semantic H1 ownership, internal links, service/team slugs, and clearly marked placeholder Open Graph fields without an overbuilt SEO subsystem.
- [ ] Specify client-only contact/consultation validation and a polished mock-success state with no request, email, persistence, authentication, booking backend, database, models, migrations, CMS, or API.

### Out-of-Scope (Non-Goals)

- Page-specific Variant A or Variant B visual compositions; those belong to Phases 9 and 10.
- Changing medical facts, verified credentials, contact facts, or supplied images without client-provided source material.
- Real Google review links or testimonials before the client supplies a destination and approved review content.
- Real privacy policy or terms copy; their pages must visibly identify final text as requiring client approval.

## 3. Content, Route & Safety Contract

- Existing Dr. Bhatti data may be rendered as the established POC content. Supporting team entries must use clearly labelled placeholders and must not receive invented credentials, biographies, accreditations, or clinical claims.
- Service records require stable slugs and fields for introduction, suitability, process, benefits, technology/materials, FAQs, related services, and CTA. Wording remains informational and avoids outcomes, guarantees, or unsupported clinical claims.
- Every existing review must be explicitly marked as a placeholder until approved content is supplied. A Google destination is configurable but absent/disabled when not client-provided; no fabricated review URL is presented as real.
- Unknown team or service slugs return a standard not-found response for both variants. The named examples `dr-tariq-bhatti` and `dental-implants` must resolve once configured.
- Shared content must render identically in meaning across variants. Styling, composition, imagery treatment, and motion may differ.

## 4. Acceptance Criteria

- [ ] **AC-1**: Given any defined public page, when opened with `?variant=a` or `?variant=b`, then the equivalent route renders in the selected design language and the switcher preserves that path while changing only the variant.
- [ ] **AC-2**: Given an invalid variant, invalid resource slug, or unknown route, when requested, then variant resolution safely falls back to A where applicable and unknown resources/routes receive the framework’s not-found response.
- [ ] **AC-3**: Given content is updated in the structured site configuration, when either variant renders a shared page, then clinic, contact, service, team, FAQ, review, navigation, legal, and metadata content comes from that source rather than duplicated page copy.
- [ ] **AC-4**: Given desktop or mobile navigation is used, when a visitor traverses primary pages, then the shared IA, active state, keyboard access, visible focus, and touch-target requirements are present without detail-page clutter.
- [ ] **AC-5**: Given any page loads, when its document metadata is inspected, then it has a unique title, description, semantic H1, canonical-ready path, internal-link structure, and labelled placeholder Open Graph architecture.
- [ ] **AC-6**: Given team, service, review, FAQ, and legal content is displayed, when its provenance is unknown or final approval is pending, then placeholders and approval requirements are explicit and no credentials, testimonials, legal text, review destination, medical guarantees, or factual claims are fabricated.
- [ ] **AC-7**: Given a visitor completes the consultation form, when fields are invalid, then labelled client-side errors are announced; when valid, a polished mock success appears without network submission or persistence.
- [ ] **AC-8**: Given shared components are integrated into all public routes, when JavaScript is unavailable or reduced motion is enabled, then content and navigation remain immediately usable and the existing single Motion Mini / observer architecture remains the only animation runtime.

## 5. Edge Cases & Error Handling

- Preserve unrelated query parameters when changing variants; replace only `variant`.
- Do not imply an appointment has been booked after mock form success; clearly describe it as a POC confirmation.
- Ensure page metadata and route labels have a safe default when a new resource record is incomplete.
- Protect all user-provided form values from unsafe DOM insertion in the client-side success treatment.
