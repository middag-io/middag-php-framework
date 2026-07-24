---
id: FW-011
title: 'Framework as a Portable Composer Package'
status: accepted
date: 2026-04-11
lang: en
domains: [framework, portability]
deciders: ['PENDING — original decider not recorded during the legacy-vault reconstruction; confirm with Michael Meneses before ratifying']
related: [FW-012]
supersedes: []
superseded_by: null
enforced_by:
  mdgstan: []
  docs: [framework/reference/portability-audit-historical]
decision: 'Extract `framework/` from the `local_middag` host plugin into an independently versioned Composer package, governed by five structural rules — no hard-coded host coupling outside namespace declarations, externally supplied configuration, a dependency boundary that never points at the host plugin, stable namespaces across the move, and DI-injected host identity instead of a global constant — so it can be reused by adapters for other hosts.'
---

# FW-011: Framework as a Portable Composer Package

> [!NOTE]
> **Provenance.** Reconstructed from the `moodle-local_middag` legacy vault (`ADR-927`, decided 2026-04-11). This is an archaeology pass, not a new decision — dates and rationale are historical. This ADR is the single clearest case in the whole reconstruction of a "planned" decision that has since become simple fact: **this repository is the extraction the ADR proposed.**

## Context

`classes/framework/` lived inside the `local_middag` Moodle plugin, but nothing about its own logic was Moodle-specific by design — the goal was to extract it as an independent Composer package (`middag/framework` at the time) so it could be reused outside that one plugin, versioned on its own, and depended on by adapters for other hosts.

## Considered Options

1. **Leave `framework/` embedded inside `local_middag`, never extracted** — rejected; the whole point of this decision was reuse outside the one plugin, independent versioning, and adapters for other hosts, none of which an embedded module can offer.
2. **Rely on informal/manual discipline for the plugin boundary** (code review convention only, no structurally enforced dependency rule) — rejected in favor of a Deptrac-enforced boundary (rule 3), because manual discipline doesn't scale to a package meant to be depended on by multiple independent hosts.
3. **Hard-code plugin-specific configuration and host identity directly in `framework/`** (naming prefixes, capability prefixes, a global constant carrying the host's identity) — rejected in favor of configuration and identity flowing in from the outside via argument/provider (rules 2 and 5).
4. **Rename/restructure namespaces freely during the extraction** — rejected in favor of keeping namespaces semantically stable across the move (rule 4), to minimize churn for any code already depending on the pre-extraction paths.

## Decision

Five rules governed the extraction: (1) zero hard-coded references to the host plugin anywhere in `framework/` except in `namespace`/`use` statements; (2) plugin-specific configuration (naming prefixes, capability prefixes) flows in from the outside, never hard-coded; (3) a Deptrac-enforced dependency boundary — `framework/` may depend on Symfony components, PSR interfaces, PHP stdlib and its own `shared`/`contract` layers, and **never** on the plugin's extensions, `base/`, or `base/facade/`; (4) namespaces stay semantically stable across the extraction (`local_middag\framework\contract\*` → a plugin-neutral equivalent); (5) `kernel`/`container_factory` receive the plugin's identity via an argument or provider, never a global constant.

## Consequences

- **This decision is fully realized, not aspirational.** `middag-php-framework` — this very repository — is the package ADR-927 described extracting. Rule 4's target namespace guess (`middag\framework\*`) did not land exactly as written; the real namespace is `Middag\Framework\*` (PSR-4, PascalCase vendor and package, see `composer.json` `autoload.psr-4`) — a naming detail, not a design deviation.
- Rule 3 (the Deptrac boundary) is now the ecosystem's formal four-pillar hexagonal architecture (`architecture.md` §1): `core (proprietary) → framework (OSS) → ui (OSS)`, dependency only ever pointing downward, backed by an explicit "pre-PR boundary checklist" (`architecture.md` §10) — a stronger, better-documented descendant of the original rule, though `architecture.md` itself notes the boundary is not yet linted by an automated tool in this repo (deptrac lives in `Product`/`Core`, not here).
- Rule 2 (external configuration) is now the `ConfigResolverInterface`/`BootstrapInterface` bridge-contract pattern (`architecture.md` §5) — a cleaner generalization of the original YAML/`license_config` idea, expressed as a contract rather than a config-file format.
- Rule 5 (configurable bootstrap) is realized via `BootstrapInterface::getProjectRoot()`/`platform()`, fed into `ContainerFactory::build()` (`bootstrap.md` §1) — no global constant carries the host's identity.
- **The historical 69% portability audit score and its named violations are moot for this repo today.** `architecture.md`'s own pre-PR checklist item 1 requires "zero `use Moodle\` / `global $DB` ... in framework and ui" — a structural guarantee the legacy audit could only aspire to. Whether the specific files the audit flagged were fixed in place or the package was assembled fresh was not established in this pass; either way, the constraint the audit was measuring is now enforced by construction (no Moodle/WordPress dependency in `composer.json` `require` at all), not by a percentage score. The full score breakdown, named violations, accepted exceptions and blockers are preserved for archaeology in `docs-middag-dev` → [framework/reference/portability-audit-historical](../../../../var/www/docs-middag-dev/docs/framework/reference/portability-audit-historical.md).
- Whether the same portability discipline holds inside the `middag-php-moodle`/`middag-php-wordpress` adapter repos is those repos' own decision record, not this one.

## Enforcement

| Decision clause | Verification | State |
|---|---|---|
| Zero hard-coded host-plugin references outside `namespace`/`use` statements | `bin/portability-scan.php` (host globals, host functions, hard-coded paths, require/include discipline across `src/`) — run via `composer check:portability` | coded |
| Deptrac-enforced dependency boundary — `framework/` never depends on a host namespace | `deptrac.yaml` (`Framework` layer may depend only on Ui / Symfony / Psr / Monolog / Nyholm / Doctrine / League / Sentry; a `Host` layer catches any Moodle/WordPress namespace) — run via `composer check:portability` | coded |
| Plugin-specific configuration flows in from the outside, never hard-coded | `ConfigResolverInterface`/`BootstrapInterface` bridge-contract (`architecture.md` §5) | coded |
| Namespaces stay semantically stable, plugin-neutral | `composer.json` `autoload.psr-4` maps `Middag\\Framework\\` to `src/` | coded |
| Host identity passed via argument/provider, never a global constant | `BootstrapInterface::getProjectRoot()`/`platform()` → `ContainerFactory::build()` (`bootstrap.md` §1) | coded |
| Full historical audit (score, violations, accepted exceptions, blockers) preserved for archaeology | [framework/reference/portability-audit-historical](../../../../var/www/docs-middag-dev/docs/framework/reference/portability-audit-historical.md) | coded |

### Acceptance floor (architect decision, 2026-07-24)

**Floor = zero violations, on both axes, as a hard gate.** `composer check:portability`
fails the build on any single violation — a `Framework → Host` namespace edge (deptrac),
or a host global / host function / hard-coded absolute path / `require`-`include` in `src/`
(`bin/portability-scan.php`). No percentage score, no exceptions allowlist: the legacy 69%
metric is retired in favour of a binary "enforced by construction" gate.

Re-measurement against the current PSR-4 tree (2026-07-24): **0 violations** — deptrac 945
allowed edges / 0 uncovered / 0 violations; scan 250 files / 0 violations. None of the stale
legacy-audit violations survive the move to PSR-4.
