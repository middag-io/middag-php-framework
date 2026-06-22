# Bootstrap — features and extension points

> How the MIDDAG kernel boots, what the framework **already provides** at boot, and **what each tier
> extends**. Focused on features and seams; the boundary rule lives in
> [`architecture.md`](architecture.md). **The codebase is the source of truth** (symbols verified in
> `src/`).

## 1. The boot phases (`Kernel/ContainerFactory::build()`)

```
build(BootstrapInterface $bootstrap, array $synthetics = [], ?string $projectRoot = null)
  │
  1. registerSyntheticDefinitions()   ← declare placeholders (ContainerInterface + the synthetics)
  2. $bootstrap->configure($builder)  ← the ADAPTER registers host services
  3. ServiceProvider::register()      ← auto-discovery by suffix (when $projectRoot != null)
  4. $container->compile()            ← FREEZE the graph (definitions immutable from here)
  5. set() each synthetic             ← inject runtime instances (e.g. the host's $DB)
  6. AbstractFacade::setFacadeContainer()
  │
bootModules($modules)                 ← a separate step: runs ModuleInterface::boot() under the BootFailurePolicy
```

**Critical invariant:** after `compile()` the container is **frozen** — you cannot add a definition
or re-autowire. The **only** post-compile operation allowed is `set()` of a _synthetic_ (a
placeholder pre-declared in phase 1). That is why `addSynthetic()` throws
`MiddagLifecycleViolationException` if called after `build()`.

## 2. Features the framework already provides at boot

| Feature                                              | Symbol                                                       | OSS default                                                          |
|------------------------------------------------------|--------------------------------------------------------------|----------------------------------------------------------------------|
| Phased, compiled DI container (PSR-11)               | `ContainerFactory`                                           | Symfony `ContainerBuilder`                                           |
| Service auto-discovery by suffix                     | `ServiceProvider` (`*Service`, `*Repository`, `*Controller`…)| scans `{projectRoot}/src`                                           |
| Single-interface auto-alias → impl                   | `ServiceProvider::registerInterfaceAliases`                  | on                                                                  |
| Synthetics (host globals, post-compile)              | `addSynthetic()` / `setSynthetic()`                          | `ContainerInterface`                                                 |
| **Lazy services** (ghost proxy, built on first use)  | `#[Lazy]` + `LazyServiceInstantiator`                        | on (no-op without `#[Lazy]`)                                         |
| **Fatal boot isolation**                             | `BootFailurePolicyInterface`                                 | `BootRethrowFailurePolicy` (dev) / `BootIsolateFailurePolicy` (prod) |
| **Real fatal guard (shutdown)**                      | `Http/FatalErrorHandler`                                     | opt-in via `register()`                                              |
| **Resource cleanup on shutdown**                     | `Kernel/ShutdownCleanup`                                     | opt-in via `register()`                                              |
| **Host maintenance gate**                            | `MaintenanceGateInterface`                                   | `NullMaintenanceGate` (never under maintenance)                      |
| Facades (testable static proxy)                      | `AbstractFacade` (`swap`/`reset`)                            | on                                                                  |
| 12-factor config                                     | `ConfigResolverInterface`                                    | `EnvConfigResolver`                                                  |
| Form pipeline (validation + Inertia)                 | `ServiceProvider::registerFormDefaults`                      | bound, overridable                                                   |

## 3. What the adapter MUST implement (bridge contracts)

The adapter (Moodle/WordPress) is what plugs the host in. It implements:

| Contract                       | For                                                                | Moodle (ref.)                                        | WordPress (ref.)           |
|--------------------------------|--------------------------------------------------------------------|------------------------------------------------------|----------------------------|
| `BootstrapInterface`           | register host services + define `platform()`/`getProjectRoot()`    | `MoodleBootstrap`                                    | `WordPressBootstrap`       |
| `ConnectionAdapterInterface`   | `$DB`/`$wpdb` as a connection                                      | `MoodleConnectionAdapter`                            | `WpdbConnectionAdapter`    |
| `MaintenanceGateInterface`     | host under upgrade/maintenance                                     | `$CFG->upgraderunning \|\| during_initial_install()` | `wp_is_maintenance_mode()` |
| `ConfigResolverInterface`      | host config                                                        | `MoodleConfigResolver`                               | `WpConfigResolver`         |
| `UserContextResolverInterface` | host user identity                                                 | `MoodleUserContext`                                  | `WpUserContext`            |
| `TranslatorInterface`          | host i18n                                                          | `get_string()` wrap                                  | `__()` wrap                |
| `HostEventBridgeInterface` *(experimental)* | translate an event ↔ native hook (the core signal layer is used in practice) | —                                  | —     |
| `HostComponentContextInterface` | neutral host component identity (`componentName()`) + `assetVersion()` + `basePath()`, registered once by the composition root via `HostContext::set()` | `MoodleHostContext`                | `WpComponentContext`       |

