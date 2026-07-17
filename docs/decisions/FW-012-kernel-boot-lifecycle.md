---
id: FW-012
title: 'Kernel Boot Lifecycle — Container, Module Discovery, Bootstrap Phases & Failure Isolation'
status: accepted
date: 2026-03-29
domains: [framework, kernel]
related: [FW-002, FW-010, FW-011]
supersedes: []
superseded_by: null
lang: en
---

# FW-012: Kernel Boot Lifecycle — Container, Module Discovery, Bootstrap Phases & Failure Isolation

> [!NOTE]
> **Provenance.** Reconstructed from the `moodle-local_middag` legacy vault (`ADR-601`, decided 2026-03-29, container/discovery; `ADR-602`, decided 2026-03-29, extension lifecycle; `ADR-603`, decided 2026-04-04, bootstrap/entry points; `ADR-609`, decided 2026-04-18, boot failure isolation). Merged into one ADR: the source synthesis itself relocated and grouped these four under one boot-pipeline rationale, explicitly noting they "should always be read together" — splitting them back into four documents here would only undo that grouping.

## Context

A plugin-shaped ecosystem needs one authoritative sequence for turning a set of registered extensions into a live, request-serving process: dependencies resolved by convention rather than hand-wiring, a strict phase boundary so an extension cannot mutate bindings after other code has started depending on them, one bootstrap entry point instead of scattered ad hoc initialization, and a policy for what happens when one extension's boot code is broken without taking the whole host down.

## Decision

The container is the authoritative dependency-resolution mechanism; discovery is convention-based (a class-name suffix maps to a loader) rather than a blind scan of an extension's whole source tree, with an explicit "ignored domains" safety net for directories that only ever hold non-injectable types. Circular dependencies are forbidden and detected at compile time — no lazy-proxy escape hatch is treated as an acceptable fix; a cycle means the design needs refactoring. Boot is three strictly enforced phases: `register()` may only declare bindings (never resolve, never touch another extension, never register a subscriber); `boot()` may resolve from the container, register hooks/controllers/subscribers, reach into another extension's already-registered service, and publish signals, but may never declare a new binding; `compile()` freezes the container and caches routes, and belongs to the kernel alone. A phase violation throws a dedicated lifecycle exception in every environment, not only in development. Bootstrap itself is idempotent — calling it twice after the first successful initialization is a safe no-op — and boot-failure handling is policy-driven: some classes of extension must fail loudly and fatally, others must be isolated (disabled, logged, the rest of the process keeps running) so that one broken third-party extension cannot take an entire host down.

## Consequences

- One canonical sequence (discover → register → materialize services → boot → collect routes → compile/freeze) replaces ad hoc per-extension initialization order.
- Compile-time cycle detection catches a class of design error before it ever reaches runtime, at the cost of occasionally forcing a refactor (setter injection, an intermediate factory, a responsibility split) instead of a quick lazy-proxy patch.
- A rigid register/boot/compile phase boundary makes an entire category of "it worked until another extension loaded in a different order" bug structurally impossible.
- **Already the real implementation, confirmed step-for-step.** `Kernel/ContainerFactory::build()` runs exactly this sequence — register synthetic definitions, let the bootstrap register host services, run `ServiceProvider::register()` (suffix-based auto-discovery), `compile()` the container (freezing it), inject synthetics, wire facades — with `bootModules()` as a distinct subsequent step, precisely matching `bootstrap.md` §1.
- **D2 — the single largest structural change since these legacy ADRs were written.** The legacy "Extension" concept (register/boot/compile enforcement plus `REQUIRES`/`DISTRIBUTION`/`GROUP`/`PRIORITY` vocabulary) has split: this OSS framework knows only the generic `ModuleInterface` (`Kernel/Contract/ModuleInterface.php`, `Kernel/Module/AbstractModule.php`) — a bare `boot()` lifecycle hook with none of the MIDDAG-specific vocabulary. The richer, governed "Extension" concept (dependency cascades, distribution-aware policy, typed capability/hook/settings definitions) is `middag-io/core`'s concern now, per `architecture.md` decision D2.
- **Boot-failure isolation generalized from a fixed distribution enum into a pluggable policy.** `BootFailurePolicyInterface` replaces the legacy hard-coded `NATIVE`/`PRO`/`THIRD_PARTY`/`CUSTOM` rule with two OSS implementations — `BootRethrowFailurePolicy` (fail loud) and `BootIsolateFailurePolicy` (isolate, log, keep running) — so a consumer supplies whichever policy fits its own governance model, rather than the framework baking in one fixed four-value distribution taxonomy.
- Discovery-by-suffix survives as the OSS default (`ServiceProvider`, PascalCase suffixes, wired by `ContainerFactory::build()`); a richer, host-integrated discovery engine (snake_case conventions, plugin hooks, self-pruning cache) is explicitly an adapter/core concern behind the same `ServiceLoaderInterface` contract (`architecture.md` §9.3).

## Out of scope

- The MIDDAG "Extension" concept's full governed lifecycle (dependency cascades, typed definitions, distribution-driven failure policy as a closed taxonomy) — now `middag-io/core`'s concern.
- The host-integrated, instance-based discovery engine an adapter subclasses — an adapter/core concern, not this framework's.
- Full discovery-suffix table, the phase-enforcement matrix, the legacy entry-point table and boot-failure-by-distribution table — see REF-FW-012-01.

## Links

- [REF-FW-012-01 — Discovery Suffix Catalog, Lifecycle Enforcement Matrix, Legacy Entry Points & Boot-Failure Policy Detail](../ref/REF-FW-012-01-boot-lifecycle-detail.md)
- [FW-002 — Attribute Routing, Controller Model & Declarative Auth](./FW-002-attribute-routing-controller-auth-model.md)
- [FW-010 — Typed Exception Hierarchy](./FW-010-typed-exception-hierarchy.md)
- [FW-011 — Framework as a Portable Composer Package](./FW-011-framework-composer-portability.md)
- [bootstrap.md](../bootstrap.md) — current implementation
