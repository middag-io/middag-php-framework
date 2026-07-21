---
id: FW-012
title: 'Kernel Boot Lifecycle — Container, Module Discovery, Bootstrap Phases & Failure Isolation'
status: accepted
date: 2026-03-29
lang: en
domains: [framework, kernel]
deciders: ['PENDING — original decider not recorded during the legacy-vault reconstruction; confirm with Michael Meneses before ratifying']
related: [FW-002, FW-010, FW-011]
supersedes: []
superseded_by: null
enforced_by:
  mdgstan: []
  docs: [framework/reference/kernel-boot-lifecycle]
decision: 'The container is the sole authoritative dependency-resolution mechanism, with suffix-based convention discovery and compile-time circular-dependency rejection; boot runs as three strictly enforced phases (register → boot → compile/freeze), and module boot failures are routed through a pluggable BootFailurePolicyInterface instead of one fixed behaviour.'
---

# FW-012: Kernel Boot Lifecycle — Container, Module Discovery, Bootstrap Phases & Failure Isolation

> [!NOTE]
> **Provenance.** Reconstructed from the `moodle-local_middag` legacy vault (`ADR-601`, decided 2026-03-29, container/discovery; `ADR-602`, decided 2026-03-29, extension lifecycle; `ADR-603`, decided 2026-04-04, bootstrap/entry points; `ADR-609`, decided 2026-04-18, boot failure isolation). Merged into one ADR: the source synthesis itself relocated and grouped these four under one boot-pipeline rationale, explicitly noting they "should always be read together" — splitting them back into four documents here would only undo that grouping.

## Context

A plugin-shaped ecosystem needs one authoritative sequence for turning a set of registered extensions into a live, request-serving process: dependencies resolved by convention rather than hand-wiring, a strict phase boundary so an extension cannot mutate bindings after other code has started depending on them, one bootstrap entry point instead of scattered ad hoc initialization, and a policy for what happens when one extension's boot code is broken without taking the whole host down.

## Considered Options

1. Blind reflection scan of an extension's entire source tree for injectable classes, instead of a suffix convention mapped to a loader — rejected as too broad and fragile compared to an explicit convention with an "ignored domains" safety net for directories that only ever hold non-injectable types.
2. Allowing a lazy-proxy as an accepted escape hatch for a circular dependency, instead of rejecting the cycle at compile time — rejected: a cycle is treated as a design defect to refactor (setter injection, an intermediate factory, a responsibility split), not a pattern the container should keep making convenient.
3. One ad hoc initialization order per extension, instead of one canonical register → boot → compile sequence — rejected: this is precisely the class of "it worked until another extension loaded in a different order" bug the phase boundary exists to make structurally impossible.
4. One uniform boot-failure behaviour for every extension (always propagate, or always isolate), instead of a pluggable, tier-aware policy — rejected: a single fixed behaviour either takes the whole host down for a third-party bug that should have been contained, or silently hides a defect in code that should fail loudly.

## Decision

The container is the authoritative dependency-resolution mechanism; discovery is convention-based (a class-name suffix maps to a loader) rather than a blind scan of an extension's whole source tree, with an explicit "ignored domains" safety net for directories that only ever hold non-injectable types. Circular dependencies are forbidden and detected at compile time — no lazy-proxy escape hatch is treated as an acceptable fix; a cycle means the design needs refactoring. Boot is three strictly enforced phases: `register()` may only declare bindings (never resolve, never touch another extension, never register a subscriber); `boot()` may resolve from the container, register hooks/controllers/subscribers, reach into another extension's already-registered service, and publish signals, but may never declare a new binding; `compile()` freezes the container and caches routes, and belongs to the kernel alone. A phase violation throws a dedicated lifecycle exception in every environment, not only in development. Bootstrap itself is idempotent — calling it twice after the first successful initialization is a safe no-op — and boot-failure handling is policy-driven: some classes of extension must fail loudly and fatally, others must be isolated (disabled, logged, the rest of the process keeps running) so that one broken third-party extension cannot take an entire host down.

## Consequences

