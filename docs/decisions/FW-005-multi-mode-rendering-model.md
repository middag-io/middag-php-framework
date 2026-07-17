---
id: FW-005
title: 'Multi-Mode Rendering Model — Host Pages, Inertia SPA, JSON API'
status: accepted
date: 2026-04-05
domains: [framework, http, rendering]
related: [FW-006, FW-002]
supersedes: []
superseded_by: null
lang: en
---

# FW-005: Multi-Mode Rendering Model — Host Pages, Inertia SPA, JSON API

> [!NOTE]
> **Provenance.** Reconstructed from the `moodle-local_middag` legacy vault (`ADR-803`, decided 2026-04-05). This is an archaeology pass, not a new decision — dates and rationale are historical.

## Context

Not every screen justifies a full SPA round-trip, and not every screen is simple enough for a bare server-rendered page — forcing either extreme onto every controller produces either needless client-side overhead on trivial admin screens or hand-rolled complexity on rich ones.

## Decision

Three rendering modes coexist, selected by which controller base a given controller extends: a host-page controller returning server-rendered HTML, the same controller family composed with an Inertia response for SPA screens, and a dedicated API controller returning pure JSON. A single extension may freely mix controllers across all three modes with no conflict, and using Inertia is never mandatory — a simple extension can stay entirely on host pages. The framework deliberately does **not** build its own textual template engine (nothing Blade/Twig-shaped): page composition is declarative, owned by the UI package's page-contract model, not by a framework-internal templating layer.

## Consequences

- Extensions choose the lightest mode that fits each screen instead of a one-size-fits-all rendering strategy.
- Not building a bespoke template engine keeps the framework's surface smaller — composition logic lives once, in the UI package, rather than duplicated across a framework templating layer and a UI builder layer.
- **A boundary narrowed since this ADR was written.** The legacy `abstract_widget` and `table_builder` (a wrapper over Moodle's `flexible_table`/`html_table`, with the explicit rule that extensions never instantiate those Moodle classes directly) do not exist in this framework's `src/` — there is no top-level `Widget/` or `Table/` concern here. That responsibility now belongs entirely to `middag-io/ui`'s own `Table/` builder (the `dense_table`-style block documented in that package's UI-002/REF-UI-002-01), since a Moodle-specific table abstraction has no place in a host-agnostic package. The rule this ADR stated ("extensions never touch the host's native table class directly") survives, just enforced one layer up, in the UI package's block contract rather than in this framework.

## Out of scope

- The Inertia protocol itself (shared props, redirects, partial reloads, version manager) — see [FW-006](./FW-006-inertia-spa-bridge.md).
- The page-contract/block composition model that replaced a framework-owned template engine — see `middag-io/ui`'s UI-002.
- Anti-patterns and the full mode-selection table — see REF-FW-005-01.

## Links

- [REF-FW-005-01 — Mode Selection Table & Rendering Anti-Patterns](../ref/REF-FW-005-01-rendering-mode-detail.md)
- [FW-006 — Inertia SPA Bridge](./FW-006-inertia-spa-bridge.md)
- [FW-002 — Attribute Routing, Controller Model & Declarative Auth](./FW-002-attribute-routing-controller-auth-model.md)
- [architecture.md](../architecture.md) — current implementation
