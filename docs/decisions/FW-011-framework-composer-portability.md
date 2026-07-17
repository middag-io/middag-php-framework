---
id: FW-011
title: 'Framework as a Portable Composer Package'
status: accepted
date: 2026-04-11
domains: [framework, portability]
related: [FW-012]
supersedes: []
superseded_by: null
lang: en
---

# FW-011: Framework as a Portable Composer Package

> [!NOTE]
> **Provenance.** Reconstructed from the `moodle-local_middag` legacy vault (`ADR-927`, decided 2026-04-11). This is an archaeology pass, not a new decision — dates and rationale are historical. This ADR is the single clearest case in the whole reconstruction of a "planned" decision that has since become simple fact: **this repository is the extraction the ADR proposed.**

## Context

`classes/framework/` lived inside the `local_middag` Moodle plugin, but nothing about its own logic was Moodle-specific by design — the goal was to extract it as an independent Composer package (`middag/framework` at the time) so it could be reused outside that one plugin, versioned on its own, and depended on by adapters for other hosts.

## Decision

Five rules governed the extraction: (1) zero hard-coded references to the host plugin anywhere in `framework/` except in `namespace`/`use` statements; (2) plugin-specific configuration (naming prefixes, capability prefixes) flows in from the outside, never hard-coded; (3) a Deptrac-enforced dependency boundary — `framework/` may depend on Symfony components, PSR interfaces, PHP stdlib and its own `shared`/`contract` layers, and **never** on the plugin's extensions, `base/`, or `base/facade/`; (4) namespaces stay semantically stable across the extraction (`local_middag\framework\contract\*` → a plugin-neutral equivalent); (5) `kernel`/`container_factory` receive the plugin's identity via an argument or provider, never a global constant.

## Consequences

- **This decision is fully realized, not aspirational.** `middag-php-framework` — this very repository — is the package ADR-927 described extracting. Rule 4's target namespace guess (`middag\framework\*`) did not land exactly as written; the real namespace is `Middag\Framework\*` (PSR-4, PascalCase vendor and package) — a naming detail, not a design deviation.
- Rule 3 (the Deptrac boundary) is now the ecosystem's formal four-pillar hexagonal architecture (`architecture.md` §1): `core (proprietary) → framework (OSS) → ui (OSS)`, dependency only ever pointing downward, enforced by an explicit "pre-PR boundary checklist" (`architecture.md` §10) — a stronger, better-documented descendant of the original rule.
- Rule 2 (external configuration) is now the `ConfigResolverInterface`/`BootstrapInterface` bridge-contract pattern (`architecture.md` §5) — a cleaner generalization of the original YAML/`license_config` idea, expressed as a contract rather than a config-file format.
- Rule 5 (configurable bootstrap) is realized via `BootstrapInterface::getProjectRoot()`/`platform()`, fed into `ContainerFactory::build()` (`bootstrap.md` §1) — no global constant carries the host's identity.
- **The historical 69% portability audit score and its named violations are moot for this repo today.** `architecture.md`'s own pre-PR checklist item 1 requires "zero `use Moodle\` / `global $DB` ... in framework and ui" — a structural guarantee the legacy audit could only aspire to. Whether the specific files the audit flagged were fixed in place or the package was assembled fresh was not established in this pass; either way, the constraint the audit was measuring is now enforced by construction, not by a percentage score. The audit itself is preserved in REF-FW-011-01 as a record of exactly what "not yet portable" looked like, for anyone building a similarly-scoped extraction elsewhere in the ecosystem.

## Out of scope

- Whether the same portability discipline holds inside the `middag-php-moodle`/`middag-php-wordpress` adapter repos — their own decisions record, not this one.
- The historical audit's line-by-line violations and accepted exceptions — preserved for archaeology in REF-FW-011-01.

## Links

- [REF-FW-011-01 — The Historical Portability Audit (Score, Violations, Accepted Exceptions, Blockers)](../ref/REF-FW-011-01-portability-audit-historical.md)
- [FW-012 — Kernel Boot Lifecycle](./FW-012-kernel-boot-lifecycle.md)
- [architecture.md](../architecture.md) — current implementation and the four-pillar boundary
