---
title: 'Static state as a bounded exception'
status: accepted
date: 2026-11-04
last-updated: 2026-11-04
lang: en
domains: [framework, kernel]
deciders: [leadership]
decision: 'State is per instance; facades, composition-root registries, request-scoped SPA props and immutable caches are the bounded exceptions.'
related: [FW-006, FW-007, FW-012, FW-013, FW-015, middag-php-moodle:MDL-001, middag-php-wordpress:WP-002]
supersedes: []
superseded_by: null
enforced_by:
  mdgstan: []
  docs: [docs/architecture.md]
---

# FW-016: Static state as a bounded exception

## Context

Per-instance state keeps several plugins in one PHP process isolated from each other and keeps tests independent. But some call sites cannot receive injected services: host callbacks, static helpers that adapters call, and the short static entry points that consumers use for convenience. The framework needs a rule that allows these without giving up isolation everywhere else.

## Considered Options

1. No static state at all.
2. Static state allowed wherever it is convenient.
3. Per-instance state as the rule, with a closed set of static categories, each with a seam to reset or override it. ← chosen

## Decision

State is per instance. Static state is allowed only in four categories:

- facades that proxy the compiled container, with their cache of resolved instances;
- composition-root registries that the consumer writes during boot: host identity, the SPA protocol's configuration, the Active Record connection and cast resolvers, and the debug tracer's runtime;
- the SPA protocol's shared props, which middleware writes on every request and which are therefore request-scoped;
- process-wide caches of immutable data, such as reflection results.

Every static slot has a seam to reset or override it. A consumer whose host runs several plugins in one process must not read the single host-identity slot. It injects identity through its own container instead.

## Consequences

- (+) Host callbacks and short static entry points work without a container in reach.
- (+) The categories are a closed set that can be reviewed.
- (−) The host-identity registry is one global slot. In a process with several plugins the last write wins, so those consumers must avoid it.
- (−) Facades are service locators, and nothing stops them from being used inside domain code.
- (−) Nothing resets static state automatically. Tests have to restore what they change, and a long-running worker has to flush the request-scoped SPA shared props between requests, or one request's flash messages, errors and CSRF token leak into the next.

## Enforcement

| Decision clause | Verification | State |
|---|---|---|
| The host-identity registry is a boot-time slot that can be read safely when unset | test `tests/Kernel/HostContextTest.php` | coded |
| The hook registry is per instance | test `tests/Kernel/Manager/HookManagerTest.php` (`testInstancesDoNotShareState`) | coded |
| The list of static slots and their reset or override seams | doc `docs/architecture.md` §9 (Bounded statics) | coded |
| No facade use inside domain code | static-analysis rule | planned |
