# Architecture

> The normative architecture of `middag-io/framework`, and how it sits inside
> the wider MIDDAG open-source ecosystem: boundaries, pillars, the module layout
> rule, and the bridge contracts an adapter implements. The Moodle and WordPress
> adapters and `middag-io/ui` follow this document.
>
> For code style, commits, and the PR checklist see
> [`../CONTRIBUTING.md`](../CONTRIBUTING.md). **The codebase is the source of
> truth** — this document states the _rule_; live structure and counts come from
> `src/`.

License: Apache-2.0.

## What the framework is — and is not

The framework exists to **decouple your business domain from its host**. You
write controllers, forms, queries and services against contracts, then run the
same code standalone, inside Moodle, or inside WordPress, by swapping a thin
adapter. The payoff is testability, portability, and a domain that survives
platform upgrades.

**It is:**

- **A host-agnostic kernel** — DI, HTTP, routing, a command bus, an immutable
  query builder, forms, logging, an Inertia bridge, and a typed exception
  hierarchy — wrapped over Symfony, PSR, Monolog and Inertia under one coherent
  API.
- **Contract-first** — every host-facing capability is an interface with an OSS
  default, so the framework runs with no adapter at all.
- **An Active-Record layer that is Eloquent-_like_, not Eloquent** — its own,
  host-agnostic, built on `ConnectionAdapterInterface` (see [Persistence](#_6-persistence-active-record-eloquent-like-not-eloquent)).

**It is not:**

- **A host** — it never knows Moodle or WordPress; that lives in adapters.
- **The governed domain engine** — reliable event delivery (Signal + outbox),
  async jobs with retry/audit, EAV, multi-tenancy and licensing are the
  proprietary `middag-io/core`, layered on top. The framework ships only the
  generic seam (for example the `SignalDispatcherInterface` contract; the outbox
  implementation is core).
- **A framework that hides your domain** — the goal is the opposite: your domain
  free of the host's prison.

The five promises the framework keeps — the domain is absolute (POPO), the host
is a detail (Ports & Adapters), persistence is isolated to infrastructure, DX
without guilt (DI + Facades as real proxies), and native events in a modern
language (PSR-14 events translated to host hooks) — map directly onto the
boundaries below.

## 1. The four pillars (dependency rings)

The ecosystem is **Hexagonal (Ports & Adapters)** in four rings. Dependency
**only ever points downward**.

```
┌─ Pillar 4 — Composition Root (Product) ── moodle-local_middag / wp-plugin-middag ──┐
│  Aggregate Roots, vertical slices, the business. Wires everyone in composer.json.  │
└───────────────┬───────────────────────────────────┬──────────────────────────────┘
                │ imports                            │ imports
        ┌───────▼────────┐                  ┌────────▼─────────┐
        │ Pillar 3 — CORE │                  │ Pillar 2 — ADAPTERS│
        │ (proprietary)  │                  │ (OSS)            │
        │ Signal/outbox, │                  │ middag-php-moodle│
        │ Job, EAV,      │                  │ middag-php-      │
        │ multi-tenant,  │                  │ wordpress        │
        │ licensing      │                  │ ($DB/$wpdb wrap) │
        └───────┬────────┘                  └────────┬─────────┘
                │ imports                            │ imports
                └──────────────┬─────────────────────┘
                       ┌───────▼─────────────────────────────────┐
                       │ Pillar 1 — OSS Foundation                │
                       │ middag-php-framework  ──▶  middag-php-ui  │
                       │ (Ports: contracts, kernel, query, forms) │
                       └──────────────────────────────────────────┘
```

| Pillar | Repositories                                           | What it is | Dependency rule |
|--------|--------------------------------------------------------|------------|-----------------|
| **1. OSS Foundation** | `middag-php-framework`, `middag-php-ui`                | The **Ports**: contracts, DI, HTTP, query, forms, bus, PSR-14 events. | `framework` **wraps Symfony/PSR/Monolog** (not zero-dep — see §2). `ui` is **pure PHP** (`require: php` only). |
| **2. OSS Adapters** | `middag-php-moodle`, `middag-php-wordpress`            | The **Adapters**: implement the bridge contracts by talking to `$DB`/`$wpdb`. | Depend **strictly** on `middag-php-framework` + the platform. **Never** import `core` (they are OSS). |
| **3. CORE (proprietary)** | `middag-php-core`, `middag-php-licensing`, `middag-php-dev-tools` | The business engine: governed Signal + outbox, async Job, EAV, multi-tenant, licensing. | Depends only on `middag-php-framework`. Does **not** depend on the adapters — it runs identically on WP and Moodle by programming against the Pillar 1 interfaces. |
| **4. Composition Root** | `moodle-local_middag`, `wp-plugin-middag-account`      | The real modular monolith: Aggregate Roots, vertical slices. | The only one that wires everything: imports framework + core + a specific adapter. |

### The boundary invariant (the rule everything respects)

```
core (proprietary) ──imports──▶ framework (OSS) ──imports──▶ ui (OSS)
       ▲ never back ──────────────────┘ never back ────────────────┘
```

OSS libraries **never** import `core`/proprietary code. If OSS code needs
governed pub/sub, async jobs, EAV or multi-tenancy, **it is in the wrong place**
→ it belongs to core. The framework exposes only the **contract** (for example
`Bus\Contract\SignalDispatcherInterface`); the outbox/Signal implementation is
core.

Because the adapters are independent OSS libraries written against the same
contracts, the ecosystem is a real pluggable platform: a community could, in
principle, build a `middag-php-magento` or `middag-php-drupal` adapter. This
document is the normative home for the ecosystem boundary; `middag-io/ui` and
the adapters link here rather than restating it.

## 2. What Pillar 1 actually depends on

> A factual correction over older guide docs that said _"Pillar 1 depends on
> nothing but PHP and PSR"_. That holds **only for `middag-php-ui`**.

- **`middag-php-ui` — pure PHP.** `composer.json require` = `php: ^8.2`. Zero
  runtime deps. Grepping `Moodle|wpdb|PDO|Database\\|CommandBus|Illuminate|Symfony\\`
  in `src/` returns empty. It is the only truly zero-dependency pillar.
- **`middag-php-framework` — wraps Symfony.** It does not reinvent the wheel: it
  wraps **Symfony** (`dependency-injection`, `http-kernel`, `routing`,
  `messenger`, `validator`, `serializer`, `cache`, `uid`, `clock`), **PSR**
  (3/6/7/11/14/15/16/17/18), **Monolog** and **nyholm/psr7** under one coherent,
  Symfony-like API. `illuminate/database` appears **only as a `suggest`** (an
  optional connection adapter for those who want real Eloquent), never a
  `require`.

Pillar 1's agnosticism is about **not knowing the host** (Moodle/WP) — not about
being vendor-free. The framework has heavy deps; what it does not have is any
`use Moodle\` / `global $DB`.

## 3. Module layout — the 3-tier rule (every OSS lib)

The layout is **concern-first** at the root of `src/`: each responsibility is a
top-level directory (`Bus/`, `Database/`, `Http/`, `Form/`, `Persistence/`, …).
There is no generic `Infrastructure/`, `Service/` or `Helper/` at the root — the
directory name is the _what_, not the _layer_.

Inside each `src/<Concern>/`, organise into **3 tiers**. The rule is
_boundary-based_: a subfolder marks **where you extend**, not "a place with ≥2
files". A one-file seam is intentional.

```
src/<Concern>/
├── Contract/                    # TIER-TYPE — interfaces only, *Interface suffix, zero impl
│   └── ReportRendererInterface.php
├── Enum/                        # TIER-TYPE — backed, immutable enums
├── Attribute/                   # TIER-TYPE — PHP 8 metadata (#[Attribute])
├── Renderer/                    # TIER-SEAM — extension point named by role,
│   ├── HtmlReportRenderer.php   #             created from the first file
│   └── RendererRegistry.php
├── Report.php                   # TIER-ROOT — concern entry point / active-record base
└── ReportFactory.php            # TIER-ROOT — factory / orchestrator of the concern
```

| Tier          | What it is                                                          | Sub-namespace      |
|---------------|---------------------------------------------------------------------|--------------------|
| **TIER-TYPE** | `Contract/`, `Enum/`, `Attribute/` — never empty when they exist    | inside the concern |
| **TIER-SEAM** | a subfolder by role/extension (`Renderer/`, `Field/`, `Transport/`) | inside the concern |
| **TIER-ROOT** | entry points + active-record bases                                  | concern root       |

**Create the seam from the first file — do not "promote later".** PSR-4 ties
namespace to path; moving `Foo.php` → `Renderer/Foo.php` later ripples the
namespace across repos.

**TIER-TYPE is absolute for the whole ecosystem.** Every interface of every lib
lives in `<Concern>/Contract/` — always, no exceptions (for example
`Page/Contract/PageInterface.php`, `Bus/Contract/MessageBusInterface.php`). No
interface at the concern root; the `*Interface` suffix is mandatory. Each concern
owns its own `Contract/` — there is **no** global `Contract/`.

## 4. Critical guardrail — the UI is agnostic of Infra/Bus

The presentation layer **must stay agnostic**. The UI builders (`middag-php-ui`:
`Block/`, `Page/`, `Table/`, `Form/`, `Navigation/`, `Region/`, `Action/`)
describe **what** to render — never **how** the data arrived nor where it goes.

**Forbidden in `middag-php-ui`:** importing `Database`, `Connection`,
`CommandBus`/Bus, `PDO`, the query builder, or any HTTP kernel.

**Translation to the wire (Inertia v3) is a framework seam, not a UI one.** The
framework consumes the UI contract and emits the Inertia protocol, in
`Http/Inertia/` and `Form/Renderer/InertiaRenderer` (an implementation of
`Middag\Ui\Form\FormRendererInterface`).

```
[ middag-php-ui ]            [ middag-php-framework ]              [ host ]
 Block/Page/Form    ──▶   Http/Inertia/  +  Form/Renderer/   ──▶   browser
 (pure contract)          (consumes contract, emits wire v3)
        │
        └── NEVER knows Database / Bus / Connection / PDO
```

Rule of thumb: **if presentation logic needs to know about a query or the bus,
the boundary has been violated** — move the decision to the controller/handler in
the framework and hand the UI only the ready data.

## 5. Bridge contracts (adapters MUST implement)

Adapters plug the host in by implementing these contracts. The framework already
ships an **OSS default** for each, so it runs standalone with no adapter.

| Contract                       | Home (concern) + OSS default                           | Moodle                  | WordPress             |
|--------------------------------|--------------------------------------------------------|-------------------------|-----------------------|
| `BootstrapInterface`           | `Kernel/Contract/`                                     | MoodleBootstrap         | WordPressBootstrap    |
| `ConfigResolverInterface`      | `Kernel/Contract/` — default `EnvConfigResolver`       | MoodleConfigResolver    | WpConfigResolver      |
| `HostEventBridgeInterface` *(experimental)* | `Kernel/Contract/` — generic sync bridge, no default; the core signal layer is used in practice | —       | —  |
| `ConnectionAdapterInterface`          | `Database/Contract/` — default `PdoConnectionAdapter`  | MoodleConnectionAdapter | WpdbConnectionAdapter |
| `UserContextResolverInterface` | `Bus/Contract/` — default `NullUserContextResolver`    | MoodleUserContext       | WpUserContext         |
| `MaintenanceGateInterface`     | `Kernel/Contract/` — default `NullMaintenanceGate`     | `$CFG->upgraderunning`  | `wp_is_maintenance_mode()` |
| `FormRendererInterface`        | **`middag-io/ui`** — framework provides `InertiaRenderer` | MformRenderer        | InertiaFormRenderer   |
| `TranslatorInterface`          | `Translation/Contract/` — default `FallbackTranslator` | MoodleTranslator        | WpTranslator          |
| `MailerInterface`              | `Mail/Contract/` — default `NullMailer` (discards)     | MailerAdapter (`email_to_user`) | WpMailer (`wp_mail`) |
| `FilesystemInterface`          | `Filesystem/Contract/` — default `LocalFilesystem`     | MoodleFilesystem (moodledata) | WpFilesystem (uploads) |
| `PasswordHasherInterface`      | `Security/Contract/` — default `NativePasswordHasher`  | delegates to the host hasher | delegates to the host hasher |

> `Mailer`/`Filesystem`/`PasswordHasher` are **infra ports with a working OSS
> default** (they run standalone); the adapter overrides them to talk to the
> host's protected storage/credentials.

An adapter **never imports core**; core governance runs first, and the adapter
delivers an opaque envelope.

## 6. Persistence — Active Record "Eloquent-like", not Eloquent

The framework **does not depend on Eloquent**. It provides its own,
host-agnostic, Eloquent-inspired Active-Record layer, built on
`ConnectionAdapterInterface`:

- `Persistence/Model.php` — the Active Record base (`find`/`all`/`where`/`save`/
  `delete`/casts/`fillable`, creators, opt-in timestamps, fresh/refresh/replicate,
  local scopes, relationships + eager loading). `save()` is **not final**;
  persistence lives in `performInsert()`/`performUpdate()` (seams), so **core**
  can subclass and wrap them with audit/revision/events **without the OSS knowing**.
- `Persistence/Query/QueryBuilder.php` — an **immutable** query builder (each
  fluent method returns a new copy) with an **ON** mode (executes terminals via
  the connection) and an **OFF** mode (`compile()`/`toSql()`/`getBindings()` for
  inspection/cache/test without a connection).
- `Persistence/Repository/AbstractRepository.php` + `Mapper/AbstractMapper.php` —
  the **Data-Mapper** path to the domain: hydrates POPO entities
  (`EntityInterface`) from rows; the AR `Model` **never leaks** out of infra.

**Two paths coexist by design:** AR (`Model`) for fast writes inside infra;
Data-Mapper (`Repository` → POPO) for a clean domain. The rule holds: **no domain
entity extends `Model`; no controller calls the builder directly** — that is the
job of the infrastructure/repository layer.

### 6.1 Active-Record ↔ Eloquent compatibility matrix (public contract)

The framework's AR **honours a subset** of Eloquent's public API with the same
names/semantics, plus one extra (the OFF mode). A Laravel developer recognises
the surface, but **must know the gaps and divergences below** — copying Eloquent
habits blindly breaks.

**✅ Compatible (same name, same semantics):**
`find` · `findOrFail` · `all` · `first` · `where` · `orWhere` · `whereIn` ·
`whereNotIn` · `orWhereIn` · `whereBetween` · `whereNull` · `whereNotNull` ·
`whereColumn` · `orWhereColumn` · `select` · `addSelect` · `distinct` · `join` ·
`leftJoin` · `orderBy` · `orderByDesc` · `latest` · `oldest` · `groupBy` ·
`having` · `orHaving` · `union` · `unionAll` · `limit` · `offset` · `forPage` ·
`lockForUpdate` · `sharedLock` · `value` · `pluck` · `count` · `sum` · `avg` ·
`min` · `max` · `exists` · `get` · `cursor` · `chunk` · `lazy` · `insertGetId` ·
`updateOrInsert` · `upsert` · `save` · `delete` · `fill` · `toArray` · `getKey` ·
`getKeyName` · `create` · `firstOrNew` · `firstOrCreate` · `updateOrCreate` ·
`fresh` · `refresh` · `replicate` · `hasOne` · `hasMany` · `belongsTo` ·
`belongsToMany` · `with` · `load` · local scopes (`scopeX`) · `$fillable` ·
`$guarded` · `$casts` · `$incrementing` · `$primaryKey` · `$table` · `$timestamps`.

**⚠️ Behavioural divergences (footguns — always document):**

| Aspect | Eloquent | MIDDAG AR | Impact |
|--------|----------|-----------|--------|
| **Builder mutability** | Mutable (`$q->where()` mutates in place) | **Immutable** (returns a copy) | `$q->where('a',1);` **without reassigning is a silent no-op**. Always `$q = $q->where(...)` or chain. |
| **Collection return** | `Illuminate\Support\Collection` | **plain `array`** | No Collection `->map()/->filter()`; use array functions. |
| **`paginate()`** | Reads the page from the global request | **explicit `paginate(int $page, int $perPage)`** | Host-agnostic by design — never coupled to the request. |
| **Casts** | Rich + custom `CastsAttributes` | **primitives + backed enum + array/json**, lazy on read | Custom casts = implement in the mapper/core. |
| **Relation collection** | `Collection` (lazy/eager) | **plain `array`** + cache in `$relations` | Eager via `with()`/`load()`; no Collection chaining. |
| **`$timestamps`** | Default `true` | **Default `false`** (opt-in) | A table without `created_at`/`updated_at` does not break; enable per model. |

**❌ Absences vs Eloquent (not in OSS — a recorded gap):**

- **Model events/observers** (`saving`/`saved`/`creating`…) — **intentional**:
  core's "gold" via subclass (`performInsert`/`performUpdate` are the seams).
- **Global scopes** (multi-tenant `GlobalScope`) — **core** (local `scopeX()` are OSS).
- **Audited soft deletes** (`SoftDeletes`, `withTrashed`) — **core**.
- **Relation wheres**: `whereHas`/`has`/`withCount` — need a correlated subquery;
  added only with a real case.
- **Custom casts** (`CastsAttributes`) — OSS covers primitives + backed enum +
  array/json; a custom object = mapper/core.
- **Rich Collection** (`Illuminate\Support\Collection`) — intentional divergence:
  plain `array`.

> Gap policy: the OSS stays **deliberately lean**. Governed features (model
> events, multi-tenant global scopes, audited soft delete) belong to **core** via
> subclass. Query-builder parity + Model conveniences (creators, opt-in
> timestamps, rich casts, fresh/refresh/replicate, local scopes, relationships +
> eager loading) are **OSS and host-agnostic**. New public surface only lands with
> a real use case — open an issue first.

## 7. Async convergence and events

- **One bus**: `Bus/MessageBus` (`CommandBus`, CQRS sync) + async via
  `MessageBusInterface` (Symfony Messenger) + transport routing. OSS default =
  `InMemoryTransport`. **Governed Signal + outbox = core.**
- **Host events**: `HostEventBridgeInterface` (an experimental generic sync bridge; the core signal layer is used in practice) +
  per-instance `HookManager` (priority filters/actions, profiling). The domain
  fires rich PSR-14 events; the **adapter** translates them to the host's
  `do_action()`/triggers. The outbox (post-commit dispatch) = **core**.

## 8. Locked architectural decisions

- **D1** — the framework defines interfaces; adapters implement the kernel pattern.
- **D2** — `ExtensionInterface` → `ModuleInterface` (`Kernel/Module/`). The MIDDAG
  Extension + loaders are core; adapters only know `ModuleInterface` (OSS).
- **D3** — the QueryBuilder is **immutable**; execution is the adapter's, via
  `ConnectionAdapterInterface`.
- **D5** — async convergence: one sync bus + async via Messenger + routing.
  Signal/outbox → core.
- **D6** — a `ConnectorRegistry` interface + a `NullConnector` in the framework.

## 9. Known OSS boundaries (seams, not bugs)

These are **intentionally unresolved in the OSS** — the seam exists; the
resolution belongs to the tier above. Documented for architectural honesty.

| Boundary | OSS seam that exists | Who resolves it |
|----------|----------------------|-----------------|
| Multi-plugin collision (same host, different versions) | per-instance state (no `static`) | Product (PHP-Scoper/Mozart at build) |
| No built-in rate limiting | `#[Middleware]` (+ `suggest symfony/rate-limiter`) | Adapter/Product/Core |
| Input sanitisation not centralised (validation only) | `AbstractFormRequest::rules()` / `#[ValidatedDto]` | Controller/Adapter |
| No boundary linter (host → domain) | convention + PHPStan | deptrac in Product/Core |
| Forward-only migrations (no `down()`) | `MigrationRunner::onUpgrade()` | Adapter (xmldb/dbDelta) |
| No service-locator guard (Facades are service locators) | discipline + `composer check` | PR convention / dev-tools PHPStan rule |
| Outbox (post-commit) + durable job queue | `SignalDispatcherInterface`/`MessageBusInterface` | **Core** (by design) |

### 9.1 Contextual binding (native Symfony DI — no framework code)

"Same interface, different impl depending on who asks" (for example a read
replica only for `ReportRepository`, the primary for the rest) **needs no new
framework code**: the engine is Symfony DI, which has native contextual binding.
The `ContainerBuilder` exposed at boot is the seam; the wiring is product config:

```php
// The default (autowiring/alias) gives the primary to everyone;
// only ReportRepository receives the read replica.
$container->getDefinition(ReportRepository::class)
    ->setArgument('$connection', new Reference('connection.replica'));
```

The framework keeps single-interface auto-aliasing (the common case); contextual
binding is **product opt-in**.

### 9.2 Ownership map (who owns what)

> Legend: **[FW]** Framework (OSS) · **[ADAPTER]** host adapter · **[CORE]** proprietary core · **[PRODUCT]** composition root · **[UI]** `middag-io/ui` · **[DEVTOOLS]** dev-tools.

- **[FW] Framework (OSS):** auth (`#[Auth]`, agnostic, no `symfony/security`) ·
  CSRF · CORS (`CorsMiddleware`) · Inertia (version-skew, props) · logging
  (`RotatingStreamHandler`, secret redaction default-on) · errors (`mapThrowable`
  + status-mapped `MiddagException`, opt-in `FatalErrorHandler`, debug renderer
  default-off) · command bus (+ `InMemoryTransport` + `CommandWorker`) · API
  surface (`#[Route]`, `AbstractFormRequest`/`#[ValidatedDto]`, null-safe `toArray`) · schema
  (`SchemaBuilder`, forward-only `MigrationRunner`, `transaction`) · infra ports
  with OSS defaults (`Clock`, `Filesystem`/`LocalFilesystem`, `Mailer`/`NullMailer`,
  `Session`, `EnvConfigResolver`, `Translator`/`FallbackTranslator`, PSR-16 cache,
  `FlashBag`) · `#[TrustedOutput]`.
- **[ADAPTER] Adapter:** host-capability auth · input sanitisation (e.g. undo WP slashes)
  · assets/url/i18n catalog/native REST · host cache pool · log base path · host
  error translation · native filesystem/mailer · host schema (xmldb/dbDelta) +
  prefix · uninstall/roles.
- **[CORE] Core (proprietary):** governed event delivery · async jobs · EAV query
  engine · multi-tenancy · licensing — the governed domain infrastructure built on
  top of this OSS, opt-in; the framework never imports it.
- **[PRODUCT] Product (composition root):** per-route rate limiting · single-run lock
  (`symfony/lock`) · CORS wiring · slugifier · vault/secrets · Redis driver ·
  seeders · E2E · timezone policy · settings memoisation.
- **[UI] `middag-io/ui`:** `FormRendererInterface` / field contracts (rendered to
  the wire by the framework).
- **[DEVTOOLS] dev-tools:** generators (`install.xml`/`upgrade.php` from descriptors; the
  facade generator).

### 9.3 Service discovery and HTTP attribute composition

**One OSS service-discovery engine, plus a bridge contract.** The framework ships
`ServiceProvider` (static, PascalCase suffix conventions) — the default wired by
`ContainerFactory::build()` for standalone/framework boots — and publishes the
`ServiceLoaderInterface` contract for host-integrated engines. The instance engine
that backs adapters (snake_case conventions, cache + self-pruning + DI tagging,
plugin discovery hook, boot-failure policy) is an adapter/core concern: it lives
downstream (e.g. core's `ServiceLoader`, which Moodle's `MoodleServiceLoader`
subclasses), **not** in the OSS framework. This mirrors *module* and *facade*
discovery — the OSS keeps only the `ServiceLoaderInterface` / `ModuleLoaderInterface`
/ `FacadeLoaderInterface` contracts (D2), not the host implementations.

**Route auto-discovery is a seam, not yet wired.** `RouteLoader` scans `#[Route]`
attributes off a controller into a `RouteCollection`, but the OSS `HttpKernel`
receives a pre-built collection — the host assembles routes (standalone lists them;
adapters ship their own loader). A turnkey standalone route-discovery factory is
roadmap, not shipped.

**`#[Auth]` overrides, `#[Middleware]` stacks.** Both read the method then the class,
with opposite merge rules: `#[Auth]` is *override* (a method-level attribute replaces
the class-level one — first non-empty wins), while `#[Middleware]` is *stacking*
(class-level and method-level accumulate; class is outermost, method innermost, each
in declaration order). Effective request order: route match → auth flags → auth gate
→ `preHandle()` → [class middleware → method middleware → action].

**Request validation has two styles, one resolver chain.** A controller validates
input either by type-hinting an `AbstractFormRequest` subclass (declarative `rules()`
returning a `field => Symfony Constraint` map, validated as an `Assert\Collection`
over the input array) or by marking a parameter `#[ValidatedDto]` (a plain class
whose properties carry `#[Assert\*]`; `ValidatedDtoResolver` reads the input via the
shared `RequestPayload`, hydrates it into the typed object — snake_case input →
camelCase properties, scalar coercion — then validates the object). Both throw
`MiddagValidationException` (HTTP 422) before the action runs and share one error-map
shape. The DTO stays a plain, reusable class with no framework base: the same
properties can also carry `#[Field]` (form schema), so one DTO is the single source
of truth for shape and validation. Reach for the array style for dynamic/loosely
shaped input; reach for the DTO for a typed contract.

## 10. Pre-PR boundary checklist

1. **No host.** Zero `use Moodle\` / `global $DB` / `add_action()` / `Illuminate\`
   in framework and ui.
2. **No core.** No OSS library imports `middag-io/core`/`licensing`/`dev-tools`.
3. **UI agnostic.** `middag-php-ui` imports no `Database`/`Bus`/`PDO`/HTTP kernel (§4).
4. **Contract first.** A new interface lives in `<Concern>/Contract/` with the
   `*Interface` suffix; the consumer depends on it, not on the implementation.
5. **Seam from the first file** (§3). Persistence: AR/builder only in infra; the
   domain holds only POPO (§6).
6. **A new Eloquent gap?** If you add AR surface, update the §6.1 matrix and
   justify host-agnosticism.
