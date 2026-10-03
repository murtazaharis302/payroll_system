# Feature Requirements: Phase 7 — Global Animation Guidance Skill

## 1. Context & Business Intent

After both site motion languages are implemented and the cross-variant polish audit is complete, the project needs a durable, globally available Codex skill. It must inspect the existing system, identify the correct expressive or calm motion language for a target component, and guide future work without blindly applying one animation style everywhere.

**Lifecycle:** Specification: Ready | Implementation: Implemented | Validation: Pending.

## 2. Scope

### In-Scope

- [x] Create a globally installed Codex skill using the `skill-creator` workflow after Phases 4–6 are validated.
- [x] Have the skill inspect the project’s actual motion architecture, component location, active variant, data hooks, CSS primitives, and related specs before recommending or changing motion.
- [x] Define two named semantic profiles: **Expressive / 2D Cutout** for Variant A and **Calm / Editorial** for Variant B, with their distinct timing, distance, easing, stagger, image, hierarchy, and interaction rules.
- [x] Require the skill to preserve centralized `resources/js/app.js` ownership, reusable CSS primitives, progressive enhancement, reduced-motion behavior, no gradients, 44px targets, file modularity, and existing accessibility patterns.
- [x] Require explicit validation routing: targeted tests and build hygiene where source changes occur, plus Playwright desktop/mobile/reduced-motion interaction evaluation.
- [x] Include a decision path for shared components, unknown targets, missing hooks, unsupported effects, and when to ask the user rather than invent motion.

### Out-of-Scope

- Creating a second application-specific script runner or overwriting an existing global skill without inspection and user direction.
- Mandating animation for non-visual backend changes.
- Adding a new animation library, React bindings, copied reference designs, loops, marquess, or scroll hijacking.

## 3. Constraints & Dependencies

- This phase starts only after Phase 4, Phase 5, and the renamed Phase 6 cross-variant polish audit are validated; the skill documents proven architecture rather than hypothetical APIs.
- The skill is globally available through Codex skill installation, while project-specific facts stay traceable to the repository’s specs and code.
- The skill must be concise, scoped, and safe: inspect first; ask if the variant or intent cannot be determined.

## 4. Acceptance Criteria

- [x] **AC-1**: Given the preceding motion phases are validated, when the global skill is created, then it follows the `skill-creator` process and is available to Codex outside this repository.
- [x] **AC-2**: Given a target component is supplied, when the skill runs, then it inspects the active project’s motion system and the relevant component/spec before proposing animation work.
- [x] **AC-3**: Given a Variant A target, when motion guidance is produced, then it applies the expressive profile: layered, tactile, small directional depth and short related-item staggers without cartoonish or looping effects.
- [x] **AC-4**: Given a Variant B target, when motion guidance is produced, then it applies the calm editorial profile: slower, smaller, quiet hierarchy and restrained image/hairline treatment without card-style elevation or copied reference design.
- [x] **AC-5**: Given a shared component or an ambiguous target, when the profile cannot be safely inferred, then the skill identifies the ambiguity and asks for direction instead of selecting a style arbitrarily.
- [x] **AC-6**: Given a motion change is guided, when implementation and review occur, then centralized ownership, progressive enhancement, reduced-motion behavior, accessible focus/touch targets, no gradients, no loops, and relevant Playwright verification are all required.

## 5. Edge Cases & Error Handling

- If a project has no recognized Variant A/B context, the skill must present neutral shared-motion guidance and request the intended profile.
- If a component already has compliant hooks, the skill must avoid duplicate initialization or independent observer logic.
- If a requested effect violates project constraints, the skill must explain the conflict and offer a compliant alternative.
