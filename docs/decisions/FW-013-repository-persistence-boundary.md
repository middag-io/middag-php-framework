---
id: FW-013
title: 'Repository Boundary & Persistence Model'
status: accepted
date: 2026-03-29
domains: [framework, persistence]
related: [FW-012]
supersedes: []
superseded_by: null
lang: en
---

# FW-013: Repository Boundary & Persistence Model

> [!NOTE]
> **Provenance.** Reconstructed from the `moodle-local_middag` legacy vault (`ADR-503`, decided 2026-03-29) — specifically the **generic** part of that ADR, the repository mechanism reapplicable to any entity. The legacy synthesis this reconstruction draws from explicitly split ADR-503 in two: this document covers the reapplicable mechanism; the concrete application to the product's own Core Capabilities schema is a `middag-io/core`-facing concern, out of scope here by that same original split.

## Context

Domain code needs one boundary to storage that hides the concrete schema behind it, so a schema detail never leaks out as part of a public API and storage access never scatters across services, controllers, and extensions.

## Decision

The repository is the framework's sole access path to storage: a repository and its interface belong to the aggregate they serve; a cache is a decorator wrapped around a repository, never a responsibility the repository itself takes on; the container discovers repositories by the `_repository` suffix convention (see FW-012). Two persistence paths coexist by design at the framework level: an Active Record path (`Model`) for fast infrastructure-level writes, and a Data-Mapper path (`Repository` → a plain domain object) for a clean domain — no domain entity ever extends `Model`, and no controller ever calls the query builder directly; that is infrastructure's job alone.

## Consequences

- One boundary for all storage access removes an entire class of "which layer is allowed to touch `$DB` today" ambiguity.
- **Already the real implementation.** `Persistence/Repository/AbstractRepository.php`, `Persistence/Mapper/AbstractMapper.php`, the immutable `Persistence/Query/QueryBuilder.php`, `Persistence/Model.php` and `Persistence/Query/Page.php` implement exactly this two-path design — see `architecture.md` §6 for the full Active-Record/Eloquent-compatibility detail, which this ADR does not restate.
- **The legacy "five families" catalog is now entirely a `middag-io/core` concern, not this framework's.** ADR-503 originally named five architectural families — current-state EAV, revision-history EAV, relational audit trail, relational job governance, relational activity feed. None of that concrete schema, nor an EAV query engine, ships in this OSS repo: `architecture.md`'s pillar map places "EAV, ... audited jobs, multi-tenant" in Pillar 3 (proprietary core). What this framework ships instead is the **generic mechanism** any of those five families would be built on top of — a repository contract, a mapper, an immutable query builder, and an entity-type registry — confirming that the original split (generic mechanism here, product-specific application in a core-facing document) was the right one.
- **A generalized entity-typing seed survives in the OSS layer.** `Persistence/Attribute/EntityType.php` plus `Persistence/Entity/{DefaultEntityType,EntityTypeRegistry}.php` and `Persistence/Loader/EntityTypeRegistrar.php` read as a host/product-agnostic descendant of the legacy `#[item_type]`/`type_loader` polymorphism mechanism (see FW-012/REF-FW-012-01's discovery table) — narrowed to a generic "an entity can declare its own type" concept, with no EAV storage assumption baked in.
- The cache-decorator rule this ADR states is consistent with a separate, older legacy decision (ADR-303, cache decoration mechanics) that belongs to a different host-facing document entirely — not restated or re-decided here.

## Out of scope

- The EAV query engine, audit trail, job governance and activity feed as concrete product schemas — `middag-io/core`'s concern.
- Cache decoration mechanics for a specific host's cache backend — that host adapter's own decisions record.
- The legacy five-families catalog (preserved for historical context only), the per-family base-class table, a citation error found in the legacy ADR, and anti-patterns — see REF-FW-013-01.

## Links

- [REF-FW-013-01 — The Legacy Five-Families Catalog, Base-Class Table, a Citation Error & Anti-Patterns](../ref/REF-FW-013-01-persistence-families-historical.md)
- [FW-012 — Kernel Boot Lifecycle](./FW-012-kernel-boot-lifecycle.md)
- [architecture.md](../architecture.md) — current Active-Record/Data-Mapper implementation
