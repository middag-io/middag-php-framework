# API Stability

This document defines what is public and supported in `middag-io/framework`,
how the public surface may evolve, and which contracts are **frozen** ahead of
the `1.0` release.

The framework is **alpha** (`0.x`). The policy below makes precise what "alpha"
does and does not allow, so downstream packages — the OSS adapters
(`middag-io/moodle`, `middag-io/wordpress`), `middag-io/ui`, and proprietary
consumers built on top — can depend on the framework without guessing.

## Stability levels

Every type carries a class-level annotation that states its stability:

| Annotation | Meaning |
|---|---|
| `@api` | **Public, supported surface.** You may implement, extend, type-hint, instantiate and catch these. Changes follow the versioning policy below. |
| `@internal` | **Implementation detail.** May change or be removed in any release, including patches. Do not depend on these from outside the framework. |

If a type has neither annotation, treat it as `@internal`.

## Versioning policy

Releases are cut by [release-please](https://github.com/googleapis/release-please)
from [Conventional Commits](https://www.conventionalcommits.org/); the version
is derived from commit types, not chosen by hand.

While the framework is `0.x`:

- **Patch** (`0.y.Z`) — bug fixes and `@internal`-only changes. No `@api`
  signature changes.
- **Minor** (`0.Y.0`) — additive `@api` changes (new contracts, new optional
  parameters, promoting an `@internal` symbol to `@api`). A minor may also carry
  a **breaking** `@api` change while we are pre-`1.0`, but only when it is called
  out explicitly with a `BREAKING CHANGE:` footer and an entry in the changelog.
- **Frozen contracts** (see below) are exempt from that last clause: we commit
  **not** to change them in a breaking way before `1.0`.

Promoting an `@internal` symbol to `@api` is additive and ships in a minor; it
never narrows the surface, so consumers pinned to a compatible range keep
resolving.

## Public surface consumed downstream

The following eleven symbols were promoted from `@internal` to `@api` because
`middag-io/core` consumes them structurally (implements / extends / instantiates
/ throws) or by type-reference. They are now part of the supported public API
and evolve under the versioning policy above.

Structural coupling (implemented, extended, instantiated, or thrown):

- `Middag\Framework\Kernel\Contract\ServiceLoaderInterface`
- `Middag\Framework\Kernel\Contract\LoaderInterface`
- `Middag\Framework\Kernel\Contract\ModuleLoaderInterface`
- `Middag\Framework\Kernel\Contract\LoaderFailurePolicyInterface`
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
binds to. They were introduced or reshaped recently (`HostComponentContextInterface`
in `0.11.2`, `HostEventBridgeInterface` promoted in `0.11.1`), so they are
explicitly **frozen**: their method signatures will **not** change in a breaking
way before `1.0`. New optional behaviour may be added; nothing already present
will be renamed, removed, or have its signature altered.

| Contract | Role |
|---|---|
| `Middag\Framework\Kernel\Contract\HostComponentContextInterface` | Neutral runtime context a host exposes to the adapters (component name, asset version, base path). Registered once at boot via `HostContext`. |
| `Middag\Framework\Kernel\Contract\HostEventBridgeInterface` | Public OSS seam for synchronous, in-process broadcast of named host events. Adapters implement it over the host's native eventing. |
| `Middag\Framework\Kernel\Contract\ComponentNameResolverInterface` | Resolves the native component identifier (Moodle frankenstyle / WordPress plugin slug) that owns the running boot cycle. |
| `Middag\Framework\Bus\Contract\SignalDispatcherInterface` | Canonical publish-side entry point for dispatching signals. No OSS implementation ships here; it is fulfilled by `middag-io/core` or a host adapter. |

## Depending on the framework safely

- Depend only on `@api` types. If you need behaviour exposed only by an
  `@internal` symbol, open an issue to have it promoted rather than reaching in.
- Pin a caret range (`^0.12`) and read the changelog before bumping across a
  minor — a pre-`1.0` minor may carry a documented breaking `@api` change to a
  non-frozen contract.
- The dependency direction only ever points downward: the framework never
  imports the proprietary layer, and OSS adapters never import `Middag\Core\`.
