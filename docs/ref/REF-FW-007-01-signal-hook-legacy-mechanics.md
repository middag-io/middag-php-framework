---
ref: REF-FW-007-01
adr: FW-007
title: 'Signal Vocabulary, Hierarchical Dispatch, Reentrance Guard, Hook Mechanics & the Legacy Async Outbox Pipeline'
lang: en
---

# REF-FW-007-01: Signal Vocabulary, Hierarchical Dispatch, Reentrance Guard, Hook Mechanics & the Legacy Async Outbox Pipeline

> Detail supporting [FW-007](../decisions/FW-007-signal-hook-reactive-model.md). Reconstructed from the `moodle-local_middag` legacy vault (ADR-701, ref-701 — the largest REF in the vault at roughly 24 KB — ADR-702 + ref-702, ADR-703, ADR-707 + ref-707). **Read this file as archaeology for `middag-io/core`, not as a description of anything shipped in `middag-php-framework` today** — the ADR body explains exactly which pieces are OSS versus core.

## The three-mechanism hierarchy (legacy)

| Mechanism                               | Scope              | Typing                 | Use                         | Ships in this OSS repo today?                                           |
|-----------------------------------------|--------------------|------------------------|-----------------------------|-------------------------------------------------------------------------|
| `dispatch()` + signal                   | Framework-internal | Typed (any PHP object) | Primary publication         | Contract only (`SignalDispatcherInterface`) — governed dispatch is core |
| Action hooks (`add_action`/`do_action`) | Public             | String-based           | Extension point for plugins | Yes — `HookManager`                                                     |
| Filters (`add_filter`/`apply_filters`)  | Public             | String-based           | Value-transform pipeline    | Yes — `HookManager`                                                     |

Any PHP object is a signal — no mandatory base class. `dispatcher_interface` (now `SignalDispatcherInterface`) was classified in the legacy governance ADR (ADR-901) as stable public API, Group A. The engine behind it was Symfony EventDispatcher, not exposed as the primary contract. A bridge automatically fired `do_action()` whenever `dispatch($signal)` ran — one-directional, hooks never triggered signals back — with the hook name derived from the signal's FQCN (a normalized short alias for known MIDDAG entities, a normalized FQCN otherwise); `#[no_hook]` opted a signal out of the bridge. Filters sat outside this bridge entirely, an independent mechanism with a mandatory return value.

## Signal enum vocabulary (core-owned)

The legacy REF (not the ADR) introduced a controlled vocabulary: a `signal` enum with roughly 50 cases grouped by category — CRUD (`CREATE`/`CREATED`/`UPDATE`/…), validation/authorization, processing/job, audit, content lifecycle, data exchange, status transitions, workflow, queue. Extensions could use custom strings outside the enum — a recommendation, not a restriction. This vocabulary is not present anywhere in this OSS repo; if `middag-io/core` reintroduces it, this is the historical rationale to consult.

## Hierarchical dispatch via `aggregate_signal_interface` (core-owned)

```php
interface aggregate_signal_interface
{
    public function get_aggregate(): string;     // 'item', 'job', 'audit'
    public function get_type(): ?string;         // item_type slug or null
    public function get_action(): Signal|string;
}
```

A signal implementing this interface generated **two** hooks per dispatch: a general one (`middag/{aggregate}/{action}`) fired first, and a type-specific one (`middag/{aggregate}/{action}/{type}`) fired second — general before specific, so a listener could intercept before refinement. A signal without the interface generated exactly one hook (its normalized FQCN).

## Auto-discovery via `#[on]` (core-owned, not documented in ADR-701 itself)

```php
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final readonly class on
{
    public function __construct(
        public Signal|string|null $signal = null,
        public string|array|null $type = null,
        public int $priority = 10,
        public string|null $aggregate = null,
    ) {}
}
```

Filter versus action was decided by the annotated method's return type: `void` registered as an action, anything else as a filter. Discovery ran via a dedicated `signal_loader` at boot.

## Reentrance guard (core-owned, not mentioned in ADR-701 at all)

A composite key `{class}:{action}:{type}:{id}` guarded against loops: the first reentrant call was skipped with a warning, a second reentrant call on the same key threw `middag_lifecycle_violation_exception` (see FW-010 for the current exception hierarchy this maps onto). Different actions on the same ID, or the same signal across different IDs, were never blocked by this guard — it targets true reentrance, not general high-frequency dispatch.

## Host events to signals to hooks (adapter/core boundary)

A `moodle_event_bridge`-equivalent converted native host events into signals through three registration styles: a generic declarative form with no dedicated signal class, an explicit-boot form with a real signal class, and a `#[moodle_event]`-attribute auto-discovery form needing no explicit boot call. External plugins extended this via a `lib.php`-style hook (`{plugin}_extend_local_middag_moodle_signal_loader(array $dirs): array`). This entire bridge is host-adapter territory by construction — it translates a specific host's native events, so it cannot live in a host-agnostic framework; whether it now lives in an adapter repo or in core was not confirmed in this pass.

## Failure isolation matrix (ADR-702)

