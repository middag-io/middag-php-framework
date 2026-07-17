---
id: FW-001
title: 'Adopt PSR-15 as the Public HTTP Kernel Boundary'
status: accepted
date: 2026-05-20
domains: [framework, http]
related: [FW-002]
supersedes: []
superseded_by: null
lang: en
---

# FW-001: Adopt PSR-15 as the Public HTTP Kernel Boundary

> [!NOTE]
> **Provenance.** Reconstructed from the `moodle-local_middag` legacy vault (`ADR-208`, decided 2026-04-08, formally **Superseded**; `ADR-209`, decided 2026-05-20, the active decision). This is an archaeology pass, not a new decision — dates and rationale are historical. Included together because ADR-208's rejection is what motivates ADR-209's shape.

## Context

The framework needed one public contract for "handle an HTTP request, get a response" that every entry point, every controller, and every future host adapter could compile against. `ADR-208` first proposed adopting `Symfony\Component\HttpKernel\HttpKernel` directly as that public interface (`handle(Request $request, ...): Response`, pure HttpFoundation) — pragmatic, but it welds the public boundary to a single framework's object model. `ADR-209` reversed that: HttpFoundation is a implementation detail Symfony happens to use internally, not a spec; a plugin ecosystem's public contract should not be framework-bound.

## Decision

**PSR-15** (`Psr\Http\Server\RequestHandlerInterface` + `Psr\Http\Server\MiddlewareInterface`) is the public HTTP kernel boundary — `HttpKernelInterface extends RequestHandlerInterface`, `handle(ServerRequestInterface): ResponseInterface`. Symfony HttpKernel/HttpFoundation remain a valid **internal** implementation detail: the kernel bridges PSR-7 in to HttpFoundation via `HttpFoundationFactory`, runs routing/resolvers/controllers unchanged against `Request -> Response`, then bridges the final `Response` back to PSR-7 on the way out. CORS, exception mapping and pre-dispatch concerns are native PSR-15 middleware, not framework-specific hook points.

## Consequences

- The public surface is a PSR spec, not a Symfony type — any future adapter or consumer can compile against `HttpKernelInterface` without a hard Symfony dependency at the boundary, even though Symfony is used underneath.
- Internal HttpFoundation usage preserves its ergonomics (`JsonResponse`, `BinaryFileResponse`, `StreamedResponse`, `RedirectResponse`) for controller authors — the PSR-15 boundary only wraps entry and exit.
- The top-level `handle()` signature is a breaking change versus any pre-PSR-15 caller (`void` → `ResponseInterface`) — external consumers need a deprecation path.
- **Already the real implementation, not aspirational.** `src/Http/HttpKernel.php` implements this boundary today; `composer.json` carries exactly the dependency set ADR-209 called for (`psr/http-server-handler`, `psr/http-server-middleware`, `psr/http-message`, `symfony/psr-http-message-bridge`, `nyholm/psr7`) — the migration this ADR describes as planned is complete in the current codebase.

## Out of scope

- Routing table, controller hierarchy, the resolver chain and the declarative auth attribute built on top of this boundary — see [FW-002](./FW-002-attribute-routing-controller-auth-model.md).
- Concrete middleware catalog and the HttpFoundation bridge wiring detail — see REF-FW-001-01.

## Links

- [REF-FW-001-01 — PSR-15 Bridge Wiring & Middleware Catalog](../ref/REF-FW-001-01-psr15-bridge-and-middleware.md)
- [FW-002 — Attribute Routing, Controller Model & Declarative Auth](./FW-002-attribute-routing-controller-auth-model.md)
- [architecture.md](../architecture.md) — current implementation
- [bootstrap.md](../bootstrap.md) — current implementation
