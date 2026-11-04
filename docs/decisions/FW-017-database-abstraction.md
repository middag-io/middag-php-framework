---
title: 'Database abstraction'
status: accepted
date: 2026-11-04
last-updated: 2026-11-04
lang: en
domains: [framework, database]
deciders: [leadership]
decision: 'One connection seam over the host connection, pluggable dialects, attribute-described schema, forward-only migrations.'
related: [FW-013, FW-015, FW-008]
supersedes: []
superseded_by: null
enforced_by:
  mdgstan: []
  docs: []
---

# FW-017: Database abstraction

## Context

Persistence and the durable bus have to run over Moodle's database API, WordPress's database API and plain PDO. Inside a host, tables must be created by the host's own schema tooling, so that backup, restore and uninstall see them. A standalone host has no such tooling.

## Considered Options

1. Always open a PDO or DBAL connection, even inside a host.
2. Call each host's database API directly from the persistence layer.
3. Doctrine DBAL as the required abstraction.
4. One connection seam that each adapter implements over the host's own connection, pluggable SQL dialects, schema described with attributes and turned into DDL by host tooling or a standalone builder, and forward-only migrations. ← chosen

## Decision

All SQL goes through one connection seam (`ConnectionAdapterInterface`). Each adapter implements it over its host's existing connection, and the OSS default is PDO. Differences between SQL engines, including table prefixes, sit behind a pluggable dialect. Tables are described with attributes on PHP classes. Host tooling, or a standalone schema builder, turns those descriptions into DDL. Migrations only move forward: there is no down step.

## Consequences

- (+) Inside a host, the process uses one database connection.
- (+) Host backup, restore and uninstall see the tables when host tooling creates them.
- (+) One schema description serves every host.
- (−) A rollback is a new forward migration.
- (−) Each SQL engine needs its own dialect and schema builder. Coverage is limited to the ones that exist.
- (−) Building schema standalone through DBAL requires an optional dependency.

## Enforcement

| Decision clause | Verification | State |
|---|---|---|
| PDO default for the connection seam | test `tests/Database/PdoConnectionAdapterTest.php` | coded |
| Pluggable dialects, including prefixes | tests `tests/Database/StandardSqlDialectTest.php`, `tests/Database/MysqlSqlDialectTest.php`, `tests/Database/PrefixingSqlDialectTest.php` | coded |
| Attribute-described schema | test `tests/Database/Schema/SchemaAttributeReaderTest.php` | coded |
| Forward-only migrations | test `tests/Database/Schema/MigrationRunnerTest.php` | coded |
| Connection, dialect, schema and migration reference | doc `docs-middag-dev:docs/framework/reference/database-schema-and-migrations.md` | planned |
