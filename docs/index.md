# middag-io/framework

A platform-agnostic PHP framework for building business domains that do not depend on their host.
Write controllers, forms, queries and services against contracts, then run the same code standalone,
inside a Moodle plugin, or inside a WordPress plugin, by swapping a thin adapter.

> Your domain should not know, or care, where it runs.

Apache-2.0. On the `1.x` line the API is still consolidating: a minor release may carry a
documented breaking change — see
[`API-STABILITY.md`](https://github.com/middag-io/middag-php-framework/blob/main/API-STABILITY.md).

## Technical documentation

- **[Architecture](architecture.md)** — the four pillars and the OSS × proprietary boundary, the
  concern-first 3-tier layout, the bridge contracts, the Active-Record / Eloquent compatibility
  matrix, and the known OSS boundaries. Start here.
- **[Bootstrap](bootstrap.md)** — the boot phases, what the kernel provides at boot, the bridge
  contracts an adapter implements, and the concrete boot flow.

## Decision records

Why the framework is shaped the way it is. Each record holds one decision; the specification lives
in the reference docs its Enforcement table points to.

| Id | Decision |
|----|----------|
| [FW-001](decisions/FW-001-psr15-http-kernel-boundary.md) | Build on Symfony and PSR; public contracts are PSR or framework-owned |
| [FW-002](decisions/FW-002-attribute-routing-controller-auth-model.md) | Declarative routing and authorization |
| [FW-003](decisions/FW-003-schema-first-forms-form-request-escalation.md) | Validation declared as data |
| [FW-004](decisions/FW-004-form-renderer-adapters-field-type-dsl.md) | The renderer seam lives in ui; host widgets only in adapters |
| [FW-005](decisions/FW-005-multi-mode-rendering-model.md) | Multi-mode rendering, no template engine |
| [FW-006](decisions/FW-006-inertia-spa-bridge.md) | Inertia server side inside the framework |
| [FW-007](decisions/FW-007-signal-hook-reactive-model.md) | The OSS ships only the generic reactive seam |
| [FW-008](decisions/FW-008-command-bus-async-job-model.md) | One command bus over Messenger; sync or async decided by routing |
| [FW-009](decisions/FW-009-shortcode-processing.md) | Output sanitized by default; opt out with `#[TrustedOutput]` |
| [FW-010](decisions/FW-010-typed-exception-hierarchy.md) | One framework exception family that carries the HTTP status |
| [FW-011](decisions/FW-011-framework-composer-portability.md) | Why a separate FRAMEWORK package exists |
| [FW-012](decisions/FW-012-kernel-boot-lifecycle.md) | Compiled container as the only resolver, frozen before modules boot |
| [FW-013](decisions/FW-013-repository-persistence-boundary.md) | Own Active Record and Data-Mapper |
| [FW-014](decisions/FW-014-segregated-controller-role-interfaces.md) | Segregated controller role interfaces |
| [FW-015](decisions/FW-015-hosts-are-adapters-of-framework-ports.md) | Hosts are ADAPTERS of framework ports |
| [FW-016](decisions/FW-016-statics-as-a-bounded-exception.md) | Static state as a bounded exception |
| [FW-017](decisions/FW-017-database-abstraction.md) | Database abstraction |

## Quick start

Requires PHP `^8.2`. Install via Composer:

```bash
composer require middag-io/framework
```

A controller that loads your domain and renders an Inertia page — attribute routing, a domain
service injected by the container, no host API in sight:

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
        return InertiaAdapter::render('Courses/Index', [
            'courses' => $catalog->published(),
        ]);
    }
}
```

The same controller runs standalone, inside Moodle, or inside WordPress, by swapping the adapter.

## Where it runs

| Target | How |
|--------|-----|
| **Standalone** | Implement `BootstrapInterface`, build the container with `ContainerFactory::build()`, and serve through `StandaloneKernel`. |
| **Moodle** | The OSS `middag-io/moodle` adapter (`MoodleBootstrap` plus the bridge contracts). |
| **WordPress** | The OSS `middag-io/wordpress` adapter (in build-out). |

## Open source and MIDDAG

The framework, the Moodle and WordPress adapters, and `middag-io/ui` are open source under
Apache-2.0: the generic plumbing, free, forever. The governed domain infrastructure that is genuinely
hard to get right — reliable event delivery, async jobs with retry and audit, an EAV query engine,
multi-tenancy, and licensing — is MIDDAG's proprietary product, built on top of this OSS and opt-in.
The framework never imports the proprietary layer; the dependency only ever points downward.

## Contributing

See [`CONTRIBUTING.md`](../CONTRIBUTING.md) for the workflow, coding standards and quality gates.