| Mechanism                       | Default role                     | Exception propagates?                                      |
|---------------------------------|----------------------------------|------------------------------------------------------------|
| `dispatch()`/signal dispatcher  | accompanies the main flow        | yes                                                        |
| `hook_manager::do_action()`     | lateral                          | yes (even though lateral)                                  |
| `hook_manager::apply_filters()` | mandatory                        | yes                                                        |
| a selected shortcode render     | mandatory                        | yes                                                        |
| bootstrap/discovery loaders     | its own bootstrap-exception rule | see [FW-012](../decisions/FW-012-kernel-boot-lifecycle.md) |

The decisive rule is not "hook versus service" — it is whether the step composes the contract promised to the caller (a return value or a documented mandatory side effect). If it does, its failure travels with the main flow; otherwise it is lateral by default. **Lateral never means automatically suppressed** — there is no implicit try/catch in the hook manager; an action-hook or filter callback's exception propagates exactly like any other PHP exception, and it is the registering developer's responsibility to handle it if it should not bubble up. The one carve-out with a different rule entirely is boot-time failure isolation, covered in FW-012 (originally ADR-609) — that rule is about isolating a misbehaving *extension* at boot, a different question from whether a *single reactive callback's* exception should propagate.

## Hooks WordPress-style — mechanics (ADR-703, this part is real and current)

`hook_manager` (now `HookManager`) is deliberately the **secondary** public mechanism — `dispatch()`/signals is primary per ADR-701 — aimed at casual integrators. Actions (`add_action`/`do_action`, side effects, no return) and filters (`add_filter`/`apply_filters`, synchronous transform with a return value), both carrying a priority (lower runs earlier) and an accepted-argument count. Hook naming derives automatically from the signal's FQCN, following the same convention as the dispatch bridge; host-integration hooks not derived from a signal (legacy `lib.php` callbacks) kept a host-specific prefix. Registration happens per extension at boot, in one of two forms: inline in a `register_hooks()`-style method, or auto-discovery via classes extending an abstract hook-register base (`Kernel/Module/AbstractHookRegister.php` is the current, real equivalent). Observability: the legacy `hook_manager` measured execution time per callback and logged a warning past a configurable slow-callback threshold (`middag_hook_slow_threshold_ms`, default 100 ms, `0` disables it) — purely passive, no throttling or cancellation; substantial I/O was expected to move to the async command/job model (FW-008) instead. Whether the live `HookManager`'s opt-in `setProfileCollector()` mechanism (documented in `architecture.md` §5.2) still enforces a default slow-callback threshold, or leaves that entirely to the bound `ProfileCollectorInterface` consumer, was not confirmed in this pass — check `Kernel/Manager/HookManager.php` before restating either behaviour as current. Coexistence with a host's own native hook system (e.g. Moodle Hooks 4.4+) was scoped as non-overlapping by design: host hooks integrate with the platform itself, MIDDAG hooks exist for product extensibility.

## The legacy async signal outbox (ADR-707 + ref-707 — entirely core-owned today)

An outbox layer sitting over the signal dispatcher, for asynchronous consumers reacting to an already-dispatched signal without the publisher needing to know who they are — explicitly distinct from the command/job model (FW-008): outbox is for *observers reacting to something that happened*; commands are for *explicit intent to do work*.

**Serialization contract:**

```php
interface async_signal_interface
{
    public function to_payload(): array;
    public static function from_payload(array $payload): static;
}
```

**Consumer registration:**

```php
#[async_on(signal: enrollment_completed::class, priority: 10)]
public function sync_warehouse(enrollment_completed $signal): void { ... }
```

Discovered at boot via an `async_signal_loader` scanning services tagged `middag.signal_handler`; the attribute is `IS_REPEATABLE`.

**Persistence — two tables.** `middag_event_outbox` (one row per dispatch that has async consumers: `signal_class`, `payload`, `status pending -> done`) and `middag_event_delivery` (one row per consumer per dispatch: `status pending -> queued -> done/failed -> dead`, `attempts`, `last_error`).

**Pipeline:** `outbox_write()` → a scheduled `outbox_worker_task` (every minute, `blocking:1`) → `queue_worker` → an ad hoc job per delivery → an async delivery task → `from_payload()` → resolve the consumer from the container → execute → mark the delivery done and attempt to close the outbox entry.

**Retry/dead-letter:** below `max_attempts` (default 3), a failed delivery re-queues on the next cycle; at or above `max_attempts` it becomes `dead` and requires **manual intervention** — no automatic replay in this design; replay was a documented manual `UPDATE` against the database.

**Idempotency guard:** the async delivery task checks for `status = done` before executing, to avoid double-processing when the outbox worker re-queues a `failed` delivery while a host's own native retry for the original ad hoc job is still pending.

**Purge:** a daily task (3 AM) removes `done` rows older than a configurable retention window (default 30 days) and `dead` rows older than a separate window (default 90 days).

**Wiring detail (container-specific, not restated in the REF, only the ADR):** the async consumer registry and the outbox store were registered as explicit **public** services in the container's basic-configuration step — necessary because resolving a service from a compiled Symfony DI container works only for public services, and neither of these two classes carried a suffix the service loader would auto-register.

## Checklist for implementing an async signal consumer (core-owned, preserved for whoever builds it)

A signal must implement `async_signal_interface`; `to_payload()`/`from_payload()` must be exact inverses, including nullable fields; a consumer must be idempotent and must not depend on the original HTTP request's state (no session, no request-scoped user context); a consumer must catch its own expected exceptions and let only genuinely unexpected ones propagate; a signal must not carry non-serializable objects in its constructor.
