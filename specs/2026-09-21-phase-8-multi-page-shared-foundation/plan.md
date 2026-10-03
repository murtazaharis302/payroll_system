# Implementation Plan: Phase 8 — Multi-Page Shared Foundation

## Overview & Architecture Approach

Extend the current single-route Laravel Blade POC into a route-aware shared shell before creating either variant’s new page compositions. Keep reusable behavior and content variant-neutral; Variant A and Variant B retain isolated presentational components in the following phases.

## Task Groups

### Group 1: IA, Route Resolution & Metadata

- [ ] Task 1.1: Audit the existing root closure, layout, header, footer, switcher, and configured content; document and implement the public route map and resource-slug lookup without adding a database or controller layer unless Laravel conventions require it.
- [ ] Task 1.2: Refactor variant selection into one reusable route-resolution path that handles query/session/default precedence, preserves the page during switching, and produces 404 responses for unknown resource slugs.
- [ ] Task 1.3: Extend the layout/view contract with per-page title, description, canonical-ready path, placeholder Open Graph metadata, and one semantic H1 per rendered page.

### Group 2: Structured Content & Shared Components

- [ ] Task 2.1: Expand `config/clinic.php` or focused sibling config files with navigation, metadata, team, service-detail, FAQ, reviews, legal, contact, and explicit placeholder/approval flags while preserving established home-page data compatibility.
- [ ] Task 2.2: Create focused shared components for primary navigation, mobile navigation, footer links, resource metadata, contact information, FAQ items, review provenance, and a route-safe variant switcher.
- [ ] Task 2.3: Create reusable service-detail and team-detail data/view contracts for Phases 9 and 10; each consumes a record by slug rather than copy-pasted pages.

### Group 3: Frontend-Only Consultation Interaction

- [ ] Task 3.1: Implement one progressively enhanced, accessible client-side form module with labelled inputs, inline error text, `aria-live` feedback, client validation, reset behavior, and mock success messaging.
- [ ] Task 3.2: Confirm the module sends no request and writes no persistent data; ensure both variants can provide their own visual wrapper around the shared behavior.

### Group 4: Automated Contract Coverage

- [ ] Task 4.1: Add focused feature tests for every route, variant preservation, session/default/invalid variant behavior, resource lookup, 404 behavior, metadata, and required structured-content flags.
- [ ] Task 4.2: Add browser or DOM-level coverage for keyboard mobile navigation, switcher URLs, form validation/mock success, and no-submit behavior.
- [ ] Task 4.3: Verify the shared shell at 320px+, tablet, and desktop with JavaScript disabled and reduced motion; check source files remain under 300 lines.
