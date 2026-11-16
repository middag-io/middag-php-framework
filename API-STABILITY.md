# API Stability

This document defines what is public and supported in `middag-io/framework`,
so downstream packages — the OSS adapters (`middag-io/moodle`,
`middag-io/wordpress`), `middag-io/ui`, and proprietary consumers built on
top — can depend on the framework without guessing.

The support rules of the whole library family (lifecycle, what each release
may carry, the previous-MAJOR line and the compatibility matrix of each MAJOR)
live on
[Versioning and compatibility](https://docs.middag.dev/libraries/reference/versioning-and-compatibility).
This file lists the framework's public surface and points there for the rest.

## Stability levels

Every type carries a class-level annotation that states its stability:

| Annotation | Meaning |
|---|---|
| `@api` | **Public, supported surface.** You may implement, extend, type-hint, instantiate and catch these. Changes follow the versioning rules below. |
| `@internal` | **Implementation detail.** May change or be removed in any release, including patches. Do not depend on these from outside the framework. |

If a type has neither annotation, treat it as `@internal`.

## What the support covers

- **Covered:** the classes, interfaces, enums, attributes and functions tagged
  `@api` — the interfaces under each concern's `Contract/` sub-namespace, plus
  the exported `@api` classes (value objects, OSS default implementations,
  base classes).
- **Not covered:** anything tagged `@internal` or untagged, private and
  protected members of `@internal` types, test helpers, and the layout of
  `src/` beyond the `@api` types. These may change in any release.
- **Not shipped:** MIDDAG domain base classes and utility bags.
  `Persistence\Contract\EntityInterface` is the generic contract here;
  `AbstractEntity`, `AbstractValueObject`, MIDDAG formatting helpers and
  validators belong to `middag-io/core`. Legacy/speculative
  `abstract_service`, faker helpers and generic validator packages are not
  supported framework API.

If you need behaviour exposed only by an `@internal` symbol, open an issue to
have it promoted rather than reaching in. Promoting a symbol to `@api` is an
additive change for a family MINOR.

## Lifecycle

- **`2.0.0` is the first supported release.** Support starts there.
- **The `1.x` line is pre-stable and unsupported.** It carries no
  compatibility promise, no deprecation cycle, no backports and no security
  fixes. A breaking change may ship in a `1.x` minor.
- **The cut to `2.0.0` is clean.** There is no upgrade path from `1.x`. The
  `CHANGELOG` continues across the cut.

## Versioning from `2.0.0`

The framework belongs to the **MIDDAG PHP library version family**. Every
library of the family shares MAJOR.MINOR; PATCH is free per library.

| Part | May carry |
|---|---|
| PATCH | Fixes only, per library. Never a change to the `@api` surface. |
| MINOR | Additive `@api` changes (new contracts, new optional parameters, promoted symbols), released as a family MINOR in every library. Never a break. |
| MAJOR | Breaking `@api` changes, released as a family MAJOR. A removal is first deprecated in a MINOR, with a runtime notice. |

### The family rule

From `2.0.0`, **every library of the family is required with the same
`^2.N`** — the same caret range, with the same `N`, for every `middag-io/*`
library you depend on. Minor upgrades inside that range do not break.

## How releases are cut

Releases are cut **exclusively** by
[release-please](https://github.com/googleapis/release-please) from
[Conventional Commits](https://www.conventionalcommits.org/). There are no
manual tags. A family MINOR or MAJOR is a coordinated `Release-As: X.Y.0`
commit on `main` of every library of the family; the product owner decides
each one.

## The family window check

Every pull request runs the **Version family** workflow (status check
`php-version-family-check / check`). It reads the PR title and every non-merge
commit of the PR (the header type and any `BREAKING CHANGE:` /
`BREAKING-CHANGE:` footer) against the organization variable
`PHP_LIBS_OPEN_RELEASE`, which is set and cleared on the order of the product
owner:

| `PHP_LIBS_OPEN_RELEASE` | Window |
|---|---|
| empty | closed |
| `X.Y` with `Y > 0` | family minor window |
| `X.0` | family major window (the `2.0.0` cut is window `2.0`) |

- `!` before the colon, or a `BREAKING CHANGE:` footer, fails unless a family
  major window is open.
- `feat` fails unless a window is open, but only once the version in
  `.release-please-manifest.json` on the base branch is `2.0.0` or later.
- A `Release-As:` footer fails unless a window is open and the footer names
  `X.Y.0` for that window.

To pass, give each commit the type that matches the change; do not relabel a
feature as `fix:` or `refactor:`. A feature PR that waits for a window stays
open and is kept current by **merging `main` into it** — never by rebase or
force-push. When the window opens, re-run the check.

- A window counts as open only when it is ahead of this library's current
  version and the PR targets `main`; a value left set after its family release
  reads as closed.
- While a window is open, this library's Release PR is merged only at the
  coordinated `X.Y.0`.
- A re-run reads the base of the original event. After the `2.0.0` cut, a PR
  opened before the cut must merge `main` into it so that the check reads the
  new version.

## Public surface consumed downstream

The following eleven symbols were promoted from `@internal` to `@api` because
`middag-io/core` consumes them structurally (implements / extends / instantiates
/ throws) or by type-reference. They are part of the supported public API and
evolve under the versioning rules above.

Structural coupling (implemented, extended, instantiated, or thrown):

- `Middag\Framework\Kernel\Contract\ServiceLoaderInterface`
- `Middag\Framework\Kernel\Contract\LoaderInterface`
- `Middag\Framework\Kernel\Contract\ModuleLoaderInterface`
- `Middag\Framework\Kernel\Contract\LoaderFailurePolicyInterface`
- `Middag\Framework\Kernel\Contract\FailedModuleRegistryInterface`
- `Middag\Framework\Kernel\Manager\HookManager`

Type-reference coupling (imported / type-hinted):

- `Middag\Framework\Shared\Util\Typing`
- `Middag\Framework\Logging\Enum\LogLevel`
- `Middag\Framework\Kernel\Contract\KernelInterface`
- `Middag\Framework\Kernel\Contract\FacadeLoaderInterface`
- `Middag\Framework\Kernel\Contract\HookRegisterInterface`

> `KernelInterface` was previously `@internal` on purpose — it pins the kernel
> lifecycle spec. It is promoted to `@api` by explicit decision so adapters and
> consumers can rely on the lifecycle contract.

## Host-neutral bridge contracts

These `@api` contracts are the host-neutral seams that downstream code binds
to. They follow the same versioning rules as the rest of the `@api` surface.

| Contract | Role |
|---|---|
| `Middag\Framework\Kernel\Contract\HostComponentContextInterface` | Neutral runtime context a host exposes to the adapters (component name, asset version, base path). Registered once at boot via `HostContext`. |
| `Middag\Framework\Kernel\Contract\HostEventBridgeInterface` | Public OSS seam for synchronous, in-process broadcast of named host events. Adapters implement it over the host's native eventing. |
| `Middag\Framework\Kernel\Contract\ComponentNameResolverInterface` | Resolves the native component identifier (Moodle frankenstyle / WordPress plugin slug) that owns the running boot cycle. |
| `Middag\Framework\Bus\Contract\SignalDispatcherInterface` | Canonical publish-side entry point for dispatching signals. No OSS implementation ships here; it is fulfilled by `middag-io/core` or a host adapter. |

## Depending on the framework safely

- Depend only on `@api` types.
- From `2.0.0`, require the framework and every other `middag-io/*` library
  of the family with the same `^2.N` range.
- The dependency direction only ever points downward: the framework never
  imports the proprietary layer, and OSS adapters never import the
  proprietary core namespace.
