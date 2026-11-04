---
title: 'One framework exception family that carries the HTTP status'
status: accepted
date: 2026-04-05
last-updated: 2026-11-04
lang: en
domains: [framework, exceptions]
deciders: [leadership]
decision: 'Every failure crosses layers as a MiddagException carrying its HTTP status; foreign exceptions are wrapped at the edge.'
related: [FW-002, FW-003]
supersedes: []
superseded_by: null
enforced_by:
  mdgstan: []
  docs: []
---

# FW-010: One framework exception family that carries the HTTP status

## Context

The kernel has to turn any failure into the right HTTP response without a growing chain of type checks. Callers and log readers need to see what kind of failure happened, whatever host the code runs on.

## Considered Options

1. A type-check chain at the kernel, with no fixed hierarchy.
2. Extend a host or library exception base, such as Moodle's or Symfony's.
3. Let foreign exceptions, such as driver errors, cross layer boundaries unwrapped.
4. A single framework-owned root, which depends on nothing but PHP, where each subclass fixes its HTTP status. Foreign exceptions are wrapped at the boundary and keep the original as `previous`. ← chosen

## Decision

Every failure that crosses a layer is a subclass of `MiddagException`, which extends only PHP's native runtime exception and reports its own HTTP status. The kernel maps a failure to a response by reading that status. Anything else becomes a generic server error, and 5xx detail never reaches the user. A foreign exception is wrapped into the matching framework exception at the boundary where it is caught, keeping the original as `previous`. Which layer may throw which subclass lives in the reference.

## Consequences

- (+) Adding a new exception type never touches the kernel.
- (+) The hierarchy has no dependency on any host or vendor.
- (−) The Moodle adapter's own exceptions extend SPL classes instead of the framework root (middag-php-moodle `src/Exception/`), so the kernel maps them to a generic 500.
- (−) The per-layer throw rules and the wrapping rule are not checked by any tool.

## Enforcement

| Decision clause | Verification | State |
|---|---|---|
| Single root; each subclass fixes its status | test `tests/Exception/ExceptionHierarchyTest.php` | coded |
| Per-layer throw restrictions; wrapping at the boundary | static-analysis rule | planned |
| Status table, per-layer matrix, anti-patterns | doc `docs-middag-dev:docs/framework/reference/exception-hierarchy.md` | coded |
