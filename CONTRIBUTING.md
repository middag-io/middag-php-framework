# Contributing

Thanks for your interest in `middag-io/framework`. This guide covers the
workflow, coding standards, and quality pipeline the project expects.

## Workflow

1. Fork and clone.
2. Create a feature branch off `develop`.
3. Run the full check suite locally before pushing: `composer check && composer test`.
4. Open a pull request against `develop`.

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

- Cover **new behaviour** with a test. Pre-1.0 does not require 100% coverage,
  but every public, testable seam has a test.
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
- Mark a breaking change with `!` (e.g. `feat(kernel)!: …`) or a
  `BREAKING CHANGE:` footer; the hook accepts both.
- **Never** add `Co-Authored-By` trailers.
- The branch base is **`develop`**.

### Versioning (release-please)

`release-please` reads the commit history to compute the next version and the
CHANGELOG, so the commit **type** drives the bump.

While the package is **pre-1.0** (`release-please-config.json` sets
`bump-minor-pre-major` and `bump-patch-for-minor-pre-major`):

| Commit | Bump while `0.x` |
| ------ | ---------------- |
| `fix:` / `feat:` | PATCH |
| breaking (`!` or `BREAKING CHANGE:`) | MINOR |

The public API is allowed to move while `0.x`, so no single commit auto-produces
a MAJOR yet.

**Going to `1.0.0` (and MAJOR releases after):** when the public API is declared
stable, cut `1.0.0` deliberately — land a commit whose footer is
`Release-As: 1.0.0` (or bump `.release-please-manifest.json` directly). From
`1.0.0` onward the pre-major flags become inert and standard semver applies:
`fix:` → PATCH, `feat:` → MINOR, and a breaking change (`!` / `BREAKING CHANGE:`)
→ MAJOR. Remove `bump-minor-pre-major` / `bump-patch-for-minor-pre-major` from
`release-please-config.json` at that point for clarity.

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
