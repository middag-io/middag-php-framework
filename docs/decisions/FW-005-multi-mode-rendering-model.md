---
id: FW-005
title: 'Multi-Mode Rendering Model — Host Pages, Inertia SPA, JSON API'
status: accepted
date: 2026-04-05
lang: en
domains: [framework, http, rendering]
deciders: ['PENDING — original decider not recorded during the legacy-vault reconstruction; confirm with Michael Meneses before ratifying']
related: [FW-006, FW-002]
supersedes: []
superseded_by: null
enforced_by:
  mdgstan: []
  docs: [framework/reference/rendering-mode-selection]
decision: 'Three rendering modes — host-page HTML, Inertia SPA, and pure JSON API — coexist per controller base, freely mixable within a single extension, with no framework-owned template engine.'
---

# FW-005: Multi-Mode Rendering Model — Host Pages, Inertia SPA, JSON API

> [!NOTE]
> **Provenance.** Reconstructed from the `moodle-local_middag` legacy vault (`ADR-803`, decided 2026-04-05). This is an archaeology pass, not a new decision — dates and rationale are historical.

## Context

Not every screen justifies a full SPA round-trip, and not every screen is simple enough for a bare server-rendered page — forcing either extreme onto every controller produces either needless client-side overhead on trivial admin screens or hand-rolled complexity on rich ones.

## Considered Options

1. **Route every screen through Inertia SPA rendering**, with no plain host-page path — rejected: needless client-side round-trip overhead on trivial admin/settings screens that a bare server-rendered page handles fine.
2. **Route every screen through bare server-rendered HTML only**, with no SPA option — rejected: forces hand-rolled complexity onto screens that need richer, dynamic interaction.
3. **Build a framework-owned textual template engine** (Blade/Twig-shaped) to handle page composition — rejected: page composition already belongs to the UI package's declarative page-contract model; a framework-internal templating layer would only duplicate that responsibility.

## Decision

Three rendering modes coexist, selected by which controller base a given controller extends: a host-page controller returning server-rendered HTML, the same controller family composed with an Inertia response for SPA screens, and a dedicated API controller returning pure JSON. A single extension may freely mix controllers across all three modes with no conflict, and using Inertia is never mandatory — a simple extension can stay entirely on host pages. No framework-owned template engine is built (Considered Options #3): page composition stays declarative, owned by the UI package's page-contract model, not by a framework-internal templating layer.

## Consequences

- Extensions choose the lightest mode that fits each screen instead of a one-size-fits-all rendering strategy.
- Not building a bespoke template engine keeps the framework's surface smaller — composition logic lives once, in the UI package, rather than duplicated across a framework templating layer and a UI builder layer.
- The Inertia protocol itself (shared props, redirects, partial reloads, version manager) is scoped entirely to [FW-006](./FW-006-inertia-spa-bridge.md) — not repeated here.
- The page-contract/block composition model that replaced a framework-owned template engine is a `middag-io/ui` concern (that package's UI-002), not this framework's.
- **A boundary narrowed since this ADR was written.** The legacy `abstract_widget` and `table_builder` (a wrapper over Moodle's `flexible_table`/`html_table`, with the explicit rule that extensions never instantiate those Moodle classes directly) do not exist in this framework's `src/` — there is no top-level `Widget/` or `Table/` concern here. That responsibility now belongs entirely to `middag-io/ui`'s own `Table/` builder (the `dense_table`-style block documented in that package's UI-002/REF-UI-002-01), since a Moodle-specific table abstraction has no place in a host-agnostic package. The rule this ADR stated ("extensions never touch the host's native table class directly") survives, just enforced one layer up, in the UI package's block contract rather than in this framework.
- The full mode-selection table (legacy base → current base class mapping) and the rendering anti-patterns list no longer live inline in this ADR — see Enforcement below.

## Enforcement

| Decision clause | Verification | State |
|---|---|---|
| Three modes exist, distinguished only by controller base (`AbstractController` vs `AbstractApiController`) and whether `InertiaAdapter::render()` is called | `Http/Controller/AbstractController.php` and `AbstractApiController.php` both exist in `src/`; no third base class | coded |
| No framework-owned template engine (Blade/Twig-shaped) is introduced | No automated check — verify via `composer.json`/`src/` dependency review | planned |
| Mode-selection table and rendering anti-patterns (controller-boundary bypass, host-global page metadata, in-template logic) | doc `framework/reference/rendering-mode-selection` | coded |
