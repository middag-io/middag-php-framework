---
ref: REF-FW-008-01
adr: FW-008
title: 'Dispatch API Resolution, Scheduling DSL, Job Field Catalog & Anti-Patterns'
lang: en
---

# REF-FW-008-01: Dispatch API Resolution, Scheduling DSL, Job Field Catalog & Anti-Patterns

> Detail supporting [FW-008](../decisions/FW-008-command-bus-async-job-model.md). Reconstructed from the `moodle-local_middag` legacy vault (ADR-705 + ref-705).

## Legacy command/handler shape

```php
class create_item implements command_interface
{
    public function __construct(
        public readonly string $type,
        public readonly array $metadata,
        public readonly int $user_id,
    ) {}
}

class create_item_handler
{
    public function __construct(
        private item_repository_interface $repository,
        private item_validator_service_interface $validator,
    ) {}
    public function __invoke(create_item $command): item { ... }
}
```

Legacy dispatch usage sketched two calls:

```php
$bus->dispatch(new create_item(...));             // synchronous
$bus->dispatch_async(new send_notification(...)); // serializes -> host adhoc task
```

## The dispatch-API discrepancy, resolved

| Source                            | Sync call                                       | Async call                              |
|-----------------------------------|-------------------------------------------------|-----------------------------------------|
| ADR-705 (legacy, normative)       | `$bus->dispatch($command)`                      | `$bus->dispatch_async($command)`        |
| ref-705 (legacy, contradicts ADR) | `command_bus::handle($command)` (static facade) | `command_bus::dispatch_async($command)` |
| **Current `Bus/MessageBus.php`**  | **`dispatch($command)`** (one surface for both) | same call — transport routing decides   |

Confirmed directly in source: `MessageBus` extends Symfony Messenger's own `MessageBus` and adds no framework-specific `handle()` or `dispatch_async()` method — `dispatch()` is the only call, and whether it runs inline or gets routed to a transport is a middleware/routing configuration, not a different method name. This settles the legacy discrepancy: `dispatch()` (the ADR's naming) was closer to right, but the real design goes further than either legacy document by collapsing sync/async into one call entirely — see `architecture.md` §7, decision D5.

## Handler resolution — two paths today

`ConventionHandlersLocator` implements the legacy `{Command}` → `{Command}Handler` naming convention unchanged. `AttributeHandlersLocator` is net-new: a handler class carries `#[AsCommandHandler(command: SomeCommand::class)]` when the host's native naming does not fit the convention (the docblock names Moodle's snake_case `*_command_handler` style as the motivating case), or when a no-argument `__invoke()` gives the bus nothing to infer the handled message type from. Both locators are `@api`-relevant extension points, not a discrepancy to resolve — a project picks whichever fits its naming.

## Declarative scheduling — legacy REF-only, now a real shipped attribute

```php
#[schedule(minute: '0', hour: '4')]
final readonly class clean_logs_command extends command { ... }
```

This existed only in the legacy REF, not the ADR body. The current `Bus/Attribute/Schedule.php` ships it for real, generalised into a host-neutral cron-style DSL: fields follow cron notation, an `'R'` value is accepted in any field for hosts implementing random scheduling (a Moodle-style `db/tasks.php` pattern), and an adapter converts the attribute into its own native scheduling primitive (Moodle's task-definition shape, WordPress's `wp_schedule_event` payload). An `$exclusive` flag is a host-neutral "do not run concurrently" hint the adapter maps onto its own locking primitive. `Bus/Schedule/{CronFieldMatcher,ScheduleReader,ScheduleRunner}.php` implement the reading/matching/running side of this DSL.

## The legacy `job` aggregate (core-owned governance, preserved as historical detail)

Lifecycle: `pending` → `started` → `completed`, or `failed` (recorded as a `job_attempt`) → retry? → back to `pending` with `attempts` incremented → `failed` at max attempts. Fields: `uuid`, `extension`, `jobtype`, `status`, `priority`, `payload`, `attempts`, `maxattempts`, `correlationid`, `dedupkey`, `groupkey`, `transport`. `job_attempt` is an immutable satellite record per attempt. None of this lifecycle or its persistence ships in `middag-php-framework` — per `architecture.md`'s pillar map, governed async jobs with retry/audit are `middag-io/core` territory; this framework's OSS surface is the transport-agnostic `MessageBus` + `InMemoryTransport` + `CommandWorker` only.

## Serialization rule

Commands serialize via a framework-defined `command_interface::to_payload()`/`from_payload()` pair, never a generic `serialize()`/`json_encode()` — this keeps a queued payload's shape independent of PHP's own serialization format. Incompatible schema changes to an already-queued command require explicit rollout coordination; no automatic payload-versioning mechanism is documented.

## Anti-patterns (legacy REF, still applicable)

- Putting logic in the handler's constructor instead of `__invoke()`.
- A mutable command class — commands should be `final readonly`.
- Serializing objects directly inside `to_payload()` instead of primitive/array data.
- A handler reaching for a host's raw data-access global directly instead of an injected repository — violates the persistence boundary (see FW-013).
- Calling `dispatch()` on an async-routed message without testing the `from_payload(to_payload())` round trip.
- A job with no `maxattempts` set — an infinite retry loop waiting to happen.
