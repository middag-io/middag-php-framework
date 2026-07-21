---
id: FW-004
title: 'Renderer Adapter Boundary & Field Type DSL'
status: accepted
date: 2026-04-13
lang: en
domains: [framework, forms]
deciders: ['PENDING — original decider not recorded during the legacy-vault reconstruction; confirm with Michael Meneses before ratifying']
related: [FW-003]
supersedes: []
superseded_by: null
enforced_by:
  mdgstan: []
  docs: [framework/reference/form-field-catalog-and-anti-corruption]
decision: 'FormRendererInterface (target() + render()) is the sole anti-corruption boundary between the host-agnostic form schema and renderer internals, backed by a closed, versioned field-type DSL behind a single FieldFactory.'
---

# FW-004: Renderer Adapter Boundary & Field Type DSL

> [!NOTE]
> **Provenance.** Reconstructed from the `moodle-local_middag` legacy vault (`ADR-805`, decided 2026-04-13, renderer adapters; `ADR-806`, decided 2026-04-13, field type DSL contract). Merged: the field DSL only matters because a closed set of renderer adapters has to translate it faithfully, and the two ADRs were decided the same day as two halves of one boundary.

## Context

The form schema (FW-003) must render to more than one target — a host's native form widgets, and an Inertia SPA payload — without the public DSL ever leaking a host-specific type into code that should stay host-agnostic. A field-type catalog with no fixed boundary invites both silent host-type leakage and an ever-growing, inconsistent set of ad hoc field variants.

## Considered Options

1. **No fixed renderer boundary** — let each renderer consume or expose its own host-specific types directly against the shared schema layer. Rejected: invites silent host-type leakage into code that must stay host-agnostic.
2. **An open, ad hoc field-type catalog** — add a new field class whenever a new UI need appears, with no closed factory or naming validation. Rejected: produces an ever-growing, inconsistent set of variants with no single point enforcing reserved names or naming rules.
3. **A closed anti-corruption boundary (`FormRendererInterface`) plus a closed, versioned field-type DSL behind a single `FieldFactory`** ← chosen.

## Decision

`FormRendererInterface` is the anti-corruption boundary: `target(): RenderTarget` (an enum) plus `render(FormInterface): RendererOutput` — no renderer's internals ever escape into the schema layer. Each renderer is an internal implementation targeting one output shape; a controller picks a default renderer, with a per-call override when a specific route needs a different target. The field-type DSL is a closed, versioned catalog behind a single static factory (`FieldFactory`) — concrete field classes are `@internal`; only the factory is `@api`. Reserved field names (`id`, `submit`, `cancel`, `save`, `_token`) and snake_case-equivalent naming are validated at construction, not left to convention.

## Consequences

- The DSL never depends on a specific renderer's types — swapping or adding a renderer never touches `Form/` schema code.
- A closed factory means field types grow by adding a factory method plus (when needed) a backing enum case, not by an unbounded set of ad hoc classes.
- **Already the real implementation, evolved beyond the legacy names.** `Form/Renderer/InertiaRenderer.php` implements `Middag\Ui\Form\FormRendererInterface` — the interface itself now lives in `middag-io/ui`, not in this framework, because the UI package owns the wire contract a renderer emits. The live `FieldFactory` renamed the legacy `int()`/`float()` methods to `integer()`/`decimal()`, and collapses `email()`/`password()`/`url()` into `TextField` variants selected by an explicit `FieldType` case rather than separate field classes — see `framework/reference/form-field-catalog-and-anti-corruption` (in `docs-middag-dev`) for the full before/after catalog.
- Since the standalone package ships no host-specific renderer, the "densest part of the project" risk the legacy ADR flagged — mapping every field type × modifier onto a host's native widgets — now lives entirely in whichever adapter repo builds that renderer, not in this framework; the concrete shape of such a renderer (e.g. a Moodle `mform`-style mapping) belongs in that adapter's own decisions record.
- The page-contract/composition model that consumes rendered form output as a block is a separate decision — see `middag-io/ui`'s UI-002.

## Enforcement

| Decision clause | Verification | State |
|---|---|---|
| `FormRendererInterface` (`target()` + `render()`) is the sole anti-corruption boundary between schema and renderer internals | doc `framework/reference/form-field-catalog-and-anti-corruption` | **coded** |
| Field-type DSL closed behind a single `@api` `FieldFactory`; concrete field classes `@internal` | doc `framework/reference/form-field-catalog-and-anti-corruption` | **coded** |
| Reserved field names (`id`, `submit`, `cancel`, `save`, `_token`) and naming rules validated at field construction | runtime check in `FieldFactory`/`AbstractField` (throws `InvalidArgumentException`) | **coded** |
| No host-specific rendering type (e.g. an mform type) may appear inside `Form/` | no automated import-boundary check in this repo — currently true only because no host renderer ships here; a host adapter must police its own boundary | **planned** |
