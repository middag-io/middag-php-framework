# Contributing

Thanks for your interest in `middag-io/framework`. This guide covers the
workflow, coding standards, and quality pipeline the project expects.

## Workflow

1. Fork and clone.
2. Create a feature branch off `main`.
3. Run the full check suite locally before pushing: `composer check && composer test`.
4. Open a pull request against `main`.

## Coding standards

### Typing and formatting

- `declare(strict_types=1);` at the top of **every** PHP file, no exceptions.
- The style is **PSR-12 plus the `@PhpCsFixer` ruleset**, enforced by
  PHP-CS-Fixer (`.php-cs-fixer.dist.php`). `camelCase` for methods and
  properties, `PascalCase` for classes.
- **PSR-4**: the namespace mirrors the path — `Middag\Framework\<Concern>\...`.
- Explicit types on every signature (parameters and return). Target PHP `^8.2`;
  use enums, `readonly`, and `final` where they fit.
- Symbols are imported with `use` (the fixer's `global_namespace_import` is on);
  avoid inline FQCN when a `use` already exists.
- The Apache-2.0 license header is applied automatically by PHP-CS-Fixer — run
  `composer fix` and it is added for you.

### Naming by type tier

Each concern owns typed sub-namespaces. Put each symbol where it belongs (see
[Architecture](#architecture)):

| Symbol      | Directory             | Convention                                          |
| ----------- | --------------------- | --------------------------------------------------- |
| Interface   | `<Concern>/Contract/` | mandatory `*Interface` suffix, no implementation    |
| Enum        | `<Concern>/Enum/`     | backed (`: string` / `: int`), immutable            |
| Attribute   | `<Concern>/Attribute/`| `#[Attribute]` class                                |
| DTO / data  | concern root or `Dto/`| dumb data: no business rules, no I/O                |

An interface always ends in `*Interface` and never sits at the concern root.

### Docblocks and the public surface

- `@api` marks the stable public surface: what consumers may call and what the
  generated reference documents. It follows semver.
- `@internal` marks implementation detail that may change without notice.
- A docblock explains the **why** and the non-obvious contracts (immutability,
  ON/OFF modes, behavioural divergences). Do not restate what a typed signature
  already says.
- ASCII diagrams in docblocks are welcome for non-trivial pipelines or state
  machines.

### Tests

- Cover **new behaviour** with a test. The project does not require 100%
  coverage, but every public, testable seam has a test.
- `tests/Unit` is pure (no I/O); `tests/Integration` is DB-aware where
  applicable.
- Documented footguns (for example, the immutable query builder) **must** have a
  test that proves the behaviour.

## Quality gates

Everything green before you push:

```bash
composer check    # PHPStan (level 6) + PHP-CS-Fixer + Rector, all dry-run
composer test     # PHPUnit
composer fix      # auto-fix: PHP-CS-Fixer + Rector
composer fix:all  # style → rector → style (re-settles formatting after Rector)
```

- PHPStan runs at **level 6** with zero new errors. Use `@phpstan-ignore-line`
  only with a comment that justifies it.
- PHP-CS-Fixer and Rector must be clean (the dry-run shows no diff).

## Commit messages and branch

[Conventional Commits](https://www.conventionalcommits.org/):

```
type(scope): short summary

Longer body when the "why" isn't obvious.
```

- Types: `feat`, `fix`, `refactor`, `perf`, `docs`, `style`, `test`, `build`,
  `ci`, `chore`, `revert` — the set `release-please` maps to CHANGELOG sections.
- One scope per commit, lowercase — the `commit-msg` hook rejects
  comma-separated multi-scope subjects.
- Do **not** mark a breaking change with `!` or a `BREAKING CHANGE:` footer
  outside a family major window: the hook accepts both, but the family window
  check refuses them (see [Versioning](#versioning-release-please)). On `1.x`,
  describe a break and its migration in the commit body instead.
- **Never** add `Co-Authored-By` trailers.
- The branch base is **`main`**.

### Versioning (release-please)

Releases are cut **exclusively** by `release-please` — never by a manual tag.
It reads the commit history to compute the next version and the CHANGELOG. The
framework is on the **`1.x`** line and belongs to the MIDDAG PHP library
version family (ADR-032 of `docs-middag-ops`), which crosses `2.0.0` together
when the product owner decides the cut:

| Commit | On `1.x` | From `2.0.0` |
| ------ | -------- | ------------ |
| `fix:` | PATCH | PATCH |
| `feat:` | MINOR | waits for a family MINOR window |
| `!` or `BREAKING CHANGE:` | not allowed | only in a family MAJOR window, after a deprecation in an earlier MINOR |

- **Breaking changes on `1.x`.** Until 2026-11-30, under a recorded exception
  that is not renewed, a break may ship in a minor as a `feat:` commit (so it
  cuts a minor, never a patch) whose body describes the break and the
  migration; never as `fix:`, `refactor:` or `chore:`. From 2026-12-01 no break
  lands on `1.x` until the family `2.0.0`. A breaking change never ships in a
  patch.
- **From `2.0.0`.** Every library of the family shares MAJOR.MINOR. A patch
  carries fixes only. A family minor or major is a coordinated `Release-As`
  in every library, decided by the product owner.
- **Family window check.** Every PR runs `php-version-family-check / check`
  against the organization variable `PHP_LIBS_OPEN_RELEASE` (empty = closed,
  `X.Y` = family minor window, `X.0` = family major window). Give each commit
  the type that matches the change. A feature PR waiting for a window stays
  open and is kept current by merging `main` into it — never by rebase or
  force-push; re-run the check when the window opens. A window counts as open
  only when it is ahead of the current version; a stale value reads as closed.
  While a window is open, merge the Release PR only at the coordinated
  `X.Y.0`. After the `2.0.0` cut, a PR opened before it must merge `main` in
  so that the check reads the new version.

The complete policy, including the frozen contracts and the check rules, lives
in [`API-STABILITY.md`](API-STABILITY.md).

## Pull request checklist

1. `declare(strict_types=1);` in every new file.
2. Interfaces in `<Concern>/Contract/` with the `*Interface` suffix; enums in
   `Enum/`; attributes in `Attribute/`.
3. `@api` / `@internal` marked on the surface you touched.
4. New behaviour covered by a test; documented footguns covered by a test.
5. `composer check && composer test` green.
6. Boundaries respected (see [Architecture](#architecture)): no host APIs, no
   proprietary `core` imports, the UI layer stays agnostic.
7. Conventional Commit, no `Co-Authored-By`, single scope.

## Architecture

The framework is platform-agnostic: host-specific code lives in adapters
(`middag-io/moodle`, `middag-io/wordpress`) or in standalone consumers, never in
the framework itself.

The source tree is **concern-first**: each concern (`Bus/`, `Database/`,
`Persistence/`, `Http/`, `Kernel/`, `Form/`, `Logging/`, …) is a top-level
directory that owns its own `Contract/`. There is no single top-level
`Contract/` or `Infrastructure/` layer. Every host-facing contract ships an OSS
default implementation in-tree, so the framework runs with no adapter at all
(`EnvConfigResolver`, `PdoConnectionAdapter`, `NullUserContextResolver`,
`InertiaRenderer`).

The full technical reference — the concern map, the 3-tier layout rule, the
bridge contracts, the Active-Record / Eloquent compatibility matrix, and the
known OSS boundaries — lives in [`docs/architecture.md`](https://github.com/middag-io/middag-php-framework/blob/main/docs/architecture.md)
and is published at [docs.middag.dev](https://docs.middag.dev).

## Code of conduct

This project follows the [`CODE_OF_CONDUCT.md`](https://github.com/middag-io/middag-php-framework/blob/main/CODE_OF_CONDUCT.md). By
participating you agree to uphold it.

## Security

Found a security issue? Follow [`SECURITY.md`](https://github.com/middag-io/middag-php-framework/blob/main/SECURITY.md). Please do not open a
public issue for vulnerabilities.

## License

By contributing you agree your contribution is released under the Apache License
2.0, the same license as the project.
