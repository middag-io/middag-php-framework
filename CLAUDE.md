# CLAUDE.md — middag-io/framework

> A **durable** orientation guide for the agent: principles, boundaries, conventions, workflow.
> **Not a file index** (an index breaks on every move). To locate symbols, use Glob/Grep.
> **The codebase is the source of truth.** Live structure/counts come from `src/`, not from here.
> For the full technical architecture (pillars, bridge contracts, Eloquent parity, decisions) see
> [`docs/architecture.md`](docs/architecture.md).

## Mental model in 30s

MIDDAG's **core** PHP framework: a **platform-agnostic** DDD base. You write your domain against
**contracts**, not against the host. The same code runs **standalone**, inside **Moodle**, or inside
**WordPress** — by swapping the adapter.

**Why it exists (the real value):** decoupling the domain from the host gives back **DX,
testability, portability and long-term maintenance** — business rules testable without booting a
whole host, reusable across platforms, surviving a platform swap/upgrade. It is not "leave the infra
to us": the win is your domain free of the host's prison.

**It does not reinvent the wheel:** it wraps **Symfony** (DI, http-kernel, routing, messenger,
validator), **PSR**, **Monolog** and **Inertia** under one coherent API. Less of our own code to
carry bugs.

It depends on **`middag-io/ui`** (contract builders) and has **zero** dependency on `middag-io/core`.

## Rule #1 — the OSS × proprietary boundary (the invariant everything respects)

| Layer                | Packages                                                 | What it is                                                                                                                                          |
|----------------------|----------------------------------------------------------|-----------------------------------------------------------------------------------------------------------------------------------------------------|
| **OSS** (Apache-2.0) | **this repo**, `middag-io/ui`, the Moodle/WordPress adapters | generic plumbing: DI, HTTP, query, forms, command bus, sync reaction, logging, Inertia                                                          |
| **Proprietary**      | separate, non-public packages | governed domain infrastructure built on top of the OSS (opt-in); the framework never imports it |

**INVARIANT (never violate):** the OSS framework **NEVER** imports proprietary code. The
dependency only points down (proprietary → OSS). If something here needs governed domain
infrastructure, **it is in the wrong place → it belongs to the proprietary layer**. The framework
exposes only the generic pipe (e.g. the `Bus\Contract\SignalDispatcherInterface` contract stays;
the governed implementation lives downstream).

**Adapters** implement the bridge contracts; an adapter **never** imports proprietary code;
governance runs first; the adapter delivers an opaque envelope.

## How the code is organised (the RULE, not the list)

**Concern-first** at the root of `src/`: each *concern* is a top-level directory (`Bus/ Database/
Persistence/ Form/ Http/ Kernel/ Logging/ Observability/ Shared/ Translation/ Exception/`, plus the
infra-port concerns `Mail/ Filesystem/ Security/`). **No** top-level `Infrastructure/`/`Service/`.

Inside each `src/<Concern>/`, **3 tiers** (a boundary-based rule, expected in every MIDDAG lib):

- **TIER-TYPE** (never empty when populated): `Contract/` (interfaces, `*Interface` suffix), `Enum/`,
  `Attribute/` — sub-namespaces *inside* the concern, they do not escape it.
- **TIER-SEAM** (boundary): a subfolder named by role/extension point, created **from the first
  file** (e.g. `Schema/`, `Resolver/`, `Mapper/`, `Handler/`). **Do not** promote later → PSR-4 ties
  namespace=path; promoting ripples the namespace across repos.
- **TIER-ROOT**: the concern's entry points + active-record bases (e.g. `Model`, `MessageBus`).

**Golden rule:** a subfolder marks *where you extend*, not "a place with ≥2 files". A one-file seam
is intentional. Each concern owns its own `Contract/` — there is **no** global `Contract/`.
(Full rule + examples: [`docs/architecture.md`](docs/architecture.md) §3.)

### Concern purpose map (durable — no file names)

