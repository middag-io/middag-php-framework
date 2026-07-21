---
id: FW-013
title: 'Repository Boundary & Persistence Model'
status: accepted
date: 2026-03-29
lang: en
domains: [framework, persistence]
deciders: ['PENDING — original decider not recorded during the legacy-vault reconstruction; confirm with Michael Meneses before ratifying']
related: [FW-012]
supersedes: []
superseded_by: null
enforced_by:
  mdgstan: []
  docs: [framework/reference/repository-persistence-families]
decision: 'The repository (and its dedicated interface) is the sole boundary to storage for this framework, discovered via the `_repository` suffix convention; an Active Record path (`Model`) and a Data-Mapper path (`Repository`) coexist by design, no domain entity ever extends `Model`, and caching is always a decorator around a repository, never a responsibility the repository takes on itself — the concrete five-family schema catalog is a `middag-io/core` concern, not this framework repo.'
---

# FW-013: Repository Boundary & Persistence Model

> [!NOTE]
> **Provenance.** Reconstructed from the `moodle-local_middag` legacy vault (`ADR-503`, decided 2026-03-29) — specifically the **generic** part of that ADR, the repository mechanism reapplicable to any entity. The legacy synthesis this reconstruction draws from explicitly split ADR-503 in two: this document covers the reapplicable mechanism; the concrete application to the product's own Core Capabilities schema is a `middag-io/core`-facing concern, out of scope here by that same original split.

## Context

Domain code needs one boundary to storage that hides the concrete schema behind it, so a schema detail never leaks out as part of a public API and storage access never scatters across services, controllers, and extensions.

## Considered Options

1. **Active-Record-only** — every domain entity extends `Model` directly, with no separate repository/mapper layer. Rejected: it would leak schema shape into domain code and blur the very boundary this decision exists to enforce.
2. **Data-Mapper-only** — no Active-Record path at all, every write routed through the full `Repository` → `Mapper` chain. Rejected: it discards a cheap path for fast, infrastructure-level writes where full domain mapping buys nothing.
3. **Two persistence paths coexisting by design** ← chosen: Active Record (`Model`) for fast infrastructure-level writes, Data-Mapper (`Repository` → a plain domain object) for a clean domain, with no domain entity ever extending `Model`.
4. Repository implementations own their own caching internally. Rejected: conflates two responsibilities and forces every repository implementation to reimplement its own cache-invalidation logic.
5. **Cache as a decorator wrapped around a repository** ← chosen: caching stays a cross-cutting concern, independent of any single repository implementation.
6. Controllers/services call the query builder directly for ad hoc reads, bypassing the repository. Rejected: reopens the "which layer is allowed to touch storage today" ambiguity the repository boundary exists to close.
7. Ship the concrete five-family architectural catalog (current-state/revision EAV, audit trail, job governance, activity feed) as part of this generic framework layer. Rejected/relocated: kept as a `middag-io/core` concern; only the generic mechanism a family would be built on top of ships here.

## Decision

The repository is the framework's sole access path to storage (closing off option 6): a repository and its interface belong to the aggregate they serve, and the container discovers repositories by the `_repository` suffix convention (see [FW-012](./FW-012-kernel-boot-lifecycle.md)). Two persistence paths coexist by design at the framework level (option 3, over the single-path alternatives 1/2): an Active Record path (`Model`) for fast infrastructure-level writes, and a Data-Mapper path (`Repository` → a plain domain object) for a clean domain — no domain entity ever extends `Model`. A cache is a decorator wrapped around a repository (option 5, over option 4), never a responsibility the repository itself takes on. The concrete five-family schema catalog (option 7) is not this framework's concern — only the generic mechanism any of those families would be built on top of ships here.

## Consequences

- One boundary for all storage access removes an entire class of "which layer is allowed to touch `$DB` today" ambiguity.
- **Already the real implementation.** `Persistence/Repository/AbstractRepository.php`, `Persistence/Mapper/AbstractMapper.php`, the immutable `Persistence/Query/QueryBuilder.php`, `Persistence/Model.php` and `Persistence/Query/Page.php` implement exactly this two-path design — see `architecture.md` §6 for the full Active-Record/Eloquent-compatibility detail, which this ADR does not restate.
- **The legacy "five families" catalog is now entirely a `middag-io/core` concern, not this framework's.** ADR-503 originally named five architectural families — current-state EAV, revision-history EAV, relational audit trail, relational job governance, relational activity feed. None of that concrete schema, nor an EAV query engine, ships in this OSS repo: `architecture.md`'s pillar map places "EAV, ... audited jobs, multi-tenant" in Pillar 3 (proprietary core). What this framework ships instead is the **generic mechanism** any of those five families would be built on top of — a repository contract, a mapper, an immutable query builder, and an entity-type registry — confirming that the original split (generic mechanism here, product-specific application in a core-facing document) was the right one.
- **A generalized entity-typing seed survives in the OSS layer.** `Persistence/Attribute/EntityType.php` plus `Persistence/Entity/{DefaultEntityType,EntityTypeRegistry}.php` and `Persistence/Loader/EntityTypeRegistrar.php` read as a host/product-agnostic descendant of the legacy `#[item_type]`/`type_loader` polymorphism mechanism (see FW-012 and its extracted doc `framework/reference/kernel-boot-lifecycle` for the discovery table) — narrowed to a generic "an entity can declare its own type" concept, with no EAV storage assumption baked in.
- The cache-decorator rule this ADR states is consistent with a separate, older legacy decision (ADR-303, cache decoration mechanics) that belongs to a different host-facing document entirely — not restated or re-decided here.
- Historical detail — the legacy five-family catalog, the per-family base-class table, a citation-error note worth preserving, and the anti-pattern list once carried by a companion REF file — now lives as a standalone reference doc rather than an ADR appendix: `framework/reference/repository-persistence-families` (docs-middag-dev); see Enforcement below.

## Enforcement

| Decision clause | Verification | State |
|---|---|---|
| Repository (and its dedicated interface) is the sole access path to storage; discovered by the `_repository` suffix convention | `tests/Persistence/Repository/AbstractRepositoryTest.php` | coded |
| Active Record (`Model`) and Data-Mapper (`Repository`) coexist as two distinct paths; no domain entity ever extends `Model` | no automated check yet (manual code review only) | planned |
| Cache is a decorator around a repository, never a responsibility the repository itself takes on | no cache-decorator implementation ships in this OSS repo — a host-adapter concern | planned |
| Legacy five-family catalog, per-family base-class table, citation-error note and anti-pattern list | `framework/reference/repository-persistence-families` (docs-middag-dev) | coded (docs) |
