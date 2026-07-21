---
id: FW-003
title: 'Schema-First Forms with Optional FormRequest Escalation'
status: accepted
date: 2026-04-13
lang: en
domains: [framework, forms]
deciders: ['PENDING — original decider not recorded during the legacy-vault reconstruction; confirm with Michael Meneses before ratifying']
related: [FW-004]
supersedes: []
superseded_by: null
enforced_by:
  mdgstan: []
  docs: [framework/reference/form-lifecycle-and-validation]
decision: 'Form.schema() is the primary form abstraction (typed fields with inline validation rules); FormRequest is an optional escalation for cross-field validation, DB-backed rules, or validation shared with a REST endpoint — never the default path.'
---

# FW-003: Schema-First Forms with Optional FormRequest Escalation

> [!NOTE]
> **Provenance.** Reconstructed from the `moodle-local_middag` legacy vault (`ADR-802`, decided 2026-04-13, superseding an earlier 2026-04-05 version). This is an archaeology pass, not a new decision — dates and rationale are historical.

## Context

Fields were declared in a host form-builder's `definition()` method while validation logic lived wherever a developer happened to put it — constructor, a callback, or nowhere — so the same field shape ended up described twice (once for the UI, once for validation) with no single source of truth and no way to share validation with a REST endpoint covering the same data.

## Considered Options

1. **Keep validation ad hoc per form** (status quo) — rejected: schema duplicated across UI and validation, no way to share a rule set with a REST endpoint covering the same payload. This is the failure mode the decision replaces.
2. **Support only per-field schema validation, no escalation mechanism at all** — rejected: cannot express cross-field validation, DB-backed rules, or a rule set shared between a form and a REST endpoint — cases already present in the codebase.
3. **Make `form_request` mandatory for every form** — rejected in favor of keeping the common case (simple per-field rules) lightweight; escalate only when a form genuinely needs it.
4. **Express conditional field behaviour as a procedural callback** evaluated by each renderer — rejected in favor of schema data, so the same declaration (`visible_when`/`required_when`/`disabled_when`/`hidden_when`) renders correctly on every target without re-implementing the logic per renderer.
5. **`schema()` + optional `form_request` escalation** ← chosen.

## Decision

`form` is the primary abstraction: a `schema()` method returns typed fields with inline validation rules for the common case. `form_request` is an **optional escalation**, not the default path, for cross-field validation, DB-backed rules, or validation shared between a form and a REST endpoint covering the same payload — attached to a form via a `REQUEST` class constant, or used standalone with no form at all. Lifecycle is four fixed stages: Construct (`for($entity?)`) → Hydrate (the resolver populates from the request) → Validate (schema rules, then the `REQUEST` class if declared, in that order) → Submit (`is_submitted_and_valid()` / `validated()`). Labels, help text, option lists and error messages resolve through the translation contract — no raw strings. Conditional field behaviour is schema data, not a procedural callback, so the same declaration renders correctly on every target (see FW-004 for how a renderer consumes it).

## Consequences

- One schema is the single source of truth for both rendering and validation — the duplicated-schema failure mode this decision replaces cannot recur by construction.
- `REQUEST`-class validation precedes and can override schema validation when both are declared, with an explicit precedence rule (no duplicate rule declaration across the two).
- **Already the real implementation.** `Form/AbstractForm.php`, `Form/FormValidator.php` and `Form/ConditionEvaluator.php` exist today; the escalation path is `Http/Request/AbstractFormRequest.php`, resolved via `Http/Resolver/FormRequestResolver.php` — the same resolver-chain mechanism documented in FW-002. Verified against source: `FormResolver` and `FormRequestResolver` are two independent resolvers, each triggered by type-hinting the corresponding class on a controller method — the current code carries no `REQUEST`-constant property read by either resolver, so today "attaching" a `FormRequest` to a `Form` is a controller-signature convention, not framework-enforced wiring. Full lifecycle, field/validation catalog and this caveat's detail: `framework/reference/form-lifecycle-and-validation` (in `docs-middag-dev`).
- The legacy form-builder migration this ADR originally described (soft deprecation, PoC-gated, eventual removal) is now moot for this repo specifically — the standalone package never carried the legacy renderer to migrate away from; that migration, if it still applies anywhere, is a concern of whichever host adapter still wraps an older form engine.
- Out of scope: the renderer adapters that translate a schema into a concrete wire format (mform HTML, Inertia JSON), and the closed field-type catalog itself, are FW-004's concern, not this decision's.

## Enforcement

| Decision clause | Verification | State |
|---|---|---|
| `schema()` is the primary abstraction; `FormRequest` is an optional escalation, never the default | no automated rule (architectural convention, code review only) | **planned** |
| Lifecycle order Construct → Hydrate → Validate → Submit, hydrate/validate only on mutating HTTP verbs | `tests/Form/AbstractFormTest.php`, `tests/Http/Resolver/FormResolverTest.php` | **coded** |
| `FormRequest` rule takes precedence over a schema rule for the same field; never duplicate | no automated cross-check between a `Form::schema()` and a companion `FormRequest::rules()` today | **planned** |
| Field/validation catalog, lifecycle usage, i18n rule and legacy migration notes | doc `framework/reference/form-lifecycle-and-validation` | **coded** |
