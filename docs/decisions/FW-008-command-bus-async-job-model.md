---
id: FW-008
title: 'Command Bus & Async Job Model'
status: accepted
date: 2026-03-29
lang: en
domains: [framework, bus]
deciders: ['PENDING — original decider not recorded during the legacy-vault reconstruction; confirm with Michael Meneses before ratifying']
related: [FW-007]
supersedes: []
superseded_by: null
enforced_by:
  mdgstan: []
  docs: [framework/reference/command-bus]
decision: 'A single MessageBus::dispatch() surface serves both sync and async command execution (routing/middleware decides transport, not a different method); handlers resolve by naming convention or an #[AsCommandHandler] attribute; payloads serialize through a dedicated to_payload()/from_payload() contract, never generic serialization.'
---

# FW-008: Command Bus & Async Job Model

> [!NOTE]
> **Provenance.** Reconstructed from the `moodle-local_middag` legacy vault (`ADR-705`, decided 2026-03-29). This is an archaeology pass, not a new decision — dates and rationale are historical.

## Context

Business-meaningful work needs a first-class unit that is neither "whatever a cron task happens to do" nor an ad hoc method call — cron/task adapters accumulating business logic directly makes that logic hard to test and impossible to trigger any other way. A command needs one consistent way to run synchronously or asynchronously, with a governed-execution wrapper available for the cases that need retry, correlation, or observability.

## Considered Options

1. **Business logic living directly inside host cron/task adapters**, with no first-class command unit — rejected: makes the logic hard to test and impossible to trigger any other way than through the scheduler.
2. **Two separate dispatch methods for sync and async**, exactly as the legacy `ADR-705` specified: `$bus->dispatch($command)` for sync, `$bus->dispatch_async($command)` for async — this was the original historical decision, but the shipped implementation converged on a single unified surface instead (see Decision).
3. **A static facade dispatch API**, `command_bus::handle($command)` / `command_bus::dispatch_async($command)`, as the legacy REF companion (`ref-705`) documented — a genuine naming mismatch against `ADR-705` itself that the legacy synthesis flagged as needing source-code confirmation. Never implemented; the real code resolves the discrepancy a third way (see Decision).
4. **Generic serialization** (`serialize()`/`json_encode()`) for command payloads — rejected in favour of a dedicated `to_payload()`/`from_payload()` contract, keeping a queued payload's shape independent of PHP's own serialization format.

## Decision

`command` is the unit of explicit, executable work; `schedule` is a declarative periodic trigger; a host's native task/cron mechanism is an adapter around this model, never the place business logic itself lives. A handler is resolved for a command by naming convention (`{Command}` → `{Command}Handler`), through dependency injection. One dispatch surface serves both sync and async execution — the caller always calls the same method, and whether a message type is routed to a transport (making it async, drained later by a worker) or handled inline is a middleware/routing concern, not a different method the caller picks. Command payloads serialize through a dedicated `to_payload()`/`from_payload()` contract, never a generic `serialize()`/`json_encode()`.

Confirmed directly in the live `Bus/MessageBus.php` (extending Symfony Messenger's `MessageBus`): `dispatch()` is the only call — there is no separate `dispatch_async()` or `handle()` method. This settles the option-2/option-3 discrepancy above: `ADR-705`'s `dispatch()` naming was the closer of the two historical guesses, but the real design goes further than either legacy document by collapsing sync/async into one call entirely (`architecture.md` §7, decision D5: "async convergence: one sync bus + async via Messenger + routing").

## Consequences

- Cron/task code stays a thin adapter; the actual unit of work is testable and triggerable outside any scheduler.
- One dispatch surface for sync and async removes an entire class of "did I call the sync or async method" mistakes — a deliberate divergence from `ADR-705`'s original two-method design (Considered Option 2).
- Changing a queued command's payload shape incompatibly still requires explicit rollout coordination — no automatic payload versioning exists.
- Handler resolution grew a second path beyond naming convention: `AttributeHandlersLocator`, resolving `#[AsCommandHandler(command: ...)]`, for a host whose native naming does not fit the `{Command}Handler` convention (e.g. a Moodle-style snake_case `*_command_handler`) — a real, `@api` extension beyond the legacy design, not a discrepancy.
- Governed job observability (retry, correlation, dedup) is core, not framework. `architecture.md` places "async jobs with retry/audit" in Pillar 3 (proprietary `middag-io/core`); this framework's OSS surface stops at `Bus/MessageBus`, `Bus/Transport/InMemoryTransport` (the OSS default transport) and `Bus/Command/CommandWorker` — the rich `job` aggregate (uuid, attempts, correlation id, dedup key, group key) described by the legacy ADR is governed infrastructure that lives downstream.
- The reactive signal/hook/outbox mechanism is a deliberately distinct concept — observation of something that already happened, versus explicit intent to do work — see [FW-007](./FW-007-signal-hook-reactive-model.md); it is not this ADR's concern.
- Declarative scheduling syntax, the full legacy `job` field catalog, and dispatch-API/handler-resolution anti-patterns are documented in full in the extracted reference doc (see Enforcement).

## Enforcement

| Decision clause | Verification | State |
|---|---|---|
| Single `dispatch()` surface for both sync and async — no separate `dispatch_async()`/`handle()` method | none automated | planned |
| Handler resolved by `{Command}Handler` naming convention or `#[AsCommandHandler]` attribute | none automated | planned |
| Commands serialize via `to_payload()`/`from_payload()`, never generic `serialize()`/`json_encode()` | none automated | planned |
| Scheduling DSL, job field catalog, dispatch-API history, handler-resolution and anti-pattern detail | `framework/reference/command-bus` | coded |
