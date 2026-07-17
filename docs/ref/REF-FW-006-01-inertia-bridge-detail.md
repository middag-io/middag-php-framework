---
ref: REF-FW-006-01
adr: FW-006
title: 'Shared Props, SPA-Safe Redirects, Partial Reloads & Protocol Evolution'
lang: en
---

# REF-FW-006-01: Shared Props, SPA-Safe Redirects, Partial Reloads & Protocol Evolution

> Detail supporting [FW-006](../decisions/FW-006-inertia-spa-bridge.md). Reconstructed from the `moodle-local_middag` legacy vault (ADR-804 + ref-804).

## Response shapes (legacy usage sample)

```php
return $this->inertia('Dashboard', ['items' => $items]);       // Inertia SPA
return $this->render_from_template('local_middag/page', $data); // host template
return $this->json_response($data);                             // JSON API
```

## Shared props registered at boot

| Prop                | Content                                        |
|---------------------|------------------------------------------------|
| `navigation`        | 3-level tree + `activeKey` + footer            |
| `auth`              | `{id, name, email, avatarUrl, capabilities[]}` |
| `theme`             | `{strings, appearance, brandColor, inherit}`   |
| `scope`             | identifiers registered by extensions at boot   |
| `flash`             | one-shot session flash                         |
| `locale`, `version` | —                                              |

Custom registration: `inertia_adapter::share('key', fn() => [...])`. Legacy anti-pattern flagged in the REF: a shared prop without a closure computes its value at boot time even when the request never needs it — always wrap in a closure so the cost is paid lazily, only when a page actually reads the prop.

## SPA-safe redirect

`inertia_location(route, params)`: the SPA client receives `409` plus an `X-Inertia-Location` header; a non-SPA request gets a normal redirect. Calling a plain `redirect()` from a route the SPA served is an anti-pattern — the SPA client ignores it silently, producing a confusing "nothing happened" bug rather than a clean failure.

## Partial reloads

The frontend sends `X-Inertia-Partial-Component` + `X-Inertia-Partial-Data`; the response filters props server-side to return only what was requested — this is the mechanism `middag-io/ui`'s `Table` block relies on for server-side sort/filter/pagination without a full page reload (see that package's UI-002).

## Legacy-to-current component mapping

| Legacy component (ADR-804)        | Current file                                                                                |
|-----------------------------------|---------------------------------------------------------------------------------------------|
| `inertia_adapter` (static facade) | `Http/Inertia/InertiaAdapter.php`                                                           |
| `inertia_factory`                 | `Http/Inertia/InertiaFactory.php`                                                           |
| `inertia_manager` (shared props)  | `Http/Inertia/InertiaManager.php`                                                           |
| `inertia_response`                | `Http/Inertia/InertiaResponse.php`                                                          |
| `inertia_version_manager`         | `Http/Inertia/InertiaVersionManager.php`                                                    |
| `inertia_field_mapper`            | `Form/Renderer/InertiaFieldMapper.php`                                                      |
| — (net-new, protocol v3)          | `Http/Inertia/DeferProp.php`, `Http/Inertia/MergeProp.php`, `Http/Inertia/OptionalProp.php` |

## Gaps recorded at decision time (legacy)

A frontend build pipeline (Vite) is required; Inertia is a runtime dependency — an unbuilt frontend fails as an error, not a silent fallback; **SSR was not implemented — SPA-only**; shared props grow with the number of extensions registering into them, requiring discipline to avoid becoming an undisciplined global-data dump. Whether SSR remains unimplemented in the current codebase was not verified in this pass — check `Http/Inertia/` directly before restating this as still true.

## Protocol-version disambiguation (a note to avoid cross-repo confusion)

"Inertia v3" here refers to the Inertia.js **wire protocol** version (lazy/`optional`, deferred/`defer`, `merge`/`deepMerge`, partial reloads, asset versioning) — the transport this ADR governs. `middag-io/ui`'s own "v2 capabilities" (polling, conditional row actions, confirmation dialogs, toasts, rich column variants, documented in that package's REF-UI-002-01) is a **different versioning axis**: the page-contract API's own capability rollout, layered on top of whatever Inertia protocol version ships underneath. The two numbers happening to both say "v2"/"v3" in their respective docs is coincidental, not a shared version line — do not cross-reference one as if it gated the other.
