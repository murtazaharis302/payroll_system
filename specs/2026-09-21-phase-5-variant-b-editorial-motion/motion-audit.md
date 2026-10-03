# Dental Design SD Motion Audit — Phase 5

**Audited:** 2026-09-21 before Variant B source edits
**Method:** Playwright 1.57 with Chromium; repeated reloads, slow full-page scrolling, and navigation/control inspection at 1440×900 and 390×844.

## Observed Motion Qualities

- The desktop page opens with photographic hero content already composed in place, followed by a short, restrained content reveal. Computed animations exposed several one-time `hideContent` animations with a 2000ms duration; the only repeating animations were video loading indicators.
- Down-page movement reads as quiet editorial pacing rather than a sequence of cards flying into view. Large photographic blocks, hairline dividers, compact labels, and content groups give sections their cadence.
- Controls are visually direct and manual: buttons, navigation, and gallery/carousel arrows invite an explicit action. No marquee-like content progression was observed.
- On mobile, the composition becomes a single-column reading flow. Content stays legible without long delayed sequences; the page relies more on hierarchy and imagery than conspicuous movement.
- The observed site uses a different information architecture, typography, photography, and responsive layout. This audit records rhythm only and does not reproduce its markup, layout, assets, or source code.

## Variant B Translation

- Use the existing Phase 4 observer exactly once per element, with an `editorial` profile: roughly 8–14px rises, 500–820ms durations, and a soft decelerating curve. This is slower and smaller than Variant A's expressive profile.
- Sequence eyebrow, divider, headline, copy, then actions; do not split words or add a loading screen.
- Reveal portraits and maps through a soft mask with a restrained 1.02-to-1 image scale. Draw only real hairlines for treatment and journey progression.
- Stagger dense accreditation, treatment, review, and footer groups modestly. Keep carousel navigation manual-first and keep the review modal fixed so it does not shift the page layout.
- On mobile, retain the same semantic hooks but use zero stagger delay. Reduced motion and the absent-JavaScript path keep all content immediately visible.

## Post-Implementation Comparison Target

Variant B should feel slower, smaller, and quieter than Variant A while preserving its own calm editorial layout. Validation will compare local desktop and mobile full-scroll behavior to this rhythm, not to the reference's visual composition.

## Post-Implementation Comparison Result

**Compared:** 2026-09-21 at 1440×900 and 390×844 during the recorded local Playwright verification.

- The local page retained the reference-informed rhythm: an already composed full-bleed hero, then quiet one-time grouped reveals during a full scroll rather than card fly-ins or looping movement.
- At mobile width, the page read as a single column and retained direct manual controls; the hero and review controls did not introduce a continuous progression.
- The implementation is deliberately distinct: it uses the clinic’s own content, imagery, clinical hierarchy, and navigation. The comparison evaluated pacing and interaction rhythm only, not reference layout or assets.
