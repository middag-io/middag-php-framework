---
id: FW-010
title: 'Typed Exception Hierarchy'
status: accepted
date: 2026-04-05
domains: [framework, exceptions]
related: [FW-002]
supersedes: []
superseded_by: null
lang: en
---

# FW-010: Typed Exception Hierarchy

> [!NOTE]
> **Provenance.** Reconstructed from the `moodle-local_middag` legacy vault (`ADR-706`, decided 2026-04-05). This is an archaeology pass, not a new decision — dates and rationale are historical.

## Context

The kernel needs to convert any thrown exception into the right HTTP status code without an `if/else`/`instanceof` chain scattered across the codebase, and different architectural layers need a narrow, predictable set of exception types they are allowed to throw — so a caller and a log reader both know at a glance what kind of failure occurred and where it originated.

## Decision

A single root, `middag_exception` (extending PHP's own `\RuntimeException`, no Moodle/Symfony dependency), exposes `getStatusCode(): int`, and every concrete subclass fixes its own status. Each architectural layer is restricted to a specific subset it may throw: domain code throws `domain_exception`/`validation_exception`; infrastructure code throws `infrastructure_exception`/`persistence_exception`; the kernel/HTTP layer throws `lifecycle_violation_exception`/`authentication_exception`/`authorization_exception`; extensions throw domain-level exceptions only, never infrastructure exceptions directly — they delegate to the service that coordinates that concern. `middag_authentication_exception` specifically is thrown exclusively by the HTTP layer, never by domain/service code. Any native exception (a database driver exception, for example) is wrapped into the matching framework exception before crossing a layer boundary, always preserving the original as `previous`.

## Consequences

- The kernel converts any exception to a response by reading `getStatusCode()`, with no type-check chain to maintain as new exception types are added.
- A 500-class exception never leaks technical detail to the end user — the response carries a generic message, and detail goes to the log only.
- **Already the real implementation, essentially unchanged.** The full nine-class hierarchy this ADR describes exists today, verbatim in shape, just renamed to PascalCase: `Exception/{MiddagException, MiddagDomainException, MiddagValidationException, MiddagNotFoundException, MiddagInfrastructureException, MiddagPersistenceException, MiddagAuthenticationException, MiddagAuthorizationException, MiddagLifecycleViolationException}.php`. Consumed by `HttpKernel::mapThrowable` (per `architecture.md` §9.2) exactly as this ADR intended — this is one of the cleanest "the ADR is simply what shipped" cases in this reconstruction.

## Out of scope

- The full status-code table, per-layer usage matrix and anti-pattern catalog — see REF-FW-010-01.

## Links

- [REF-FW-010-01 — Status Code Table, Layer Usage Matrix & Anti-Patterns](../ref/REF-FW-010-01-exception-hierarchy-detail.md)
- [FW-002 — Attribute Routing, Controller Model & Declarative Auth](./FW-002-attribute-routing-controller-auth-model.md)
- [architecture.md](../architecture.md) — current implementation
