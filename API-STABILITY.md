# API Stability

This document defines what is public and supported in `middag-io/framework`,
and how the public surface may evolve on the current **`1.x`** line and in the
version family from `2.0.0`, so
downstream packages — the OSS adapters (`middag-io/moodle`,
`middag-io/wordpress`), `middag-io/ui`, and proprietary consumers built on
top — can depend on the framework without guessing.

## Stability levels

Every type carries a class-level annotation that states its stability:

| Annotation | Meaning |
|---|---|
| `@api` | **Public, supported surface.** You may implement, extend, type-hint, instantiate and catch these. Changes follow the versioning policy below. |
| `@internal` | **Implementation detail.** May change or be removed in any release, including patches. Do not depend on these from outside the framework. |

If a type has neither annotation, treat it as `@internal`.

The public surface is therefore the set of `@api`-annotated types: the
interfaces under each concern's `Contract/` sub-namespace, plus the exported
`@api` classes (value objects, OSS default implementations, base classes).

## Scope exclusions

The framework intentionally does not ship MIDDAG domain base classes or utility
bags. `Persistence\Contract\EntityInterface` is the generic contract here;
`AbstractEntity`, `AbstractValueObject`, MIDDAG formatting helpers and validators
belong to `middag-io/core`. Legacy/speculative `abstract_service`, faker helpers
and generic validator packages are not supported framework API.

## How releases are cut

Releases are cut **exclusively** by
[release-please](https://github.com/googleapis/release-please) from
[Conventional Commits](https://www.conventionalcommits.org/). There are no
manual tags.

The framework belongs to the **MIDDAG PHP library version family**:
`middag-php-framework`, `middag-php-core`, `middag-php-ui`,
`middag-php-moodle`, `middag-php-wordpress`, `middag-php-licensing`,
`middag-php-dev-tools` and `middag-php-demo-standalone`. The family is decided
in ADR-032 of `docs-middag-ops`, which amends ADR-003 (release strategy) for
these libraries. The libraries stay on `1.x` until the family crosses `2.0.0`
together; the product owner decides that cut.

## The `1.x` policy

The `1.x` line cuts only patch and minor releases. There is no `1.x` major:
the framework reaches `2.0.0` only with the whole family.

- **Patch** (`1.y.Z`) — bug fixes and `@internal`-only changes. Never a
  breaking `@api` change.
- **Minor** (`1.Y.0`) — cut by release-please from a `feat:` commit: additive
  `@api` changes (new contracts, new optional parameters, promoting an
  `@internal` symbol to `@api`).
- **Breaking `@api` change** — **until 2026-11-30** only, a minor may carry
  one, under a recorded exception that ends on 2026-11-30 and is not renewed.
  It ships as a `feat:` commit, so it cuts a minor and never a patch, and the
  commit body describes the break and the migration. It never ships as `fix:`,
  `refactor:` or `chore:`. It is **never** marked with
  `!` or a `BREAKING CHANGE:` footer: release-please would cut `2.0.0` in this
  library alone, and the [family window check](#the-family-window-check)
  refuses both markers outside a family major window.
- **From 2026-12-01** the exception ends and is not renewed. If the family has
  not reached `2.0.0` by then, no breaking change lands on `1.x` until the cut.
- **Frozen contracts** (below) are exempt: they do not change in a breaking
  way in **any** `1.x` release.

> Historical note: `1.0.2` shipped a breaking `Mail` change as a patch, before
> this policy existed. From this document on, a breaking `@api` change never
> lands in a patch.

## From `2.0.0`: the version family

- **Numbers.** Every library of the family shares MAJOR.MINOR; PATCH is free
  per library. A family minor or major goes out in every library, changed or
  not.
- **Patch** carries fixes only. From the cut, release-please runs with
  `versioning: always-bump-patch`, so no commit type raises the minor or the
  major.
- **Minor** never breaks. A feature waits for the next family minor. A removal
  is first deprecated in a minor, with a runtime notice, and leaves only in the
  next family major.
- **Family minor or major** — a coordinated `Release-As: X.Y.0` commit on
  `main` of every library of the family.
- **Who decides.** The product owner decides each family minor, on one of
  three triggers: a new Moodle version to support; a MIDDAG release that needs
  a feature, a deprecation or a new range; a new Symfony minor to absorb. The
  product owner decides every family major.
- **Compatibility.** Each family major publishes a compatibility matrix
  (Symfony, PHP, Moodle and WordPress ranges). Its home is still to be decided.
- **Previous major.** The last minor of a family major receives security fixes
  only, for 6 months after the next major ships.

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
  `.release-please-manifest.json` on the base branch is `2.0.0` or later. On
  `1.x`, `feat` passes.
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
evolve under the versioning policy above.

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

## Frozen contracts

These four bridge contracts are the host-neutral seams that downstream code
binds to. They are **frozen for the whole `1.x` line**: their method signatures
will not change in a breaking way in any `1.x` release. New optional behaviour
may be added; nothing already present will be renamed, removed, or have its
signature altered. Breaking one of these requires the family `2.0.0`.

| Contract | Role |
|---|---|
| `Middag\Framework\Kernel\Contract\HostComponentContextInterface` | Neutral runtime context a host exposes to the adapters (component name, asset version, base path). Registered once at boot via `HostContext`. |
| `Middag\Framework\Kernel\Contract\HostEventBridgeInterface` | Public OSS seam for synchronous, in-process broadcast of named host events. Adapters implement it over the host's native eventing. |
| `Middag\Framework\Kernel\Contract\ComponentNameResolverInterface` | Resolves the native component identifier (Moodle frankenstyle / WordPress plugin slug) that owns the running boot cycle. |
| `Middag\Framework\Bus\Contract\SignalDispatcherInterface` | Canonical publish-side entry point for dispatching signals. No OSS implementation ships here; it is fulfilled by `middag-io/core` or a host adapter. |

## Depending on the framework safely

- Depend only on `@api` types. If you need behaviour exposed only by an
  `@internal` symbol, open an issue to have it promoted rather than reaching in.
- **On `1.x`:** pin a caret range (`^1.0`) and read the CHANGELOG before
  crossing a minor — until 2026-11-30 a `1.x` minor may carry a breaking `@api`
  change to a non-frozen contract, described in the body of its commit.
- **Zero-surprise upgrades on `1.x`:** pin a tilde patch range (for example
  `~1.0.3`) to receive only patches, and move across minors deliberately.
- **From `2.0.0`:** declare one `^2.N` range, with the same `N`, for every
  `middag-php-*` library you depend on. Minor upgrades inside it do not break.
- The dependency direction only ever points downward: the framework never
  imports the proprietary layer, and OSS adapters never import `Middag\Core\`.