| Concern          | What it is for                                                                                                                                            |
|------------------|-----------------------------------------------------------------------------------------------------------------------------------------------------------|
| `Bus/`           | CQRS: `CommandBus` sync + async via `MessageBus` + transport routing (`InMemoryTransport`, the OSS default). |
| `Database/`      | connection abstraction (`ConnectionAdapterInterface`) + SQL dialect + schema builder/migrations; OSS default `PdoConnectionAdapter`                               |
| `Persistence/`   | `AbstractRepository`/`AbstractMapper` + the **immutable** QueryBuilder + the `Model` active record + `Page` (pagination). |
| `Form/`          | `AbstractForm` + `FormValidator` + field types + the Inertia renderer (an impl of `Middag\Ui\Form\FormRendererInterface`)                                  |
| `Http/`          | `HttpKernel` (PSR-15) + `StandaloneKernel` + Inertia (v3 protocol) + FormRequest + route middleware (`#[Middleware]`) + the auth gate (`#[Auth]`)          |
| `Kernel/`        | `ContainerFactory` (boot/DI) + `ServiceProvider`/loaders (auto-discovery) + `AbstractModule` + `AbstractFacade` + `HookManager` (per-instance)             |
| `Logging/`       | `LoggerFactory` (Monolog) per module/channel + `RotatingStreamHandler`                                                                                     |
| `Observability/` | `ErrorReporterInterface` port (`NullErrorReporter`/`SentryErrorReporter`) + `ProfileCollectorInterface` profiling sink (single `bus`/`hook`/`query` timeline) |
| `Mail/`          | `MailerInterface` port + `Mail`/`Address`/`Attachment` value objects (`cid:` embeds); OSS default `NullMailer`; adapters map onto the host sender          |
| `Shared/`        | cross-cutting: DTOs, enums (`Operator`/`SortDirection`/`DebugMode`), utils, `Attribute/TrustedOutput` (an output-trust marker; behaviour in the host adapter) |
| `Translation/`   | the i18n contract (`TranslatorInterface`) + OSS default `FallbackTranslator` (standalone); adapters wrap `get_string`/`__`                                 |
| `Exception/`     | a typed, status-mapped hierarchy consumed by `HttpKernel::mapThrowable`                                                                                    |

### Domain-model libs (DDD)

The 3-tier rule holds for the other libs, but libs that model a domain (bounded contexts in
the proprietary core) accommodate tactical building blocks: ports → `Contract/` (TIER-TYPE);
`Aggregate/`/`ValueObject/`/`Event/`/`Policy/`/`Service/` as seams (TIER-SEAM); the aggregate root +
entities → the context root (TIER-ROOT). **Open decision:** whether domain libs layer
`Domain/Application/Infrastructure` (hexagonal) on top of concern-first or stay flat-concern — layers
are orthogonal to the 3-tier and compose with it. Decide per-lib when the first domain lib is structured.

## Bridge contracts and locked decisions

The bridge contracts adapters implement (with their OSS defaults) and the locked architectural
decisions (D1–D6) live in [`docs/architecture.md`](docs/architecture.md) §5 and §8. In short: the
framework defines interfaces and adapters implement them; the QueryBuilder is immutable (execution is
the adapter's, via `ConnectionAdapterInterface`); there is one sync bus + async via Messenger.

## How to work here

- **Gates (everything green before any delivery):** `composer check` (PHPStan L6 + PHP-CS-Fixer +
  Rector, dry-run) **&&** `composer test` (PHPUnit). Auto-fix: `composer fix` (style+rector) or
  `composer fix:all` (style→rector→style; settles formatting after Rector).
- **Style:** `declare(strict_types=1)` in every file; PSR-12 + camelCase; cover new behaviour with a
  test. `@api` = public surface (enters the generated xRef/docs); `@internal` = internal. Full guide:
  [`CONTRIBUTING.md`](CONTRIBUTING.md).
- **Commits:** Conventional Commits; **NEVER** `Co-Authored-By`. One scope per commit (the commit-msg
  hook rejects comma multi-scope); mark breaking changes with `!` or a `BREAKING CHANGE:` footer.
  On the `1.x` line: fix → patch, feat → minor; a breaking change only ships in a minor cut
  deliberately via `Release-As` (never in a patch) — policy in [`API-STABILITY.md`](API-STABILITY.md).
  Branch base: `develop`.
- **Repo boundary:** this repo is OSS (Apache-2.0). It depends only on `middag-io/ui` and never on
  the proprietary layer; the framework defines contracts that downstream packages implement.

## State

The framework is **public on the `1.x` line** (Apache-2.0). The API is still consolidating: a minor
release may carry a documented breaking change, cut deliberately via `Release-As` — the policy is
[`API-STABILITY.md`](API-STABILITY.md). Technical documentation lives in **`docs/`**
(`architecture.md`, …) and is published at **docs.middag.dev**.
