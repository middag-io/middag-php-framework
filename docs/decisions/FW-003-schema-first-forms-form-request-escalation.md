---
id: FW-003
title: 'Schema-First Forms with Optional FormRequest Escalation'
status: accepted
date: 2026-04-13
domains: [framework, forms]
related: [FW-004]
supersedes: []
superseded_by: null
lang: en
---

# FW-003: Schema-First Forms with Optional FormRequest Escalation

> [!NOTE]
> **Provenance.** Reconstructed from the `moodle-local_middag` legacy vault (`ADR-802`, decided 2026-04-13, superseding an earlier 2026-04-05 version). This is an archaeology pass, not a new decision — dates and rationale are historical.

## Context

Fields were declared in a host form-builder's `definition()` method while validation logic lived wherever a developer happened to put it — constructor, a callback, or nowhere — so the same field shape ended up described twice (once for the UI, once for validation) with no single source of truth and no way to share validation with a REST endpoint covering the same data.

## Decision

`form` is the primary abstraction: a `schema()` method returns typed fields with inline validation rules for the common case. `form_request` is an **optional escalation**, not the default path, for cross-field validation, DB-backed rules, or validation shared between a form and a REST endpoint covering the same payload — attached to a form via a `REQUEST` class constant, or used standalone with no form at all. Lifecycle is four fixed stages: Construct (`for($entity?)`) → Hydrate (the resolver populates from the request) → Validate (schema rules, then the `REQUEST` class if declared, in that order) → Submit (`is_submitted_and_valid()` / `validated()`). Labels, help text, option lists and error messages resolve through the translation contract — no raw strings. Conditional field behaviour (`visible_when`/`required_when`/`disabled_when`/`hidden_when`) is schema data, not a procedural callback, so the same declaration renders correctly on every target (see FW-004 for how a renderer consumes it).

## Consequences

- One schema is the single source of truth for both rendering and validation — the duplicated-schema failure mode this decision replaces cannot recur by construction.
- `REQUEST`-class validation precedes and can override schema validation when both are declared, with an explicit precedence rule (no duplicate rule declaration across the two).
- **Already the real implementation.** `Form/AbstractForm.php`, `Form/FormValidator.php` and `Form/ConditionEvaluator.php` exist today; the escalation path is `Http/Request/AbstractFormRequest.php`, resolved via `Http/Resolver/FormRequestResolver.php` — the same resolver-chain mechanism documented in FW-002.
- The legacy form-builder migration this ADR originally described (soft deprecation, PoC-gated, eventual removal) is now moot for this repo specifically — the standalone package never carried the legacy renderer to migrate away from; that migration, if it still applies anywhere, is a concern of whichever host adapter still wraps an older form engine.

## Out of scope

- The renderer adapters that translate a schema into a concrete wire format (mform HTML, Inertia JSON), and the closed field-type catalog itself — see [FW-004](./FW-004-form-renderer-adapters-field-type-dsl.md).
- Built-in validation rule catalog, the lifecycle code sample and the naming/migration detail preserved from the legacy vault — see REF-FW-003-01.

## Links

- [REF-FW-003-01 — Form Lifecycle, Validation Rule Catalog & Legacy Migration Notes](../ref/REF-FW-003-01-form-lifecycle-and-validation-catalog.md)
- [FW-004 — Renderer Adapter Boundary & Field Type DSL](./FW-004-form-renderer-adapters-field-type-dsl.md)
- [architecture.md](../architecture.md) — current implementation
