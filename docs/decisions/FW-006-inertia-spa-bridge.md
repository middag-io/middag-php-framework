---
id: FW-006
title: 'Inertia SPA Bridge'
status: accepted
date: 2026-04-04
domains: [framework, http, rendering]
related: [FW-005]
supersedes: []
superseded_by: null
lang: en
---

# FW-006: Inertia SPA Bridge

> [!NOTE]
> **Provenance.** Reconstructed from the `moodle-local_middag` legacy vault (`ADR-804`, decided 2026-04-04). This is an archaeology pass, not a new decision — dates and rationale are historical.

## Context

An SPA screen needs global data (navigation, current user, theme, one-shot flash messages) available on every page without every controller wiring it by hand, a way to redirect without breaking client-side navigation state, and a way to refresh only part of a page's data instead of a full reload — while the transport protocol itself should not be tied to which frontend framework consumes it. At the time this decision was made, the product's frontend was still on Vue (aligned with Moodle's own internal use of Vue); the switch to React as the sole frontend stack (see `middag-io/ui`'s UI-001) happened afterwards without touching this protocol — Inertia stayed valid across that swap, only the rendering target changed.

## Decision

An `InertiaAdapter`-style static facade renders Inertia responses; an `InertiaResponse`-equivalent inspects the `X-Inertia` header to decide between a JSON payload and an HTML bootstrap shell; a shared-props mechanism registers global data once at boot via lazy closures (`navigation`, `auth`, `theme`, `scope`, `flash`, `locale`, `version`) so no controller repeats that wiring; a version manager fingerprints the frontend bundle for cache-busting. Redirects use an SPA-safe path: the client receives `409` plus an `X-Inertia-Location` header instead of a normal redirect Inertia would otherwise ignore. Partial reloads read `X-Inertia-Partial-Component`/`X-Inertia-Partial-Data` request headers and the response filters shared/page props down to only what was requested.

## Consequences

- Every SPA screen gets consistent global data with zero per-controller boilerplate; a lazy closure means a shared prop's cost is paid only when a page actually reads it.
- A normal (non-Inertia-aware) `redirect()` call inside an SPA-served route is an anti-pattern — the SPA client ignores it; `inertia_location()`-equivalent must be used instead.
- Shared props are an easy place to accumulate an undisciplined global-data dump as extensions register more of them — requires ongoing discipline, not a mechanism the framework enforces automatically.
- **Already the real implementation, and now a real superset of what this ADR described.** `Http/Inertia/{InertiaAdapter,InertiaFactory,InertiaManager,InertiaResponse,InertiaVersionManager}.php` implement exactly the mechanism above. What ADR-804 could not have anticipated: the framework now ships the **full Inertia v3 wire protocol** — lazy props (`optional`), deferred props (`defer`), prop merging (`merge`/`deepMerge`), on top of the partial reloads and asset versioning this ADR already called for (`Http/Inertia/{DeferProp,MergeProp,OptionalProp}.php`). This "v3" refers to the Inertia.js wire-protocol version itself — a different axis entirely from `middag-io/ui`'s own "v2 capabilities" versioning of the page-contract API (polling, confirmation dialogs, toasts); the two version numbers should not be conflated when cross-referencing the two repos.
- SSR was explicitly out of scope at decision time (SPA-only) — not revisited in this pass; confirm against the current `Http/Inertia/` code before asserting whether that gap has since closed.

## Out of scope

- The form-field-to-React-component mapping — see [FW-004](./FW-004-form-renderer-adapters-field-type-dsl.md) (`Form/Renderer/InertiaFieldMapper.php`).
- Page-contract-level capabilities (polling, row actions, confirmation dialogs, toasts, rich column variants) — a `middag-io/ui` concern (see that package's UI-002/REF-UI-002-01), not this bridge.
- Full shared-props table, redirect/partial-reload mechanics and legacy-to-current component mapping — see REF-FW-006-01.

## Links

- [REF-FW-006-01 — Shared Props, SPA-Safe Redirects, Partial Reloads & Protocol Evolution](../ref/REF-FW-006-01-inertia-bridge-detail.md)
- [FW-005 — Multi-Mode Rendering Model](./FW-005-multi-mode-rendering-model.md)
- [architecture.md](../architecture.md) — current implementation
