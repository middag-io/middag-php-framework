---
title: 'Declarative routing and authorization'
status: accepted
date: 2026-04-04
last-updated: 2026-11-04
lang: en
domains: [framework, http]
deciders: [leadership]
decision: 'Route, auth and middleware are attributes the kernel applies before the action; capability checks go to the adapter.'
related: [FW-001, FW-010, FW-015]
supersedes: []
superseded_by: null
enforced_by:
  mdgstan: []
  docs: []
---

# FW-002: Declarative routing and authorization

## Context

Several entry points share one kernel and one route collection. A route's auth requirement has to be visible where the route is declared and enforced before any action code runs. Users and capabilities belong to the host, so the framework cannot own the capability model.

## Considered Options

1. Routes in docblock annotations parsed as strings at runtime.
2. Imperative auth checks inside each action body.
3. symfony/security, with its firewall and voters, as the auth model.
4. PHP attributes for the route, the auth requirement and the route middleware. The kernel applies them at a fixed stage before the action, delegates capability checks to the host adapter, and fails closed when a declared middleware cannot be resolved. ← chosen

## Decision

Routing, auth requirements and route middleware are declared as PHP attributes on the controller. The kernel applies them at a fixed stage before the action runs. The framework enforces login itself. Capability checks are delegated to the host adapter through a kernel hook (`applyPlatformAuth`). The framework does not use symfony/security. A declared middleware that cannot be resolved aborts the request instead of being skipped. Precedence, merge rules and the resolver chain live in the reference.

## Consequences

- (+) A route's auth requirement can be read at its declaration, and one missed call in an action body cannot open the endpoint.
- (+) Every controller shape goes through the same gate.
- (+) Each host keeps its own capability model.
- (−) Without an adapter, the OSS kernel enforces only login. Capability enforcement exists only where an adapter or consumer implements the hook.
- (−) The auth attribute overrides from method to class, while the middleware attribute stacks. Authors have to know both merge rules.
- (−) The OSS kernel does not discover routes on its own. The host assembles the route collection.

## Enforcement

| Decision clause | Verification | State |
|---|---|---|
| The auth gate runs before the action | test `tests/Http/AuthGateTest.php` | coded |
| An unresolvable middleware aborts the request | test `tests/Http/Middleware/RouteMiddlewareTest.php` (`anUnresolvableMiddlewareIdAbortsTheRequestNamingTheRouteAndTheId`) | coded |
| Capability checks are delegated to the adapter hook | doc `docs-middag-dev:docs/framework/reference/attribute-routing-and-auth-model.md` | coded |
| Attribute model, precedence, resolver chain and stage order | doc `docs-middag-dev:docs/framework/reference/attribute-routing-and-auth-model.md` | coded |
| No symfony/security dependency | review of `composer.json` | planned |
