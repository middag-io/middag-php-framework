---
ref: REF-FW-012-01
adr: FW-012
title: 'Discovery Suffix Catalog, Lifecycle Enforcement Matrix, Legacy Entry Points & Boot-Failure Policy Detail'
lang: en
---

# REF-FW-012-01: Discovery Suffix Catalog, Lifecycle Enforcement Matrix, Legacy Entry Points & Boot-Failure Policy Detail

> Detail supporting [FW-012](../decisions/FW-012-kernel-boot-lifecycle.md). Reconstructed from the `moodle-local_middag` legacy vault (ADR-601 + REF-601-01, ADR-602 + REF-602-01, ADR-603 + REF-603-01, ADR-609 + REF-609-01).

## Discovery-by-suffix catalog (legacy)

| Suffix (legacy)                                                                                                                          | Loader                 | Artifact                      |
|------------------------------------------------------------------------------------------------------------------------------------------|------------------------|-------------------------------|
| `_service`, `_repository`, `_manager`, `_handler`, `_factory`, `_provider`, `_loader`, `_builder`, `_connector`, `_adapter`, `_settings` | `service_loader`       | Registered in the container   |
| `_extension`                                                                                                                             | `extension_loader`     | Framework extensions          |
| `_controller` (with `#[Route]`)                                                                                                          | `route_loader`         | HTTP controllers              |
| `_facade`                                                                                                                                | `facade_loader`        | Facades                       |
| `_signal` (with `#[moodle_event]`)                                                                                                       | `moodle_signal_loader` | Host event bridge             |
| `#[item_type]` or `TYPE`                                                                                                                 | `type_loader`          | Item types                    |
| `#[on]`                                                                                                                                  | `signal_loader`        | Signal handlers via attribute |

**Domains the `service_loader` ignored** (containing exclusively non-injectable types): `cli/`, `contract/`, `db/`, `deprecated/`, `dto/`, `entity/`, `enum/`, `exception/`, `fixture/`, `interface/`, `legacy/`, `templates/`, `tests/`, `trait/`, `value_object/`. Notably **not** ignored, despite looking like plain data folders at a glance: `base/`, `model/`, `domain/`, `view/`, `widget/`, `facade/` — the suffix on the class name decides registration, not which folder the class happens to live in.

**External plugin auto-discovery (legacy, host-specific):** a container factory derived extension directories for non-native distributions automatically from a `lib.php`-style hook, resolving each extension's path via reflection and handing it to the other loaders — external plugins needed to implement only that one hook; the rest (`extend_..._service_loader`, `extend_..._register_item_types`, `extend_..._moodle_signal_loader`) were optional. This entire auto-discovery mechanism is host-adapter territory by construction (it is reading a specific host's plugin registry), so it does not appear in this OSS framework's own discovery engine.

## Lifecycle phase enforcement matrix

| Operation                                                                                                                       | `register()` | `boot()` | `compile()`       |
|---------------------------------------------------------------------------------------------------------------------------------|--------------|----------|-------------------|
| Declare bindings/services/repositories/typed definitions/item types                                                             | YES          | no       | no                |
| Register subscribers/hooks/filters/controllers; resolve from the container; access another extension's service; publish signals | no           | YES      | no                |
| Modify bindings                                                                                                                 | no           | no       | no                |
| Freeze the container / cache routes                                                                                             | no           | no       | YES (kernel only) |

