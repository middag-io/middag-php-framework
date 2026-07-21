---
id: FW-006
title: 'Inertia SPA Bridge'
status: accepted
date: 2026-04-04
lang: en
domains: [framework, http, rendering]
deciders: ['PENDING — original decider not recorded during the legacy-vault reconstruction; confirm with Michael Meneses before ratifying']
related: [FW-005]
supersedes: []
superseded_by: null
enforced_by:
  mdgstan: []
  docs: [framework/reference/inertia-protocol]
decision: 'Inertia is adopted as the framework-agnostic SPA transport: a static InertiaAdapter facade renders responses, a lazy-closure shared-props mechanism supplies global data once at boot, redirects use the SPA-safe 409 + X-Inertia-Location pattern, and partial reloads filter props server-side — all decoupled from whichever frontend framework consumes the protocol.'
---

# FW-006: Inertia SPA Bridge

> [!NOTE]
> **Provenance.** Reconstructed from the `moodle-local_middag` legacy vault (`ADR-804`, decided 2026-04-04). This is an archaeology pass, not a new decision — dates and rationale are historical.

## Context

An SPA screen needs global data (navigation, current user, theme, one-shot flash messages) available on every page without every controller wiring it by hand, a way to redirect without breaking client-side navigation state, and a way to refresh only part of a page's data instead of a full reload — while the transport protocol itself should not be tied to which frontend framework consumes it. At the time this decision was made, the product's frontend was still on Vue (aligned with Moodle's own internal use of Vue); the switch to React as the sole frontend stack (see `middag-io/ui`'s UI-001) happened afterwards without touching this protocol — Inertia stayed valid across that swap, only the rendering target changed.

## Considered Options

1. **Full-page reload on every data refresh**, instead of refreshing only the part of a page that changed. Rejected — the framework instead reads `X-Inertia-Partial-Component`/`X-Inertia-Partial-Data` request headers and filters shared/page props server-side down to only what was requested.
2. **A normal HTTP redirect returned from an SPA-served route.** Rejected — the Inertia client silently ignores a non-Inertia-aware redirect, producing a confusing "nothing happened" bug rather than a clean failure; the framework instead returns `409` plus an `X-Inertia-Location` header (an SPA-safe redirect).
3. **Coupling the wire protocol to a specific frontend framework.** At decision time the product's frontend was Vue (aligned with Moodle core's own use of Vue). The protocol was kept framework-agnostic instead, which let the later Vue→React swap (`middag-io/ui`'s UI-001) happen without any change to this bridge.
4. **Server-side rendering (SSR).** Out of scope at decision time — SPA-only was chosen. Not revisited in this reconstruction pass; verify the current `Http/Inertia/` code before asserting whether that gap has since closed.

## Decision

An `InertiaAdapter`-style static facade renders Inertia responses; an `InertiaResponse`-equivalent inspects the `X-Inertia` header to decide between a JSON payload and an HTML bootstrap shell; a shared-props mechanism registers global data once at boot via lazy closures (`navigation`, `auth`, `theme`, `scope`, `flash`, `locale`, `version`) so no controller repeats that wiring; a version manager fingerprints the frontend bundle for cache-busting. Redirects use an SPA-safe path: the client receives `409` plus an `X-Inertia-Location` header instead of a normal redirect Inertia would otherwise ignore. Partial reloads read `X-Inertia-Partial-Component`/`X-Inertia-Partial-Data` request headers and the response filters shared/page props down to only what was requested.

## Consequences

- Every SPA screen gets consistent global data with zero per-controller boilerplate; a lazy closure means a shared prop's cost is paid only when a page actually reads it.
- A normal (non-Inertia-aware) `redirect()` call inside an SPA-served route remains an anti-pattern — the SPA client ignores it silently; `inertia_location()`-equivalent must be used instead (see Enforcement).
- Shared props are an easy place to accumulate an undisciplined global-data dump as extensions register more of them — requires ongoing discipline, not a mechanism the framework enforces automatically.
- **Already the real implementation, and now a real superset of what this ADR described.** `Http/Inertia/{InertiaAdapter,InertiaFactory,InertiaManager,InertiaResponse,InertiaVersionManager}.php` implement exactly the mechanism above. What ADR-804 could not have anticipated: the framework now ships the **full Inertia v3 wire protocol** — lazy props (`optional`), deferred props (`defer`), prop merging (`merge`/`deepMerge`), on top of the partial reloads and asset versioning this ADR already called for (`Http/Inertia/{DeferProp,MergeProp,OptionalProp}.php`). This "v3" refers to the Inertia.js wire-protocol version itself — a different axis entirely from `middag-io/ui`'s own "v2 capabilities" versioning of the page-contract API (polling, confirmation dialogs, toasts); the two version numbers should not be conflated when cross-referencing the two repos.
- SSR remains out of scope per Considered Options above and was not revisited in this pass; confirm against the current `Http/Inertia/` code before asserting whether that gap has since closed.
- The form-field-to-React-component mapping is a related but separate decision — see [FW-004](./FW-004-form-renderer-adapters-field-type-dsl.md) (`Form/Renderer/InertiaFieldMapper.php`).
- Page-contract-level capabilities (polling, row actions, confirmation dialogs, toasts, rich column variants) are a `middag-io/ui` concern (that package's UI-002), not this bridge.

## Enforcement

| Decision clause | Verification | State |
|---|---|---|
| Shared-props catalog, SPA-safe redirect mechanics, partial-reload wire format, legacy-to-current component mapping, and protocol-version disambiguation are documented for readers who never read this ADR | Extracted to `framework/reference/inertia-protocol` in docs-middag-dev | coded |
| Shared props must be registered via a lazy closure (`fn() => [...]`), never an eagerly-computed value | No automated lint rule | planned |
| A plain `redirect()` must never be called from an SPA-served route — `inertia_location()`-equivalent required instead | No automated lint rule | planned |
