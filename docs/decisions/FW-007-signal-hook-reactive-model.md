---
id: FW-007
title: 'Signal/Hook Reactive Model and the OSS/Core Split'
status: accepted
date: 2026-03-29
lang: en
domains: [framework, events]
deciders: ['ratified 2026-07-24, including the public-surface specification below']
related: [FW-008, FW-012]
supersedes: []
superseded_by: null
enforced_by:
  mdgstan: []
  docs: [framework/reference/signal-hook-legacy-mechanics]
decision: 'The framework ships only the generic reactive seam — a contract-only `SignalDispatcherInterface` and the full WordPress-style `HookManager` (per-instance, action/filter) — while governed dispatch, signal hierarchy, auto-discovery, the reentrance guard, and the async outbox remain `middag-io/core` concerns.'
---

# FW-007: Signal/Hook Reactive Model and the OSS/Core Split

> [!NOTE]
> **Provenance.** Reconstructed from the `moodle-local_middag` legacy vault (`ADR-701`, decided 2026-03-29, general model; `ADR-702`, decided 2026-03-29, failure isolation; `ADR-703`, decided 2026-04-04, WordPress-style hooks; `ADR-707`, decided 2026-04-20, async signal outbox). Kept as one ADR, matching the single `##` section these four sub-decisions share in the source synthesis — they are facets of one evolving reactive-model decision, not four independent ones.

## Context

Three reactive mechanisms coexisted with distinct roles: a typed, framework-internal publish path for any PHP object (no signal base class required); public, string-based WordPress-style action hooks for casual third-party extension; and public, string-based filters for synchronous value transformation. Layered on top: a rule for whether a reactive callback's failure should interrupt the caller (only when the step composes the contract promised to the caller) or stay lateral, and — later — a persistent outbox so asynchronous consumers could react to a signal without the publisher knowing who they are.

## Considered Options

1. Ship the full governed engine — hierarchical dispatch (`aggregate_signal_interface`), `#[on]` auto-discovery, the reentrance guard, and the async outbox pipeline — directly in this OSS framework.
2. Ship only the generic publish-side seam (`SignalDispatcherInterface`) and defer the governed engine to `middag-io/core`, as the kind of governed domain infrastructure the ecosystem's OSS/proprietary boundary reserves for core.
3. Keep `HookManager` registry state static/global, matching the legacy design.
4. Give `HookManager` per-instance state, closing the multi-plugin-collision failure mode the legacy static registry carried.
5. Wrap hook and filter callback execution in an automatic try/catch inside `HookManager`, suppressing or centrally logging failures.
6. Let hook and filter callback exceptions propagate uncaught like any other PHP exception, leaving isolation to the registering developer's own convention.
7. Ship a default `HostEventBridgeInterface` implementation that translates native host events into domain signals inside the framework.
8. Ship `HostEventBridgeInterface` as an experimental contract only, with no default implementation, since in practice the core signal layer performs that translation.

## Decision

This framework ships the **generic seam**, not the governed engine (option 2 over 1): a `SignalDispatcherInterface` contract in `Bus/Contract/` is the stable publish-side interface a domain's typed pub/sub is expected to work against, with no OSS implementation of governed dispatch, hierarchy, or an outbox. What the framework does ship in full is the WordPress-style side: a per-instance `HookManager` (`Kernel/Manager/HookManager.php`, `Kernel/Contract/HookManagerInterface.php`) for `add_action`/`do_action` and `add_filter`/`apply_filters`, with priority ordering and opt-in profiling — deliberately per-instance state, not static globals (option 4 over 3). A `HostEventBridgeInterface` exists as an experimental, generic sync bridge contract with no default implementation (option 8 over 7). The historical failure-isolation rule — a reactive step's exception accompanies the main flow only when that step composes the contract promised to the caller, otherwise it is lateral, but "lateral" never means silently suppressed — survives as an authorial convention for whoever registers a hook callback: `HookManager` performs no automatic try/catch around callbacks (option 6 over 5).

## Public surface (specification, ratified 2026-07-24)

