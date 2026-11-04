---
title: 'Own Active Record and Data-Mapper'
status: accepted
date: 2026-03-29
last-updated: 2026-11-04
lang: en
domains: [framework, persistence]
deciders: [leadership]
decision: 'Own Active Record, immutable query builder and Data-Mapper repositories; domain reaches storage only via repositories.'
related: [FW-012, FW-016, FW-017]
supersedes: []
superseded_by: null
enforced_by:
  mdgstan: []
  docs: []
---

# FW-013: Own Active Record and Data-Mapper

## Context

Domain code needs one path to storage that keeps schema details out of the domain. The persistence layer has to run over whatever connection the host provides (FW-017), with no heavy ORM and no host coupling.

## Considered Options

1. Eloquent (illuminate/database) as the ORM.
2. Doctrine ORM.
3. Active Record only, with domain entities extending the model.
4. The framework's own host-agnostic Active Record, an immutable query builder and Data-Mapper repositories over the connection seam. The domain reaches storage only through repositories, and a transaction writes one aggregate. ← chosen

## Decision

The framework has its own persistence layer: an Active Record model for fast writes inside infrastructure, an immutable query builder, and Data-Mapper repositories that hydrate plain domain objects. All of it runs on the connection seam. Domain code reaches storage only through repositories, and no domain entity extends the Active Record model. A transaction writes one aggregate. Caching wraps a repository as a decorator and is never part of the repository itself. The Eloquent-compatible surface and its differences live in the reference.

## Consequences

- (+) No ORM dependency and no host coupling.
- (+) Developers who know Laravel recognise most of the surface.
- (−) Some behaviour differs from Eloquent: for example, an immutable builder call that is not reassigned does nothing. These traps are documented, not prevented.
- (−) Governed features (model events, global scopes, audited soft delete, the product schema families) live in downstream packages.
- (−) The repository-only rule, the one-aggregate rule and the no-entity-extends-model rule are not checked by any tool.
- (−) The model's connection resolver is static, which is a bounded exception covered by FW-016.

## Enforcement

| Decision clause | Verification | State |
|---|---|---|
| Repository and mapper path | test `tests/Persistence/Repository/AbstractRepositoryTest.php` | coded |
| Immutable query builder | test `tests/Persistence/Query/QueryBuilderTest.php` | coded |
| No domain entity extends the model; one aggregate per transaction | static-analysis rule | planned |
| Eloquent compatibility matrix | doc `docs-middag-dev:docs/framework/reference/active-record-eloquent-compatibility.md` | coded |
| Persistence families and anti-patterns | doc `docs-middag-dev:docs/framework/reference/repository-persistence-families.md` | coded |
