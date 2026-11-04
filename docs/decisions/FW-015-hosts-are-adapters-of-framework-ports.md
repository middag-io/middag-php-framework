---
title: 'Hosts are ADAPTERS of framework ports'
status: accepted
date: 2026-11-04
last-updated: 2026-11-04
lang: en
domains: [framework, adapters, portability]
deciders: [leadership]
decision: 'Hosts enter only via adapters implementing framework ports; each port has an OSS default and identity is injected.'
related: [FW-011, FW-016, FW-017, FW-014, middag-php-moodle:MDL-001, middag-php-wordpress:WP-002, middag-php-wordpress:WP-003]
supersedes: []
superseded_by: null
enforced_by:
  mdgstan: []
  docs: [docs/architecture.md, docs/bootstrap.md]
---

# FW-015: Hosts are ADAPTERS of framework ports

## Context

The same domain code has to run inside Moodle, inside WordPress and on a plain PHP host. Each host has its own database API, users and capabilities, configuration, translation, mail, file storage, log destination and error reporting. The domain must not see any of them, and more than one product may share a host process. The question is how a host enters the code.

## Considered Options

1. A `framework/moodle/` subtree inside the framework, with a whitelist of host calls it may make.
2. Host calls spread through framework and domain code behind runtime checks.
3. Adapters that also carry product infrastructure and product wiring.
4. Defaults that carry the product's own identity, such as MIDDAG names and prefixes.
5. Hosts enter only through separate adapters that implement framework ports. Each port has an OSS default, so the framework runs standalone. The consumer owns the composition root, and identity is injected at runtime with neutral defaults. ← chosen

## Decision

Every host capability the framework needs is a port: an interface the framework defines. A host enters only through a separate OSS adapter package that implements those ports. The framework has no adapter directory and never names a host namespace. Every port has a working OSS default, so the same code runs standalone with no adapter. The consumer, a product plugin or an application, owns the composition root and decides what to bind. Adapters supply implementations, not product wiring. Component identity (name, version, paths, prefixes) is injected by the consumer at runtime, and framework and adapter defaults stay neutral. Logging and observability are ports like any other.

## Consequences

- (+) Domain code is tested without booting a host.
- (+) Anyone can write an adapter for another host against the same ports.
- (+) A standalone product needs no host at all.
- (−) A new host capability touches three places: the port, its OSS default and each adapter.
- (−) Adapters trail the framework and may pin different minors of it.
- (−) Some host entry points cannot receive injected services, which forces the static exception in FW-016.
- (−) Neutral defaults mean every consumer has to configure its own identity.
- (−) The fallback error-log logger still defaults its channel to `middag` (`src/Logging/ErrorLogFallbackLogger.php:38`).

## Enforcement

| Decision clause | Verification | State |
|---|---|---|
| No adapter directory and no host namespace in `src/` | test `tests/Architecture/NoHostAdapterDirectoryTest.php`; deptrac `Host` layer in `composer check:portability` | coded |
| Every port has an OSS default; port-by-host matrix | doc `docs/architecture.md` §5; doc `docs-middag-dev:docs/framework/reference/bridge-contracts.md` | coded |
| Bootstrap contracts an adapter implements | doc `docs/bootstrap.md`; doc `docs-middag-dev:docs/framework/reference/bootstrap-contracts.md` | coded |
| The framework runs standalone | tests `tests/Runtime/StandaloneKernelTest.php`, `tests/Http/StandaloneKernelTest.php` | coded |
| Standalone runtime reference | doc `docs-middag-dev:docs/framework/reference/standalone-runtime.md` | planned |
| Logging and observability ports | doc `docs-middag-dev:docs/framework/reference/logging-and-observability.md` | planned |
| No product-named default in `src/` | guard test | planned |
