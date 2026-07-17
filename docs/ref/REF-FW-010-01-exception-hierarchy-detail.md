---
ref: REF-FW-010-01
adr: FW-010
title: 'Status Code Table, Layer Usage Matrix & Anti-Patterns'
lang: en
---

# REF-FW-010-01: Status Code Table, Layer Usage Matrix & Anti-Patterns

> Detail supporting [FW-010](../decisions/FW-010-typed-exception-hierarchy.md). Reconstructed from the `moodle-local_middag` legacy vault (ADR-706 + ref-706).

## Hierarchy and status codes — legacy to current

| Legacy exception (ADR-706)                           | Current class                       | HTTP status |
|------------------------------------------------------|-------------------------------------|-------------|
| `middag_exception` (abstract, `@api`)                | `MiddagException`                   | 500         |
| `middag_domain_exception` (`@api`)                   | `MiddagDomainException`             | 400         |
| `middag_validation_exception` (`@api`)               | `MiddagValidationException`         | 422         |
| `middag_not_found_exception` (`@api`)                | `MiddagNotFoundException`           | 404         |
| `middag_infrastructure_exception` (`@api`)           | `MiddagInfrastructureException`     | 500         |
| `middag_persistence_exception` (`@api`)              | `MiddagPersistenceException`        | 500         |
| `middag_authentication_exception` (`@api`)           | `MiddagAuthenticationException`     | 401         |
| `middag_authorization_exception` (`@api`)            | `MiddagAuthorizationException`      | 403         |
| `middag_lifecycle_violation_exception` (`@internal`) | `MiddagLifecycleViolationException` | 500         |

`middag_domain_exception`/`MiddagDomainException` and `middag_infrastructure_exception`/`MiddagInfrastructureException` are the two branch nodes; `validation_exception`/`not_found_exception` hang off the domain branch, `persistence_exception` off the infrastructure branch — a shallow, two-level tree under the single `middag_exception` root, all living in `classes/framework/exception/` (legacy) / `Exception/` (current) with no facade — plain `throw`/`catch` by direct import.

## Layer usage matrix

| Layer                               | Allowed to throw                                                                             |
|-------------------------------------|----------------------------------------------------------------------------------------------|
| `framework/domain/`                 | `domain_exception`, `validation_exception`                                                   |
| `framework/infrastructure/`         | `infrastructure_exception`, `persistence_exception`                                          |
| `framework/moodle/` (host boundary) | `infrastructure_exception` (wraps host failures)                                             |
| `framework/kernel/`                 | `lifecycle_violation_exception`, `authentication_exception`, `authorization_exception`       |
| `classes/extensions/`               | `domain_exception`, `validation_exception`, `authorization_exception` — never infra directly |

`middag_authentication_exception` is reserved exclusively for the HTTP layer (the API controller boundary) — a rule only the REF stated explicitly, not the ADR body: domain/service code must never throw it directly.

## Wrapping a native exception at a boundary crossing

```php
try {
    $record = $DB->get_record_or_die('middag_items', ['id' => $id]);
} catch (\dml_exception $e) {
    throw new persistence_exception("Failed to load item {$id}", previous: $e);
}
```

Always preserve `previous` — losing the original exception when wrapping destroys the debugging trail.

## Security rule (REF, implicit rather than stated outright in the ADR)

Any 500-class exception (infrastructure or persistence) must never expose technical detail to the end user — the response carries a generic message ("Internal error"); the actual detail goes to the log only.

## Anti-patterns (legacy REF)

- `throw new \Exception('...')` — a bare native exception bypasses the whole hierarchy; the kernel has no status to map it to.
- An extension throwing `infrastructure_exception` directly instead of delegating to the coordinating service.
- Catching bare `\Exception` instead of `middag_exception` — this silently swallows genuinely unexpected PHP exceptions that should have been allowed to propagate.
- Exposing a stack trace in an API response.
- Wrapping a native exception without preserving `previous`.
- A `validation_exception` thrown with no `errors` array attached — the form resolver has nothing to map back onto the offending field.
