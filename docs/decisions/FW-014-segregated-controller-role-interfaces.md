---
title: 'Segregated controller role interfaces'
status: accepted
date: 2026-07-24
last-updated: 2026-11-04
lang: en
domains: [framework, http]
deciders: [leadership]
decision: 'ControllerInterface is the union of host-neutral role interfaces; each host composes only the roles it can serve.'
related: [FW-002, FW-015, middag-php-wordpress:WP-001]
supersedes: []
superseded_by: null
enforced_by:
  mdgstan: []
  docs: []
---

# FW-014: Segregated controller role interfaces

## Context

The controller contract bundled three responsibilities: container wiring, the single request-to-response cycle and the auth gate. That fits the standalone kernel and Moodle. It does not fit hosts whose dispatch is a registration model, such as WordPress REST, where one controller registers several endpoints and reports failures in its own protocol. The framework must not model any one host's dispatch.

## Considered Options

1. Keep the single contract, and let hosts that do not fit define unrelated contracts.
2. Grow the contract with host-shaped options so every host fits it.
3. Split it into host-neutral role interfaces and keep the controller contract as their union. ← chosen

## Decision

The controller contract is the union of three host-neutral role interfaces: container awareness, request handling and authorization awareness. Its total surface is unchanged, so existing implementations keep working. A host whose dispatch model is not the kernel's request cycle implements only the roles it can serve. The framework gains no host-shaped surface. A role fixes method signatures, not how a failure is reported.

## Consequences

- (+) The change is not breaking: existing controllers compile unchanged.
- (+) Future dispatch models, such as queue consumers or CLI controllers, compose roles instead of faking a request cycle.
- (−) Code that receives a controller cannot assume every role and has to check for the one it needs.

## Enforcement

| Decision clause | Verification | State |
|---|---|---|
| The controller contract is the union of the three roles | test `tests/Http/Contract/ControllerInterfaceSegregationTest.php` | coded |
| Controller bases per host | doc `docs-middag-dev:docs/framework/reference/bridge-contracts.md` | coded |
