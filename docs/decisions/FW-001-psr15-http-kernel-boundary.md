---
title: 'Build on Symfony and PSR; public contracts are PSR or framework-owned'
status: accepted
date: 2026-05-20
last-updated: 2026-11-04
lang: en
domains: [framework, http]
deciders: [leadership]
decision: 'Symfony, PSR and Monolog stay inside; public boundaries expose only PSR types and framework-owned types.'
related: [FW-002, FW-011]
supersedes: []
superseded_by: null
enforced_by:
  mdgstan: []
  docs: [docs/architecture.md]
---

# FW-001: Build on Symfony and PSR; public contracts are PSR or framework-owned

## Context

The framework needs HTTP handling, dependency injection, a message bus and logging. Writing them from scratch adds code to maintain and bugs to fix. Reusing a vendor library directly can also leak that vendor's types into every consumer's domain code, so a consumer ends up coupled to the vendor.

## Considered Options

1. Write the HTTP kernel, container, bus and logger from scratch.
2. Expose the vendor types directly: Symfony HttpKernel/HttpFoundation as the public kernel contract, and Symfony attributes and transports imported by consumer code.
3. Build on Symfony, PSR and Monolog internally, and expose at the public boundaries only PSR types (PSR-15/PSR-7 for HTTP) and framework-owned types. Where a consumer would otherwise import a vendor type, the framework ships its own shadow type that reuses the vendor machinery. ← chosen

## Decision

Symfony, PSR and Monolog are implementation details of the framework. The public HTTP boundary is PSR-15 over PSR-7 messages. Where consumer code would otherwise import a vendor type, the framework defines its own type and keeps the vendor machinery underneath it. Examples are the framework's own lazy-service marker and its Doctrine messenger transport, which is a `final` subclass with an empty body. The list of shadows and the bridge mechanics live in the reference.

## Consequences

- (+) Proven libraries do the heavy lifting, so the framework carries less code of its own.
- (+) A consumer's domain and its adapters compile against PSR and framework types, not against a vendor's object model.
- (−) The framework carries a large vendor dependency set. Being host-agnostic does not make it vendor-free.
- (−) The PSR boundary covers the kernel's entry and exit. Controller code still uses Symfony's routing attribute and HttpFoundation responses.
- (−) Each shadow type is one more type that must be kept aligned with its vendor original.

## Enforcement

| Decision clause | Verification | State |
|---|---|---|
| The public kernel contract extends the PSR-15 request handler | test `tests/Kernel/HttpKernelTest.php` | coded |
| The framework depends only on ui, Symfony, PSR, Monolog and a fixed set of vendors | deptrac `deptrac.yaml` with `--fail-on-uncovered`, run by `composer check:portability` | coded |
| Shadow types, the PSR-7/HttpFoundation bridge and the middleware catalog | doc `docs-middag-dev:docs/framework/reference/psr15-bridge-and-middleware.md` | coded |
| Vendor dependency set and its rationale | doc `docs/architecture.md` §2 | coded |