FW-007 decided the *model*; this section fixes **which of the `HookManager`
seam is public API** and which is internal wiring. It closes the "specify the
public hook surface" work item.

### Public `@api` — third-party extension
The extension contract is **`HookManagerInterface`**, consumed through
`Kernel\Facade\HookFacade` (static) or the injected interface, plus the
registration entry points `HookRegisterInterface`/`AbstractHookRegister` and
`HookfileLoader`:

- register/detach: `addAction`, `removeAction`, `addFilter`, `removeFilter`
- emit: `doAction`, `applyFilters`
- introspect: `hasAction`, `hasFilter`, `currentFilter`, `doingAction`, `didAction`

### Internal — framework/host wiring only (NOT `@api`)
Present on the concrete `HookManager`, deliberately absent from the interface:
`__construct`, `setProfileCollector`, `setSlowThreshold`, `setLogger`.

`reset()` is **lifecycle/test-only**: it clears the whole per-instance
registry and has no product caller (a kernel re-init builds a fresh instance
rather than resetting). It is not part of the extension surface — treat it as
a test-harness/lifecycle affordance, not `@api` for extenders.

### Tag naming & ownership
Hook/filter tags follow a namespaced convention `{component}.{area}.{event}`;
bare global names are reserved for framework-owned tags. The convention is
enforced by a guard test reading an **overridable naming policy** (default in
the framework; an adapter or `middag-io/core` may register its own
prefixes/patterns — mirrors the `SettingsNamingPolicy` pattern), so hosts add
their vocabulary without forking the guard.

## Consequences

- The framework stays honest to its own OSS/core boundary rule: no governed pub/sub, no persistent outbox, no reentrance guard, no signal-vocabulary enum ships here — a consumer that genuinely needs those depends on `middag-io/core`, not on a workaround grafted onto this framework. See `architecture.md` §1, Pillar 3, for the full boundary this decision instantiates.
- Per-instance `HookManager` state removes a class of bugs the legacy static-registry design was exposed to when two products share a host process.
- Exceptions from hook callbacks are never silently swallowed by the framework — "lateral" describes ownership of the failure (the callback author's), not suppression.
- **A real narrowing versus the legacy design, not a gap.** The signal enum vocabulary (~50 cases), hierarchical `aggregate_signal_interface` dispatch, the `#[on]` auto-discovery attribute, the reentrance guard, and the full async outbox pipeline (persistence tables, retry, dead-letter, purge) described by the legacy ADRs are not present in this OSS repo — they are core-owned governed infrastructure now. Preserved as historical/archaeological detail in [the extracted reference doc](https://docs.middag.dev/framework/reference/signal-hook-legacy-mechanics) for whoever implements or maintains that core layer, since the mechanism's design rationale does not appear anywhere else.
- Explicit, intentional units of work (as opposed to reactive observation of something that already happened) are a deliberately distinct mechanism — see [FW-008](./FW-008-command-bus-async-job-model.md) for the command/job model.
- The governed outbox/async delivery pipeline itself is a `middag-io/core` concern; this ADR only documents the seam (`SignalDispatcherInterface`) the framework exposes toward it.

## Enforcement

| Decision clause | Verification | State |
|---|---|---|
| Governed dispatch, signal hierarchy, `#[on]` auto-discovery, reentrance guard, and the async outbox pipeline are `middag-io/core` concerns, not shipped here | No implementing class of `SignalDispatcherInterface` or `HostEventBridgeInterface` exists in this repo (`src/Bus/Contract/SignalDispatcherInterface.php`, `src/Kernel/Contract/HostEventBridgeInterface.php` are contracts only); full legacy mechanics preserved in the extracted reference doc | coded (docs) |
| `HookManager` (`Kernel/Manager/HookManager.php`) holds per-instance registry state, not static globals | `tests/Kernel/Manager/HookManagerTest.php::testInstancesDoNotShareState` | coded |
| Hook and filter callback exceptions are never automatically swallowed — no implicit try/catch in `HookManager` | `tests/Kernel/Manager/HookManagerTest.php::testDoActionDoesNotSwallowCallbackExceptions` + `::testApplyFiltersDoesNotSwallowCallbackExceptions` | coded |