> The names `MoodleBootstrap`/`WordPressBootstrap` (and likewise `MoodleHostContext`/`WpComponentContext`)
> live in the **adapter repos** (`middag-php-moodle`, `middag-php-wordpress`) — they do **not** exist in
> this OSS repo (the adapters are not built yet).

> **Host context registration.** The host composition root calls `HostContext::set()` **once** during
> bootstrap, before any Inertia/bootstrap logic reads it. `HostContext` is a composition-root registry,
> **not** a synthetic in the DI graph: static adapter helpers that live outside the container read the
> active context via `HostContext::get()`, which returns `null` when no host has configured one — so
> those callers degrade gracefully instead of failing.
>
> **Relationship to `ComponentNameResolverInterface`.** Both surface the identifier of the host component
> that owns the boot cycle (a Moodle frankenstyle like `local_example`, or a WordPress plugin slug), but
> for different consumers. `ComponentNameResolverInterface::nativeComponent()` exists solely so the
> boot-failure policy (`BootRethrowFailurePolicy`) can classify a failing class as native vs third-party
> during fatal-boot isolation. `HostComponentContextInterface::componentName()` exposes that same identity
> as one field of the broader neutral runtime-context descriptor (alongside `assetVersion()` and
> `basePath()`) that adapter helpers read through `HostContext`. The two **overlap only on the identifier
> value**: they are separate contracts, resolved/registered independently, because they serve distinct
> seams (boot-failure classification vs. neutral context lookup).

## 4. Concrete flow: how the product boots the kernel

The **product** (the Composition Root, e.g. `local_middag`) is the one that chooses the bootstrap —
in **code**, not config, because it knows which host it runs on:

```php
// The product's AppBootstrap (pseudo-code)
$bootstrap = new MoodleBootstrap(...);              // the target host's adapter

if ($bootstrap->maintenanceGate()->isUnderMaintenance()) {
    return;                                          // host upgrading → MIDDAG does not boot
}

$factory = new ContainerFactory(
    logger: $logger,
    failurePolicy: new BootIsolateFailurePolicy($logger),   // prod: do not take the host down
);
(new FatalErrorHandler($logger, $profile, debug: false))->register();  // no white screen
$shutdown = new ShutdownCleanup($logger);
$shutdown->register();

$container = $factory->build($bootstrap, $synthetics, $bootstrap->getProjectRoot());
$factory->bootModules($modules);
```

- **Standalone** (outside a host): `StandaloneKernel` is the HTTP entry point; the bootstrap is a
  minimal `BootstrapInterface` (`platform() === 'standalone'`), the gate is `NullMaintenanceGate`.
- **Where** the adapter triggers this in the host: Moodle = a callback / `setup`; WordPress =
  `plugins_loaded` / `init`. That point is the adapter's responsibility (to be confirmed when the
  adapters are built).

## 5. What each tier extends

- **Adapter (Pillar 2):** implements the bridge contracts (§3). Binds
  `BootIsolateFailurePolicy`, registers `FatalErrorHandler`/`ShutdownCleanup`, decides the injection
  point in the host.
- **Core (Pillar 3):** subclasses `ServiceProvider`/modules for Signal/Job/EAV/multi-tenant; binds a
  governed boot policy; **never** imports an adapter.
- **Product (Pillar 4):** the `AppBootstrap` (§4) — wires framework + core + adapter; marks heavy
  services with `#[Lazy]`; registers `addCleanup()` for the resources it opens.

## 6. Known residue

- **Multi-plugin collision** (two MIDDAG products on the same host with different versions): PHP does
  not isolate classes per plugin. The real mitigation is **PHP-Scoper/Mozart** at the product's build
  (it prefixes the `Middag\` namespace). The framework helps with per-instance state (no global
  `static`), but does not resolve the double autoload. Document this in the product guide.
- `ContainerFactory::build()` has no global try/catch around `compile()`; the `FatalErrorHandler`
  (shutdown) covers the boot fatal, but there is no granular per-service recovery at compile time.