- One canonical sequence (discover → register → materialize services → boot → collect routes → compile/freeze) replaces ad hoc per-extension initialization order.
- Compile-time cycle detection catches a class of design error before it ever reaches runtime, at the cost of occasionally forcing a refactor (setter injection, an intermediate factory, a responsibility split) instead of a quick lazy-proxy patch.
- A rigid register/boot/compile phase boundary makes an entire category of "it worked until another extension loaded in a different order" bug structurally impossible.
- **Already the real implementation, confirmed step-for-step.** `Kernel/ContainerFactory::build()` runs exactly this sequence — register synthetic definitions, let the bootstrap register host services, run `ServiceProvider::register()` (suffix-based auto-discovery), `compile()` the container (freezing it), inject synthetics, wire facades — with `bootModules()` as a distinct subsequent step, precisely matching `bootstrap.md` §1.
- **D2 — the single largest structural change since these legacy ADRs were written.** The legacy "Extension" concept (register/boot/compile enforcement plus `REQUIRES`/`DISTRIBUTION`/`GROUP`/`PRIORITY` vocabulary) has split: this OSS framework knows only the generic `ModuleInterface` (`Kernel/Contract/ModuleInterface.php`, `Kernel/Module/AbstractModule.php`) — a bare `boot()` lifecycle hook with none of the MIDDAG-specific vocabulary. The richer, governed "Extension" concept (dependency cascades, distribution-aware policy, typed capability/hook/settings definitions) is `middag-io/core`'s concern now, per `architecture.md` decision D2. This is also why the MIDDAG "Extension" concept's full governed lifecycle and the host-integrated, instance-based discovery engine an adapter subclasses are explicitly out of scope for this ADR: both belong to `middag-io/core` and to the adapter layer respectively, not to this framework (`architecture.md` §9.3).
- **Boot-failure isolation generalized from a fixed distribution enum into a pluggable policy.** `BootFailurePolicyInterface` replaces the legacy hard-coded `NATIVE`/`PRO`/`THIRD_PARTY`/`CUSTOM` rule with two OSS implementations — `BootRethrowFailurePolicy` (fail loud) and `BootIsolateFailurePolicy` (isolate, log, keep running) — so a consumer supplies whichever policy fits its own governance model, rather than the framework baking in one fixed four-value distribution taxonomy.
- Discovery-by-suffix survives as the OSS default (`ServiceProvider`, PascalCase suffixes, wired by `ContainerFactory::build()`); a richer, host-integrated discovery engine (snake_case conventions, plugin hooks, self-pruning cache) is explicitly an adapter/core concern behind the same `ServiceLoaderInterface` contract (`architecture.md` §9.3).

## Enforcement

| Decision clause | Verification | State |
|---|---|---|
| Container freezes after `compile()`; no new binding may be registered post-compile | `MiddagLifecycleViolationException`, thrown by `ContainerFactory::addSynthetic()` when called after `build()`; covered by `ContainerFactoryLifecycleTest::addSyntheticAfterBuildThrowsLifecycleViolation` | coded |
| Circular dependencies are rejected at compile time, with no lazy-proxy escape hatch | Enforced automatically by Symfony's `ContainerBuilder::compile()` (`CheckCircularReferencesPass`); no MIDDAG-specific test exists on top of it | coded |
| A module `boot()` failure is routed to the configured `BootFailurePolicyInterface` instead of crashing the whole boot loop | `ContainerFactoryLifecycleTest::bootModulesBootsEachAndRoutesFailuresToThePolicy`, `BootRethrowFailurePolicyTest::testHandleRethrowsTheSameThrowable`, `BootIsolateFailurePolicyTest::testLogsCriticalAndDoesNotRethrow` | coded |
| An isolated boot failure remains queryable after the fact (descendant of the legacy `kernel::failed_extensions()` registry) | `FailedModuleRegistryInterface` (`register()`/`has()`/`all()`); framework ships the contract, a consumer supplies the concrete request-scoped registry | coded |
| Full discovery-suffix catalog, phase-enforcement matrix, legacy entry points, and boot-failure-by-distribution detail | Extracted to `docs-middag-dev` → `framework/reference/kernel-boot-lifecycle` | coded |
| A module's `register()` must not resolve from the container, and `boot()` must not declare a new binding | No automated guard exists yet for `ModuleInterface` implementations — only the container-freeze boundary above is code-enforced; this phase split is currently discipline, not a lint rule | planned |
