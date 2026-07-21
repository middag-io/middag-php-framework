---
id: FW-002
title: 'Attribute Routing, Controller Model & Declarative Auth'
status: accepted
date: 2026-04-04
lang: en
domains: [framework, http]
deciders: ['PENDING — original decider not recorded during the legacy-vault reconstruction; confirm with Michael Meneses before ratifying']
related: [FW-001, FW-010]
supersedes: []
superseded_by: null
enforced_by:
  mdgstan: []
  docs: [framework/reference/attribute-routing-and-auth-model]
decision: 'PHP 8 #[Route]/#[Auth] attributes are the routing and auth-declaration surface, with method-over-class-over-none precedence; the kernel enforces auth as a fixed pipeline stage before any handler code runs, across three supported controller shapes.'
---

# FW-002: Attribute Routing, Controller Model & Declarative Auth

> [!NOTE]
> **Provenance.** Reconstructed from the `moodle-local_middag` legacy vault (`ADR-801`, decided 2026-04-04, routing and controller model; `ADR-301`, decided 2026-03-29, authentication/authorization contracts). Merged into one ADR: the source material itself notes the `#[auth]` attribute "integrates directly ... with the same precedence method > class > none" as the routing model already documents — describing the same kernel pipeline twice across two documents would fragment one mechanism.

## Context

A plugin ecosystem needs multiple entry points (each product/plugin exposing its own `index.php`/`webhook.php`/`ajax.php`-style front door) to share one kernel, one container and one route collection, without every controller re-implementing capability checks by hand. Routing and auth requirements need to be visible at the call site and enforced by the kernel before an action runs, rather than discovered by reading each action's body — and the rendering spectrum a controller can target (host-rendered page, JSON API, SPA) is wide enough that no single controller shape covers it.

## Considered Options

1. **Annotation-comment routing** (Doctrine-style `@Route` PHPDoc annotations) — rejected: string-parsed at runtime, not type-checked, and not IDE-navigable the way a native PHP 8 attribute is.
2. **Ad-hoc imperative auth checks scattered through each action body** (a `has_capability()`-style call inline wherever needed) — rejected: hides the auth requirement from the call site, and one missed call anywhere silently opens the endpoint.
3. **A single controller base class forcing every entry point into one rendering shape** (host page only, or API-JSON only) — rejected: the plugin ecosystem's front doors genuinely need different response shapes; forcing one would push every other shape into adapter-side faking.
4. **`#[Route]` (Symfony's attribute form) for routing, `#[Auth(login:, capabilities:, context:, instanceId:)]` for declarative kernel-enforced auth**, method-over-class-over-none precedence, three supported controller shapes ← chosen.

## Decision

Routes are declared with `#[Route]` (Symfony's attribute form) on controller methods; auth requirements are declared with `#[Auth(login:, capabilities:, context:, instanceId:)]` on the method or the class — method wins over class, no attribute means open. Three controller shapes cover the rendering spectrum: a host-page controller (server-rendered template), an API controller (JSON envelope), and either composed with an Inertia SPA response — the kernel does not force a screen into one shape. A fixed resolver chain turns the incoming request into the action's typed parameters (route parameters, request objects, validated payloads, container-resolved services, Inertia-aware parameters) — first resolver that can resolve a given parameter wins; the exact chain, controller-shape mapping and auth mechanics live in `framework/reference/attribute-routing-and-auth-model` (in `docs-middag-dev`). Auth enforcement runs as a fixed pipeline stage — route match, then auth flags (`setRequireLogin()`/`setRequireCapabilities()`), then `applyPlatformAuth()` (an adapter hook), then `preHandle()`, then middleware, then the action — before any handler code executes; a failed OSS login check throws a typed exception (`MiddagAuthenticationException`, see FW-010) on API/JSON requests rather than being checked ad hoc inside the action.

## Consequences

- One declarative surface for auth across every controller shape — no per-controller-kind auth matrix to keep in sync.
- Attribute scanning is a boot-time, cached cost, not a per-request cost — the ecosystem's route/auth metadata does not scale linearly with request volume, only with boot time.
- `#[Auth]`'s merge rule (method-level replaces class-level — first non-empty wins) is deliberately the opposite of the per-route `#[Middleware]` stacking rule (class and method accumulate) — auth is a gate where the most specific requirement is authoritative, middleware is a pipeline where every applicable layer must run.
- **Already the real implementation.** `src/Http/Attribute/Auth.php` and `src/Http/Controller/{AbstractController,AbstractApiController}.php` exist today, and the auth surface grew beyond the original ADR-301 shape: capability strings remain first-class, but `#[Auth]` now also accepts a richer `requirements: list<CapabilityRequirement>` field so an adapter can carry structured capability data instead of a bare string — full detail in the reference doc below.
- Capability enforcement itself is adapter territory, not kernel territory: `AbstractController::setRequireCapabilities()` is a no-op by default, and the OSS kernel never throws `MiddagAuthorizationException` on its own — only the login gate (`AuthenticatorInterface`-backed) is OSS-enforced. The exception hierarchy this gate throws into is a separate decision — see [FW-010](./FW-010-typed-exception-hierarchy.md).
- The bridge contracts a host adapter implements to resolve user/session/capability data (`UserContextResolverInterface` and siblings) live in `architecture.md` §5, not here — this ADR fixes the auth *model* (attribute, precedence, pipeline stage), not the resolver contracts a host plugs in.

## Enforcement

| Decision clause | Verification | State |
|---|---|---|
| `#[Route]`/`#[Auth]` attribute model — method > class > none precedence; resolver chain order; controller-shape mapping; CSRF; auth error behavior | doc `framework/reference/attribute-routing-and-auth-model` | **coded** |
| Auth pipeline stage order (route match → auth flags → `applyPlatformAuth()` → `preHandle()` → middleware → action) runs before any handler code | no automated check | **planned** |
| Host-adapter bridge contracts (`UserContextResolverInterface` and siblings) | `architecture.md` §5 | **coded** |
