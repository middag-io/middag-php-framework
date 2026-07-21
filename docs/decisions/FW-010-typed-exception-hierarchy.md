---
id: FW-010
title: 'Typed Exception Hierarchy'
status: accepted
date: 2026-04-05
lang: en
domains: [framework, exceptions]
deciders: ['PENDING — original decider not recorded during the legacy-vault reconstruction; confirm with Michael Meneses before ratifying']
related: [FW-002]
supersedes: []
superseded_by: null
enforced_by:
  mdgstan: []
  docs: [framework/reference/exception-hierarchy]
decision: 'A single MiddagException root, extending PHP native RuntimeException with no Moodle/Symfony dependency, exposes getStatusCode(); every concrete subclass fixes its own HTTP status, and each architectural layer is restricted to a specific subset it may throw.'
---

# FW-010: Typed Exception Hierarchy

> [!NOTE]
> **Provenance.** Reconstructed from the `moodle-local_middag` legacy vault (`ADR-706`, decided 2026-04-05). This is an archaeology pass, not a new decision — dates and rationale are historical.

## Context

The kernel needs to convert any thrown exception into the right HTTP status code without an `if/else`/`instanceof` chain scattered across the codebase, and different architectural layers need a narrow, predictable set of exception types they are allowed to throw — so a caller and a log reader both know at a glance what kind of failure occurred and where it originated.

## Considered Options

1. **`instanceof`/`if-else` chain at the kernel boundary, no fixed hierarchy.** Rejected — the chain grows unbounded as new exception types are added, and there is no single point that enforces which layer may throw what.
2. **Extend an existing host/library exception base** (Moodle's exception classes, or a Symfony exception type) instead of a framework-owned root. Rejected — it would couple the exception model to a specific host or dependency, contradicting the framework's host-agnostic design (the chosen root extends only PHP's native `\RuntimeException`).
3. **Let native/driver exceptions (e.g. a database exception) propagate across layer boundaries unwrapped.** Rejected — the caller on the other side of the boundary would see a foreign exception type the kernel has no status mapping for, and the original failure context would not be traceable through a consistent contract.
4. **Allow extensions to throw infrastructure-level exceptions directly.** Rejected — it bypasses the service that coordinates that concern and blurs the boundary between extension code and infrastructure code.

## Decision

A single root, `middag_exception` (extending PHP's own `\RuntimeException`, no Moodle/Symfony dependency), exposes `getStatusCode(): int`, and every concrete subclass fixes its own status. Each architectural layer is restricted to a specific subset it may throw: domain code throws `domain_exception`/`validation_exception`; infrastructure code throws `infrastructure_exception`/`persistence_exception`; the kernel/HTTP layer throws `lifecycle_violation_exception`/`authentication_exception`/`authorization_exception`; extensions throw domain-level exceptions only, never infrastructure exceptions directly — they delegate to the service that coordinates that concern. `middag_authentication_exception` specifically is thrown exclusively by the HTTP layer, never by domain/service code. Any native exception (a database driver exception, for example) is wrapped into the matching framework exception before crossing a layer boundary, always preserving the original as `previous`.

## Consequences

- The kernel converts any exception to a response by reading `getStatusCode()`, with no type-check chain to maintain as new exception types are added.
- A 500-class exception never leaks technical detail to the end user — the response carries a generic message, and detail goes to the log only.
- **Already the real implementation, essentially unchanged.** The full nine-class hierarchy this ADR describes exists today, verbatim in shape, just renamed to PascalCase: `Exception/{MiddagException, MiddagDomainException, MiddagValidationException, MiddagNotFoundException, MiddagInfrastructureException, MiddagPersistenceException, MiddagAuthenticationException, MiddagAuthorizationException, MiddagLifecycleViolationException}.php`. Consumed by `HttpKernel::mapThrowable` (per `architecture.md` §9.2) exactly as this ADR intended — this is one of the cleanest "the ADR is simply what shipped" cases in this reconstruction.
- The auth gate this hierarchy backs (`MiddagAuthenticationException`/`MiddagAuthorizationException`) is what [FW-002](./FW-002-attribute-routing-controller-auth-model.md)'s declarative `#[Auth]` pipeline throws on a failed check — the two decisions share one contract rather than each inventing its own failure signal.
- The full status-code table, per-layer usage matrix and anti-pattern catalog that supported this decision now live as a standalone reference doc rather than an ADR appendix — see Enforcement below.

## Enforcement

| Decision clause | Verification | State |
|---|---|---|
| Status-code map: root defaults to 500, each concrete subclass overrides only its declared status | `tests/Exception/ExceptionHierarchyTest.php` | coded |
| Every concrete exception descends from the `MiddagException` root (single `catch` covers all of them) | `tests/Exception/ExceptionHierarchyTest.php` | coded |
| Full status-code table, per-layer throw matrix and anti-pattern catalog | `framework/reference/exception-hierarchy` (docs-middag-dev) | coded |
| Each architectural layer restricted to a fixed subset of exception types it may throw (domain / infrastructure / kernel / extensions) | — (manual code review only, no static-analysis rule yet) | planned |
| `MiddagAuthenticationException` thrown exclusively by the HTTP layer, never domain/service code | — (manual code review only, no static-analysis rule yet) | planned |
| Native/driver exceptions wrapped before crossing a layer boundary, always preserving `previous` | — (manual code review only, no static-analysis rule yet) | planned |
