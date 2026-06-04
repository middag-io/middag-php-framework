<div align="center">

# middag-io/framework

**Symfony-grade DDD architecture that runs inside a Moodle or WordPress plugin, or standalone.**

[Documentation](https://docs.middag.dev) · [Where it runs](#where-it-runs) · [Professional services](#professional-services) · [GitHub](https://github.com/middag-io/middag-php-framework)

[![CI](https://github.com/middag-io/middag-php-framework/actions/workflows/ci.yml/badge.svg)](https://github.com/middag-io/middag-php-framework/actions/workflows/ci.yml) [![License: Apache 2.0](https://img.shields.io/badge/License-Apache_2.0-blue.svg)](https://opensource.org/licenses/Apache-2.0) [![PHP](https://img.shields.io/badge/php-%5E8.2-777BB4.svg)](https://www.php.net/) [![PHPStan](https://img.shields.io/badge/PHPStan-level%206-brightgreen.svg)](https://phpstan.org/)

[![Packagist Version](https://img.shields.io/packagist/v/middag-io/framework.svg)](https://packagist.org/packages/middag-io/framework) [![Packagist Downloads](https://img.shields.io/packagist/dt/middag-io/framework.svg)](https://packagist.org/packages/middag-io/framework)

</div>

## About

`middag-io/framework` is a platform-agnostic PHP framework for building business domains that do not depend on their host. You write controllers, forms, queries and services against contracts, then run the exact same code inside a Moodle plugin, a WordPress plugin, or as a standalone app by swapping a thin adapter.

> Your domain should not know, or care, where it runs.

Status: alpha (the public API may still change). License: Apache-2.0.

## Why middag-io/framework?

Plugin code couples business logic straight to the host: `$DB`, `$CFG` and `mform` on Moodle, `$wpdb` on WordPress. That coupling is exactly what makes a plugin impossible to test without booting a full host, impossible to reuse on another platform, and fragile on every upgrade. This framework breaks the coupling, and gives you back four things:

- **Testable** — exercise your domain against an in-memory database, with no host running. The suite proves it.
- **Portable** — write the domain once; host it on Moodle, WordPress, or nothing at all, by changing one adapter.
- **Maintainable** — the domain outlives platform upgrades and migrations, because it never depended on the platform.
- **Familiar** — one coherent API over Symfony, PSR, Monolog and Inertia, not a new dialect to learn.

## Built on giants

We did not reinvent the wheel. Under the hood it is Symfony (dependency injection, HTTP kernel, routing, Messenger, Validator), plain PSR contracts, Monolog and Inertia, wrapped in a single host-agnostic API. Less of our code to carry bugs, battle-tested foundations underneath.

## Installation

Requires PHP `^8.2` and the `curl`, `intl`, `json`, `mbstring` and `pdo` extensions. Runs standalone or behind a host adapter.

```bash
composer require middag-io/framework
```

## Quick start

A controller that loads your domain and renders an Inertia page: attribute routing, a domain service injected by the container, and not a single host API in sight.

```php
use Middag\Framework\Http\Controller\AbstractController;
use Middag\Framework\Http\Inertia\InertiaAdapter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CourseController extends AbstractController
{
    #[Route('/courses', name: 'courses')]
    public function index(CourseCatalog $catalog): Response
    {
        // CourseCatalog is your own domain service, injected by the container.
        // No $DB, no $CFG, no host — just your code, described in PHP.
        return InertiaAdapter::render('Courses/Index', [
            'courses' => $catalog->published(),
        ]);
    }
}
```

`CourseCatalog` is your service and `index()` stays plain PHP, so the exact same controller runs standalone, inside Moodle, or inside WordPress, by swapping the adapter.

And the proof of decoupling: the query layer runs with no host at all, over a plain PDO connection.

```php
use Middag\Framework\Database\PdoConnectionAdapter;
use Middag\Framework\Persistence\Query\QueryBuilder;

$conn = new PdoConnectionAdapter(new PDO('sqlite::memory:')); // or a mysql:/pgsql: DSN

// The QueryBuilder is immutable. ::on() binds a connection, so the terminals execute.
$courses = QueryBuilder::on($conn, 'courses')
    ->where('published', true)   // equality shortcut
    ->orderBy('title')
    ->get();                     // array<int, array<string, mixed>>
```

## What's inside

Generic plumbing, organised by concern (each concern owns its own `Contract/`):

| Concern | What it does |
|---|---|
| **Kernel** | Symfony DI container booted from a `BootstrapInterface`, service auto-discovery, `AbstractModule`, `AbstractFacade`, and a per-instance WordPress-style `HookManager`. |
| **Http** | PSR-15 `HttpKernel`, `StandaloneKernel`, attribute routing (`#[Route]`), declarative auth (`#[Auth]`), per-route middleware (`#[Middleware]`), `AbstractController` / `AbstractApiController`, and `AbstractFormRequest`. |
| **Inertia** | Full Inertia v3 protocol: lazy (`optional`), deferred (`defer`), merge (`merge` / `deepMerge`), partial reloads and asset versioning. |
| **Form** | `AbstractForm`, `FormValidator`, a set of field types (text, select, date, file, and more), and an Inertia renderer. |
| **Persistence** | An immutable `QueryBuilder`, an active-record `Model`, `AbstractRepository` / `AbstractMapper`, and `Page` pagination. |
| **Database** | `ConnectionAdapterInterface` with a default `PdoConnectionAdapter`, a SQL dialect layer, and a schema builder with migrations. |
| **Bus** | A command bus over Symfony Messenger (sync, plus async by transport routing), an `InMemoryTransport`, and a `CommandWorker`. |
| **Logging** | A Monolog-backed `LoggerFactory` per channel, with a `RotatingStreamHandler`. |
| **Shared / Exception** | DTOs, enums and utilities, plus a typed exception hierarchy mapped to HTTP status codes. |

Full API reference, guides and examples live at **[docs.middag.dev](https://docs.middag.dev)**.

## Where it runs

| Target | How |
|---|---|
| **Standalone** | Implement `Middag\Framework\Kernel\Contract\BootstrapInterface`, build the container with `ContainerFactory::build()`, and serve through `StandaloneKernel`. |
| **Moodle** | The OSS [`middag-io/moodle`](https://github.com/middag-io) adapter (`MoodleBootstrap` plus the bridge contracts). |
| **WordPress** | The OSS [`middag-io/wordpress`](https://github.com/middag-io) adapter (in build-out). |

An adapter is a thin layer that implements the bridge contracts (`BootstrapInterface`, `ConfigResolverInterface`, `ConnectionAdapterInterface`, `HostEventBridgeInterface`, `UserContextResolverInterface`, `TranslatorInterface`, `FormRendererInterface`). The framework ships a default OSS implementation of each, so it runs standalone with no adapter at all.

## Open source and MIDDAG

The framework, the Moodle and WordPress adapters, and `middag-io/ui` are open source under Apache-2.0: the generic plumbing, free, forever.

The governed domain infrastructure that is genuinely hard to get right — reliable event delivery, async jobs with retry and audit, an EAV query engine, multi-tenancy, and licensing — is MIDDAG's proprietary product. It is built on top of this OSS and is opt-in, for when a domain outgrows the basics. The framework never imports the proprietary layer; the dependency only ever points downward.

## Professional services

Adopting the framework, building a custom adapter, or migrating a legacy plugin to a testable domain? MIDDAG offers consulting and custom development. Reach us at [email@middag.io](mailto:email@middag.io) or visit [middag.io](https://middag.io) for more.

## Development

```bash
composer test    # PHPUnit
composer check   # PHPStan (level 6) + PHP-CS-Fixer + Rector, all dry-run
composer fix     # auto-fix style and Rector (composer fix:all re-settles formatting afterwards)
```

Git hooks are wired automatically on install; the `commit-msg` hook enforces [Conventional Commits](https://www.conventionalcommits.org/), and releases are cut by [release-please](https://github.com/googleapis/release-please).

## Contributing

See [`CONTRIBUTING.md`](CONTRIBUTING.md) for the workflow, coding standards and quality pipeline. Please also read the [`CODE_OF_CONDUCT.md`](CODE_OF_CONDUCT.md). Found a security issue? Follow [`SECURITY.md`](SECURITY.md).

## License

Licensed under the Apache License, Version 2.0. See [`LICENSE`](LICENSE). Copyright 2026 MIDDAG.
