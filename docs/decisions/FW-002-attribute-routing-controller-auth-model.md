---
id: FW-002
title: 'Attribute Routing, Controller Model & Declarative Auth'
status: accepted
date: 2026-04-04
domains: [framework, http]
related: [FW-001, FW-010]
supersedes: []
superseded_by: null
lang: en
---

# FW-002: Attribute Routing, Controller Model & Declarative Auth

> [!NOTE]
> **Provenance.** Reconstructed from the `moodle-local_middag` legacy vault (`ADR-801`, decided 2026-04-04, routing and controller model; `ADR-301`, decided 2026-03-29, authentication/authorization contracts). Merged into one ADR: the source material itself notes the `#[auth]` attribute "integrates directly ... with the same precedence method > class > none" as the routing model already documents — describing the same kernel pipeline twice across two documents would fragment one mechanism.

## Context

A plugin ecosystem needs multiple entry points (each product/plugin exposing its own `index.php`/`webhook.php`/`ajax.php`-style front door) to share one kernel, one container and one route collection, without every controller re-implementing capability checks by hand. PHP 8 attributes replace annotation-comment routing (`#[Route]` over `@Route`); a declarative `#[auth]`/`#[Auth]` attribute replaces scattering `has_capability()`-style calls through every action, so auth requirements are visible at the call site and enforced by the kernel before the action runs.

## Decision

Routes are declared with `#[Route]` (Symfony's attribute form) on controller methods; auth requirements are declared with `#[Auth(login:, capabilities:, context:, instanceId:)]` on the method or the class, **method wins over class, no attribute means open**. Three controller shapes cover the rendering spectrum: a host-page controller (server-rendered template), an API controller (JSON envelope), and either composed with an Inertia SPA response — the kernel does not force a screen into one shape. A fixed resolver chain turns the incoming request into the action's typed parameters (route parameters, request objects, validated payloads, container-resolved services, Inertia-aware parameters), first resolver that can resolve a given parameter wins. Auth enforcement runs as a fixed pipeline stage — route match, then auth flags, then the auth gate, then `preHandle()`, then middleware, then the action — before any handler code executes, and a failed check throws a typed exception (`MiddagAuthenticationException`/`MiddagAuthorizationException`, see FW-010) rather than being checked ad hoc inside the action.

## Consequences

- One declarative surface for auth across every controller shape — no per-controller-kind auth matrix to keep in sync.
- Attribute scanning is a boot-time, cached cost, not a per-request cost — the ecosystem's route/auth metadata does not scale linearly with request volume, only with boot time.
- `#[Auth]`'s merge rule (method-level replaces class-level — first non-empty wins) is deliberately the opposite of the per-route `#[Middleware]` stacking rule (class and method accumulate) — auth is a gate where the most specific requirement is authoritative, middleware is a pipeline where every applicable layer must run.
- **Already the real implementation.** `src/Http/Attribute/Auth.php` and `src/Http/Controller/{AbstractController,AbstractApiController}.php` exist today, and the auth surface grew beyond the original ADR-301 shape: capability strings remain first-class, but `#[Auth]` now also accepts a richer `requirements: list<CapabilityRequirement>` so an adapter can carry structured capability data instead of a bare string — see REF-FW-002-01.

## Out of scope

- The exception hierarchy and its HTTP status mapping — see [FW-010](./FW-010-typed-exception-hierarchy.md).
- The bridge contracts a host adapter implements to resolve user/session/capability data (`UserContextResolverInterface`, etc.) — see `architecture.md` §5.
- The resolver chain's exact ordering, the auth error-response matrix, CSRF handling and anti-patterns — see REF-FW-002-01.

## Links

- [REF-FW-002-01 — Resolver Chain, Auth Error Matrix & Anti-Patterns](../ref/REF-FW-002-01-resolver-chain-and-auth-detail.md)
- [FW-001 — Adopt PSR-15 as the Public HTTP Kernel Boundary](./FW-001-psr15-http-kernel-boundary.md)
- [FW-010 — Typed Exception Hierarchy](./FW-010-typed-exception-hierarchy.md)
- [architecture.md](../architecture.md) — current implementation
