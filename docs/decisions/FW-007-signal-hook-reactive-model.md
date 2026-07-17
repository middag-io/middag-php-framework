---
id: FW-007
title: 'Signal/Hook Reactive Model and the OSS/Core Split'
status: accepted
date: 2026-03-29
domains: [framework, events]
related: [FW-008, FW-012]
supersedes: []
superseded_by: null
lang: en
---

# FW-007: Signal/Hook Reactive Model and the OSS/Core Split

> [!NOTE]
> **Provenance.** Reconstructed from the `moodle-local_middag` legacy vault (`ADR-701`, decided 2026-03-29, general model; `ADR-702`, decided 2026-03-29, failure isolation; `ADR-703`, decided 2026-04-04, WordPress-style hooks; `ADR-707`, decided 2026-04-20, async signal outbox). Kept as one ADR, matching the single `##` section these four sub-decisions share in the source synthesis — they are facets of one evolving reactive-model decision, not four independent ones.

## Context

Three reactive mechanisms coexisted with distinct roles: a typed, framework-internal publish path for any PHP object (no signal base class required); public, string-based WordPress-style action hooks for casual third-party extension; and public, string-based filters for synchronous value transformation. Layered on top: a rule for whether a reactive callback's failure should interrupt the caller (only when the step composes the contract promised to the caller) or stay lateral, and — later — a persistent outbox so asynchronous consumers could react to a signal without the publisher knowing who they are.

## Decision

This framework ships the **generic seam**, not the governed engine: a `SignalDispatcherInterface` contract in `Bus/Contract/` is the stable publish-side interface a domain's typed pub/sub is expected to work against, with **no OSS implementation of governed dispatch, hierarchy, or an outbox** — that engine is exactly the kind of governed domain infrastructure this ecosystem's OSS/proprietary boundary (see `architecture.md` §1, Pillar 3) reserves for `middag-io/core`. What the framework does ship in full is the WordPress-style side: a per-instance `HookManager` (`Kernel/Manager/HookManager.php`, `Kernel/Contract/HookManagerInterface.php`) for `add_action`/`do_action` and `add_filter`/`apply_filters`, with priority ordering and opt-in profiling — deliberately **per-instance state, not static globals**, closing a multi-plugin-collision failure mode the legacy design carried. A `HostEventBridgeInterface` exists as an experimental, generic sync bridge contract for translating a domain event into a native host hook — the framework ships no default implementation of it, because in practice it is the core signal layer that performs that translation. The historical failure-isolation rule — a reactive step's exception accompanies the main flow only when that step composes the contract promised to the caller, otherwise it is lateral, but "lateral" never means silently suppressed — survives as an authorial convention for whoever registers a hook callback, since `HookManager` performs no automatic try/catch around callbacks.

## Consequences

- The framework stays honest to its own OSS/core boundary rule: no governed pub/sub, no persistent outbox, no reentrance guard, no signal-vocabulary enum ships here — a consumer that genuinely needs those depends on `middag-io/core`, not on a workaround grafted onto this framework.
- Per-instance `HookManager` state removes a class of bugs the legacy static-registry design was exposed to when two products share a host process.
- Exceptions from hook callbacks are never silently swallowed by the framework — "lateral" describes ownership of the failure (the callback author's), not suppression.
- **A real narrowing versus the legacy design, not a gap.** The signal enum vocabulary (~50 cases), hierarchical `aggregate_signal_interface` dispatch, the `#[on]` auto-discovery attribute, the reentrance guard, and the full async outbox pipeline (persistence tables, retry, dead-letter, purge) described by the legacy ADRs are **not present in this OSS repo** — they are core-owned governed infrastructure now. Preserved as historical/archaeological detail in REF-FW-007-01 for whoever implements or maintains that core layer, since the mechanism's design rationale does not appear anywhere else.

## Out of scope

- The governed outbox/async delivery pipeline itself — a `middag-io/core` concern; this ADR only documents the seam (`SignalDispatcherInterface`) the framework exposes toward it.
- Explicit, intentional units of work (as opposed to reactive observation of something that already happened) — see [FW-008](./FW-008-command-bus-async-job-model.md) for the command/job model, a deliberately distinct mechanism.
- Signal vocabulary, hierarchical dispatch, reentrance guard, hook mechanics detail, the failure-isolation matrix and the full legacy outbox pipeline — see REF-FW-007-01.

## Links

- [REF-FW-007-01 — Signal Vocabulary, Hierarchical Dispatch, Reentrance Guard, Hook Mechanics & the Legacy Async Outbox Pipeline](../ref/REF-FW-007-01-signal-hook-legacy-mechanics.md)
- [FW-008 — Command Bus & Async Job Model](./FW-008-command-bus-async-job-model.md)
- [FW-012 — Kernel Boot Lifecycle](./FW-012-kernel-boot-lifecycle.md)
- [architecture.md](../architecture.md) — current implementation and OSS/core boundary
