---
title: 'Compiled container as the only resolver, frozen before modules boot'
status: accepted
date: 2026-03-29
last-updated: 2026-11-04
lang: en
domains: [framework, kernel]
deciders: [leadership]
decision: 'A compiled container is the only resolver, frozen before modules boot; module failure handling is a pluggable policy.'
related: [FW-011, FW-013, FW-016]
supersedes: []
superseded_by: null
enforced_by:
  mdgstan: []
  docs: []
---

# FW-012: Compiled container as the only resolver, frozen before modules boot

## Context

A set of modules has to become a running process in one reliable sequence. Dependencies are resolved by convention, not wired by hand. No module may change bindings after other code depends on them. A broken module must not take the whole host down unless the consumer wants it to.

## Considered Options

1. A container that stays mutable at runtime, where modules may add bindings while they boot.
2. A reflection scan of each module's whole source tree.
3. Lazy proxies accepted as a way around circular dependencies.
4. A fixed failure taxonomy built into the framework, such as a distribution enum.
5. A compiled container as the only resolver, with discovery by convention and cycles rejected at compile time. The container is frozen before any module boots, the module contract is minimal, and failure handling is a pluggable policy. ← chosen

## Decision

The container is compiled once and is the only way dependencies are resolved. Services are discovered by naming convention. Circular dependencies fail compilation and are never worked around with proxies. The container is frozen before any module's boot hook runs, so a module can resolve services while it boots but cannot add bindings. The module contract is minimal: governed module vocabulary, such as dependency cascades and distribution classes, lives downstream. What happens when a module fails to boot is decided by a policy the consumer supplies.

## Consequences

- (+) Boot does not depend on the order modules load in.
- (+) A circular dependency is a compile error, not a runtime surprise.
- (+) Each consumer chooses whether a failed module aborts the boot or is isolated.
- (−) Breaking a cycle can force a refactor, such as setter injection, a factory or splitting a responsibility.
- (−) Anything a module needs bound has to be declared before the container is compiled.
- (−) Facades read the compiled container statically, which is a bounded exception covered by FW-016.

## Enforcement

| Decision clause | Verification | State |
|---|---|---|
| No binding after compile | test `tests/Kernel/ContainerFactoryLifecycleTest.php` (`addSyntheticAfterBuildThrowsLifecycleViolation`) | coded |
| Module boot failures routed to the policy | tests `tests/Kernel/ContainerFactoryLifecycleTest.php` (`bootModulesBootsEachAndRoutesFailuresToThePolicy`), `tests/Kernel/Bootstrap/BootRethrowFailurePolicyTest.php`, `tests/Kernel/Bootstrap/BootIsolateFailurePolicyTest.php` | coded |
| Discovery by convention | test `tests/Kernel/ServiceProviderDiscoveryTest.php` | coded |
| Cycles rejected at compile | Symfony container compilation (no MIDDAG test) | coded |
| Boot sequence, discovery suffixes, phase rules | doc `docs-middag-dev:docs/framework/reference/kernel-boot-lifecycle.md` | coded |
