---
ref: REF-FW-002-01
adr: FW-002
title: 'Resolver Chain, Auth Error Matrix & Anti-Patterns'
lang: en
---

# REF-FW-002-01: Resolver Chain, Auth Error Matrix & Anti-Patterns

> Detail supporting [FW-002](../decisions/FW-002-attribute-routing-controller-auth-model.md). Reconstructed from the `moodle-local_middag` legacy vault (ADR-801 + ref-801, ADR-301 + REF-301-01).

## Legacy route/auth attribute shape

```php
#[Route(path: '/items', name: 'items_index', methods: ['GET'])]
#[auth(login: true, capabilities: ['local/middag:manage'])]
public function index(): Response { ... }

#[Route(path: '/ping', name: 'ping', methods: ['GET'])]
#[auth(login: false)]
public function ping(): JsonResponse { ... }
```

`#[Route]` is Symfony's `Attribute\Route`, never `Annotation\Route`. `#[auth]` lived in `local_middag\framework\contract\attributes\auth`; precedence was method attribute > class attribute > no auth (open). The kernel read it via reflection in `apply_route_auth()` before `pre_handle()`.

## Three controller shapes — legacy to current

| Base (legacy)                   | Mode                                          | Current OSS equivalent                                                                                                     |
|---------------------------------|-----------------------------------------------|----------------------------------------------------------------------------------------------------------------------------|
| `base\controller` (Moodle page) | HTML via `$OUTPUT`/Mustache                   | `AbstractController` composed with a host-adapter template renderer (adapter concern, not a separate framework base class) |
| `base\api_controller`           | JSON API, envelope `{success, data, message}` | `Http/Controller/AbstractApiController`                                                                                    |
| `base\controller` + `inertia()` | SPA Inertia                                   | `Http/Controller/AbstractController` + `InertiaAdapter::render()` (see FW-006)                                             |

The first row is a real narrowing: in the legacy plugin, "Moodle page" was a first-class framework base class because the framework lived inside the Moodle plugin. In the standalone OSS package there is no host template engine to render against, so a host-rendered page is host-adapter composition on top of the generic `AbstractController`, not a framework-provided third base class — a direct consequence of the portability decision (FW-011).

## Resolver chain — fixed order, first resolver wins

| Legacy resolver (ADR-801)  | Current file                                                                                                            |
|----------------------------|-------------------------------------------------------------------------------------------------------------------------|
| `route_parameter_resolver` | `Http/Resolver/RouteParameterResolver.php`                                                                              |
| `request_resolver`         | `Http/Resolver/RequestResolver.php`                                                                                     |
| `form_request_resolver`    | `Http/Resolver/FormRequestResolver.php`                                                                                 |
| `container_resolver`       | `Http/Resolver/ContainerResolver.php`                                                                                   |
| `inertia_resolver`         | `Http/Resolver/InertiaResolver.php`                                                                                     |
| — (net-new)                | `Http/Resolver/FormResolver.php`, `Http/Resolver/ValidatedDtoResolver.php`, `Http/Resolver/MethodParameterResolver.php` |

The chain grew two net-new resolvers not described by ADR-801: `FormResolver` (bridges `AbstractForm`, see FW-003) and `ValidatedDtoResolver` (the `#[ValidatedDto]` path, see architecture.md §9.3). The exact relative ordering of the three net-new resolvers against the five legacy ones is not restated by any ADR read for this pass — confirm against `MethodParameterResolver`'s dispatch logic in `src/Http/Resolver/` before depending on a precise sequence.

## Plugin-aware URL generation (legacy detail, host-specific)

Symfony's `RequestContext` does not natively support multiple base URLs per route. The legacy fix was a `plugin_aware_url_generator`: every route carried a `_plugin_base` default, injected by the route loader from the extension's source, and `router::generate_url()` returned a Moodle-native `moodle_url`. This is host-adapter territory now — the framework's `RouteLoader` (`src/Http/Routing/RouteLoader.php`) scans `#[Route]` attributes into a plain `RouteCollection`; multi-base-URL resolution for a specific host is that host adapter's problem to solve, not a framework contract.

## Auth error matrix (legacy, adapter-owned today)

| Controller      | Failure                                              | Response                                   |
|-----------------|------------------------------------------------------|--------------------------------------------|
| API controller  | invalid/expired token, no session                    | `401` — `MiddagAuthenticationException`    |
| API controller  | missing capability, API disabled, invalid CSRF token | `403` — `MiddagAuthorizationException`     |
| Page controller | not logged in                                        | host-specific redirect (adapter concern)   |
| Page controller | missing capability                                   | host-specific error page (adapter concern) |

The kernel's job stops at throwing the typed exception (FW-010); rendering a host-native login redirect or error page for a page controller is the adapter's job, not the kernel's, in the current design — a cleaner split than the legacy matrix, which baked Moodle-specific redirect behaviour into the kernel itself.

## CSRF

Legacy: exclusively Moodle's native `sesskey` for session-authenticated writes; `wstoken` calls used no separate CSRF check; the framework introduced no parallel CSRF middleware. Current: `Http/Middleware/VerifyCsrfMiddleware.php` is a real, host-neutral PSR-15 middleware — a generalisation of the sesskey-specific rule into a mechanism any adapter (Moodle, WordPress, standalone) can wire its own token source into.

## Auth contracts (ADR-301, legacy shape)

```php
interface authentication_interface {
    public function require_login(): void;
    public function is_logged_in(): bool;
    public function is_guest(): bool;
    public function require_sesskey(): void;
}

interface capability_interface {
    public function can(string $capability, context_level $contextlevel, int $instanceid, ?int $userid): bool;
    public function authorize(...): void; // throws middag_authorization_exception
}

interface authorizer_interface extends authentication_interface, capability_interface {}
```

Rule: inject the narrowest interface a service actually needs (`authentication_interface` for session-only checks, `capability_interface` for capability-only checks, `authorizer_interface` only when a consumer genuinely needs both).

## Current `#[Auth]` — richer than the legacy shape

The live `Http/Attribute/Auth.php` keeps the legacy string-capability surface (`capabilities: list<string>`) as first-class, but adds a `requirements: list<CapabilityRequirement>` field: a `CapabilityRequirement` (`Http/Auth/CapabilityRequirement.php`) can wrap a bare string, a `CapabilityReference`, or a full `CapabilityDefinitionInterface` implementation, so a rich host adapter can carry structured capability metadata instead of losing information down to a plain string. This is a real evolution beyond ADR-301's original two-interface design, not a discrepancy to flag — the legacy `authentication_interface`/`capability_interface` split still holds conceptually (login vs capability), it is just that the capability side of `#[Auth]` grew a richer optional payload.

## Anti-patterns (legacy REF-301-01, still applicable)

- Calling a host's native capability check directly instead of going through the framework's auth gate (bypasses the host-neutral boundary).
- Performing auth checks in a controller constructor instead of the `#[Auth]` attribute or inside the action body.
- Requiring a CSRF/session token check (`sesskey`-equivalent) on a `GET` route — that check exists to protect mutations.
- Checking a capability inside a template/view instead of passing a pre-computed boolean prop (auth logic does not belong in presentation).
- Swallowing `MiddagAuthorizationException` silently instead of letting it propagate to the kernel's exception mapping.

## Discovery/wiring note

Attribute scanning happens once at boot and is cached by the container — this bounds the cost to boot time rather than per-request time, and the shared `RouteCollection` grows linearly with the number of registered controllers (acceptable given the cache).
