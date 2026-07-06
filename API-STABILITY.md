# API Stability

This document defines what is public and supported in `middag-io/framework`,
and how the public surface may evolve during the current **`1.x`** line, so
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

## How releases are cut

Releases are cut **exclusively** by
[release-please](https://github.com/googleapis/release-please) from
[Conventional Commits](https://www.conventionalcommits.org/). There are no
manual tags: the version is derived from commit types, or set deliberately by
a maintainer with a `Release-As:` footer.

## The `1.x` policy

During the `1.x` line the API is **still consolidating**. Standard semver
applies to patches and additive minors, with one explicit exception for
breaking changes:

- **Patch** (`1.y.Z`) — bug fixes and `@internal`-only changes. Never a
  breaking `@api` change.
- **Minor** (`1.Y.0`) — additive `@api` changes (new contracts, new optional
  parameters, promoting an `@internal` symbol to `@api`). A minor **may also
  carry a breaking `@api` change** while the API consolidates. Every breaking
  change is explicitly marked in the history (`feat!` / a `BREAKING CHANGE:`
  footer) and listed in the CHANGELOG's **⚠ BREAKING CHANGES** section. Such
  releases are always cut deliberately by a maintainer with a `Release-As:`
  footer — never as an accidental side effect of merging.
- **Frozen contracts** (below) are exempt: they do not change in a breaking
  way in **any** `1.x` release.

Full strict-semver rigor — breaking changes **only** in major releases —
starts at `2.0`. A major release is never cut automatically: it happens only
by explicit maintainer decision, when the break genuinely impacts Composer
consumers — a release PR proposing a major bump is not merged without that
sign-off.

> Historical note: `1.0.2` shipped a breaking `Mail` change as a patch, before
> this policy existed. From this document on, a breaking `@api` change never
> lands in a patch.

## Public surface consumed downstream

The following twelve symbols were promoted from `@internal` to `@api` because
`middag-io/core` consumes them structurally (implements / extends / instantiates
/ throws) or by type-reference. They are part of the supported public API and
evolve under the versioning policy above.

Structural coupling (implemented, extended, instantiated, or thrown):

- `Middag\Framework\Kernel\Contract\ServiceLoaderInterface`
- `Middag\Framework\Kernel\Contract\LoaderInterface`
- `Middag\Framework\Kernel\Contract\ModuleLoaderInterface`
- `Middag\Framework\Kernel\Contract\LoaderFailurePolicyInterface`
- `Middag\Framework\Kernel\Contract\FailedModuleRegistryInterface`
- `Middag\Framework\Exception\MiddagLifecycleViolationException`
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
signature altered. Breaking one of these requires `2.0`.

| Contract | Role |
|---|---|
| `Middag\Framework\Kernel\Contract\HostComponentContextInterface` | Neutral runtime context a host exposes to the adapters (component name, asset version, base path). Registered once at boot via `HostContext`. |
| `Middag\Framework\Kernel\Contract\HostEventBridgeInterface` | Public OSS seam for synchronous, in-process broadcast of named host events. Adapters implement it over the host's native eventing. |
| `Middag\Framework\Kernel\Contract\ComponentNameResolverInterface` | Resolves the native component identifier (Moodle frankenstyle / WordPress plugin slug) that owns the running boot cycle. |
| `Middag\Framework\Bus\Contract\SignalDispatcherInterface` | Canonical publish-side entry point for dispatching signals. No OSS implementation ships here; it is fulfilled by `middag-io/core` or a host adapter. |

## Depending on the framework safely

- Depend only on `@api` types. If you need behaviour exposed only by an
  `@internal` symbol, open an issue to have it promoted rather than reaching in.
- **Default:** pin a caret range (`^1.0`) and read the CHANGELOG's
  **⚠ BREAKING CHANGES** section before crossing a minor — a `1.x` minor may
  carry a documented breaking `@api` change to a non-frozen contract.
- **Zero-surprise upgrades:** pin a tilde patch range (for example `~1.0.3`)
  to receive only patches, and move across minors deliberately.
- The dependency direction only ever points downward: the framework never
  imports the proprietary layer, and OSS adapters never import `Middag\Core\`.