A phase violation throws `MiddagLifecycleViolationException` (FW-010) in **any** environment — this is not a development-only safety net. A missing or incompatible declared dependency (the legacy `REQUIRES` mechanism) disabled the dependent extension with a warning, and that disablement cascaded to anything depending on the now-disabled extension in turn — a mechanism belonging to the richer, core-owned Extension concept (see FW-012's Consequences on D2), not to the plain `ModuleInterface` this framework ships.

## Legacy entry points

| Entry point (legacy) | Role                       | Context                                             |
|----------------------|----------------------------|-----------------------------------------------------|
| `index.php`          | Page routing and REST API  | UI navigation and endpoints                         |
| `webhook.php`        | Inbound external requests  | Callbacks from external services (payment gateways) |
| `ajax.php`           | Internal frontend requests | AJAX calls from the product's own JS                |

All three delegated to `kernel::handle()`. A known limitation recorded at the time: an external plugin registering controllers only got routes nested under the host plugin's own entry point path, losing its own URL identity — a "Developer Kit" with delegated entry points was the planned (never shipped, per this pass) fix. `bootstrap.md` confirms the current design still frames "where the adapter triggers boot in the host" (a Moodle callback/`setup` step, a WordPress `plugins_loaded`/`init` hook) as the adapter's own responsibility, not something this framework prescribes.

## Boot-failure policy — legacy distribution table

| Distribution (legacy) | Failure in `boot()`    | Behaviour                                 |
|-----------------------|------------------------|-------------------------------------------|
| `NATIVE`              | Propagates immediately | Fatal — the team fixes it before deploy   |
| `PRO`                 | Propagates immediately | Same responsibility as `NATIVE`           |
| `THIRD_PARTY`         | Isolated and logged    | Extension disabled, product keeps running |
| `CUSTOM`              | Isolated and logged    | Same treatment as `THIRD_PARTY`           |

A failure in `register()` was **always** fatal regardless of distribution — `register()` is the structural phase; there is no "isolate and continue" option for it. This distribution-driven policy is exactly what `BootFailurePolicyInterface` generalizes in the current framework (FW-012's Consequences): `BootRethrowFailurePolicy` behaves like the `NATIVE`/`PRO` row, `BootIsolateFailurePolicy` behaves like the `THIRD_PARTY`/`CUSTOM` row, but neither is hard-wired to a fixed distribution enum — a consumer chooses (or writes) whichever policy fits.

## Why this rule exists (ADR-609's own stated motivation)

The legacy loaders originally caught `Throwable` silently via a debug-trace helper — a real, self-identified contradiction with the failure-isolation rule elsewhere in the reactive model (FW-007: "lateral never means automatically suppressed"), and one that hid third-party failures in production entirely, since that debug trace only worked with debugging enabled. The distribution-aware policy (and its current `BootFailurePolicyInterface` generalization) exists specifically to fix that silent-swallowing bug, not merely to add a nice-to-have feature.

## Legacy runtime disable mechanism and its honestly-documented gap

A caught boot failure marked the offending extension `disabled_by_error` **at runtime only** — the legacy REF stated explicitly that **no flag was persisted** to storage or config. Logging went through a dedicated `boot` channel at `error` level (replacing the old silent debug-trace), and a `kernel::failed_extensions()` registry exposed `['slug' => ['exception', 'distribution', 'message', 'timestamp']]` for programmatic or manual inspection.

**A gap inside the legacy pair itself, worth preserving:** the ADR-609 body's own stated consequence claimed an admin would be notified "on the next visit to the MIDDAG panel (an inline, non-blocking notification)" — but its own REF companion stated flatly that **no such inline admin notification exists**; the real mechanism is the logger plus `kernel::failed_extensions()`, consulted programmatically or manually. In other words: the disable-on-failure mechanism was real and runtime-only; the admin-facing alert UI was aspirational and, per the REF, never built.

## Defaults for an extension with no explicit distribution (legacy)

An extension under the host plugin's own namespace defaulted to `NATIVE` when no `DISTRIBUTION` constant was declared; one loaded from an external plugin defaulted to `THIRD_PARTY`.

## Anti-patterns (legacy REF, across all four source ADRs)

- `$this->container->get(...)` inside `register()`, or `$this->container->bind(...)` inside `boot()` — both are phase violations.
- Dispatching a signal, or accessing another extension's service, from inside `register()`.
- Omitting the required identity constant an extension needs to be discoverable.
- A circular `REQUIRES` declaration.
- `new {Service}()` inside `boot()` instead of resolving through the container.
- Treating `THIRD_PARTY` as `NATIVE` by omission — this takes the whole product down for a failure that was supposed to be isolated.
- Catching a `route_loader` error and continuing anyway — this hides a genuinely structural `boot()` failure instead of surfacing it.
- Continuing to use an extension in the same request after it has already failed.
- Relying on `debug::trace()` as the only visibility into a boot failure — invisible whenever debugging is off, which is exactly the failure mode this whole ADR pair exists to close.
