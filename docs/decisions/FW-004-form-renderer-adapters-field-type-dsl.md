---
id: FW-004
title: 'Renderer Adapter Boundary & Field Type DSL'
status: accepted
date: 2026-04-13
domains: [framework, forms]
related: [FW-003]
supersedes: []
superseded_by: null
lang: en
---

# FW-004: Renderer Adapter Boundary & Field Type DSL

> [!NOTE]
> **Provenance.** Reconstructed from the `moodle-local_middag` legacy vault (`ADR-805`, decided 2026-04-13, renderer adapters; `ADR-806`, decided 2026-04-13, field type DSL contract). Merged: the field DSL only matters because a closed set of renderer adapters has to translate it faithfully, and the two ADRs were decided the same day as two halves of one boundary.

## Context

The form schema (FW-003) must render to more than one target — a host's native form widgets, and an Inertia SPA payload — without the public DSL ever leaking a host-specific type into code that should stay host-agnostic. A field-type catalog with no fixed boundary invites both silent host-type leakage and an ever-growing, inconsistent set of ad hoc field variants.

## Decision

`FormRendererInterface` is the anti-corruption boundary: `target(): RenderTarget` (an enum) plus `render(FormInterface): RendererOutput` — no renderer's internals ever escape into the schema layer. Each renderer is an internal implementation targeting one output shape; a controller picks a default renderer, with a per-call override when a specific route needs a different target. The field-type DSL is a closed, versioned catalog behind a single static factory (`FieldFactory`) — concrete field classes are `@internal`; only the factory is `@api`. Reserved field names (`id`, `submit`, `cancel`, `save`, `_token`) and snake_case-equivalent naming are validated at construction, not left to convention.

## Consequences

- The DSL never depends on a specific renderer's types — swapping or adding a renderer never touches `Form/` schema code.
- A closed factory means field types grow by adding a factory method plus (when needed) a backing enum case, not by an unbounded set of ad hoc classes.
- **Already the real implementation, evolved beyond the legacy names.** `Form/Renderer/InertiaRenderer.php` implements `Middag\Ui\Form\FormRendererInterface` — the interface itself now lives in `middag-io/ui`, not in this framework, because the UI package owns the wire contract a renderer emits (see `middag-io/ui`'s own UI-001/UI-002 decisions). The live `FieldFactory` renamed the legacy `int()`/`float()` methods to `integer()`/`decimal()`, and collapses `email()`/`password()`/`url()` into `TextField` variants selected by an explicit `FieldType` case rather than separate field classes — see REF-FW-004-01 for the full before/after catalog.
- Since the standalone package ships no host-specific renderer, the "densest part of the project" risk the legacy ADR flagged (mapping every field type × modifier onto a host's native widgets) now lives entirely in whichever adapter repo builds that renderer, not in this framework.

## Out of scope

- The concrete shape of a host adapter's non-Inertia renderer (e.g. a Moodle `mform`-style mapping) — lives in that adapter's own decisions record.
- The page-contract/composition model that consumes rendered form output as a block — see `middag-io/ui`'s UI-002.
- Full field-type catalog, condition operators, naming rules and the anti-corruption enforcement mechanism — see REF-FW-004-01.

## Links

- [REF-FW-004-01 — Field Type Catalog, Condition Operators & Anti-Corruption Enforcement](../ref/REF-FW-004-01-field-catalog-and-anti-corruption.md)
- [FW-003 — Schema-First Forms with Optional FormRequest Escalation](./FW-003-schema-first-forms-form-request-escalation.md)
- [architecture.md](../architecture.md) — current implementation
