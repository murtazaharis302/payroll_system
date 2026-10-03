# Implementation Plan: Phase 7 — Global Animation Guidance Skill

## Overview & Architecture Approach

This phase turns the validated motion system into an inspect-first reusable Codex skill. It deliberately occurs last so its rules reflect working code and measured Variant B reference findings, not assumptions.

## Task Groups

### Group 1: Evidence Intake & Scope

- [x] Task 1.1: Confirm Phase 4, Phase 5, and Phase 6 validation status is complete; read the implemented motion engine, CSS primitives, audit, and representative components.
- [x] Task 1.2: Identify the minimum reusable instructions, decision points, and validation contract without duplicating transient project copy or source code.

### Group 2: Skill Authoring

- [x] Task 2.1: Invoke `skill-creator` to create the globally installed skill with a focused name and a complete `SKILL.md`.
- [x] Task 2.2: Encode the inspect-first workflow, variant/profile selection, shared-component ambiguity handling, architecture constraints, and escalation rules.
- [x] Task 2.3: Include concise profile tables for expressive and calm motion, explicitly separating timing, distance, easing, stagger, image treatment, and interactive feedback.

### Group 3: Skill Validation

- [x] Task 3.1: Exercise the skill against one Variant A component, one Variant B component, and one shared/ambiguous component without making speculative changes.
- [x] Task 3.2: Verify the outcomes identify the existing system, select or request the correct profile, and require reduced-motion and Playwright checks.
- [x] Task 3.3: Confirm the installed skill can be discovered globally and that its guidance does not authorize prohibited effects or decentralized scripts.
