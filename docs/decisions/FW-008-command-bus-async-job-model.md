---
id: FW-008
title: 'Command Bus & Async Job Model'
status: accepted
date: 2026-03-29
domains: [framework, bus]
related: [FW-007]
supersedes: []
superseded_by: null
lang: en
---

# FW-008: Command Bus & Async Job Model

> [!NOTE]
> **Provenance.** Reconstructed from the `moodle-local_middag` legacy vault (`ADR-705`, decided 2026-03-29). This is an archaeology pass, not a new decision — dates and rationale are historical.

## Context

Business-meaningful work needs a first-class unit that is neither "whatever a cron task happens to do" nor an ad hoc method call — cron/task adapters accumulating business logic directly makes that logic hard to test and impossible to trigger any other way. A command needs one consistent way to run synchronously or asynchronously, with a governed-execution wrapper available for the cases that need retry, correlation, or observability.

## Decision

`command` is the unit of explicit, executable work; `schedule` is a declarative periodic trigger; a host's native task/cron mechanism is an adapter around this model, never the place business logic itself lives. A handler is resolved for a command by naming convention (`{Command}` → `{Command}Handler`), through dependency injection. One dispatch surface serves both sync and async execution — the caller always calls the same method, and whether a message type is routed to a transport (making it async, drained later by a worker) or handled inline is a middleware/routing concern, not a different method the caller picks. Command payloads serialize through a dedicated `to_payload()`/`from_payload()` contract, never a generic `serialize()`/`json_encode()`.

## Consequences

- Cron/task code stays a thin adapter; the actual unit of work is testable and triggerable outside any scheduler.
- One dispatch surface for sync and async removes an entire class of "did I call the sync or async method" mistakes.
- Changing a queued command's payload shape incompatibly still requires explicit rollout coordination — no automatic payload versioning exists.
- **A legacy ADR-vs-REF discrepancy, now resolved by the real code — in favour of a third answer.** ADR-705 described `$bus->dispatch($command)` / `$bus->dispatch_async($command)`; its REF companion instead documented a static facade `command_bus::handle($command)` / `command_bus::dispatch_async($command)` — a genuine naming mismatch the legacy synthesis flagged as needing source-code confirmation before publishing. The live `Bus/MessageBus.php` (extending Symfony Messenger's `MessageBus`) resolves it a third way: **`dispatch()` is the single surface for both sync and async** — there is no separate `dispatch_async()` or `handle()` method at all; whether a message runs inline or is routed to a transport is a routing/middleware decision, not a different call the caller makes (`architecture.md` §7, decision D5: "async convergence: one sync bus + async via Messenger + routing"). Treat ADR-705's `dispatch()` naming as the closer of the two historical guesses, and the unified single-surface design as the actual current answer.
- **Handler resolution grew a second path.** Convention-based resolution (`ConventionHandlersLocator`) still exists exactly as ADR-705 described, but a host whose native naming does not fit the `{Command}Handler` convention (e.g. a Moodle-style snake_case `*_command_handler`) can instead annotate the handler with `#[AsCommandHandler(command: ...)]`, resolved by `AttributeHandlersLocator` — a real, `@api` extension beyond the legacy design, not a discrepancy.
- **Governed job observability (retry, correlation, dedup) is core, not framework.** `architecture.md` places "async jobs with retry/audit" in Pillar 3 (proprietary `middag-io/core`); this framework's OSS surface stops at `Bus/MessageBus`, `Bus/Transport/InMemoryTransport` (the OSS default transport) and `Bus/Command/CommandWorker` — the rich `job` aggregate (uuid, attempts, correlation id, dedup key, group key) described by the legacy ADR is governed infrastructure that lives downstream.

## Out of scope

- The reactive signal/hook/outbox mechanism — a deliberately distinct concept (observation of something that already happened, versus explicit intent to do work) — see [FW-007](./FW-007-signal-hook-reactive-model.md).
- The governed `job` aggregate's full lifecycle and persistence — a `middag-io/core` concern.
- Declarative scheduling syntax, the full legacy `job` field catalog and anti-patterns — see REF-FW-008-01.

## Links

- [REF-FW-008-01 — Dispatch API Resolution, Scheduling DSL, Job Field Catalog & Anti-Patterns](../ref/REF-FW-008-01-command-bus-detail.md)
- [FW-007 — Signal/Hook Reactive Model and the OSS/Core Split](./FW-007-signal-hook-reactive-model.md)
- [architecture.md](../architecture.md) — current implementation
