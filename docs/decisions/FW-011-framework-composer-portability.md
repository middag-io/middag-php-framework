---
title: 'Why a separate FRAMEWORK package exists'
status: accepted
date: 2026-04-11
last-updated: 2026-11-04
lang: en
domains: [framework, portability]
deciders: [leadership]
decision: 'The framework is a separate OSS package with no host and no proprietary code; dependencies only point down.'
related: [FW-015, FW-012, middag-php-ui:UI-001, docs-middag-ops:ADR-003, docs-middag-ops:ADR-025]
supersedes: []
superseded_by: null
enforced_by:
  mdgstan: []
  docs: [docs/architecture.md]
---

# FW-011: Why a separate FRAMEWORK package exists

## Context

The generic plumbing (kernel, container, HTTP, forms, bus, persistence) started as a folder inside one Moodle product plugin. MIDDAG builds products for more than one host, and the plumbing has nothing product-specific in it. The question is where that plumbing lives and what it is allowed to depend on.

## Considered Options

1. Keep it as a `classes/framework/` folder inside the Moodle product plugin.
2. One proprietary package that holds both the plumbing and the governed domain.
3. Packages that mix OSS and proprietary code, switched by feature flags.
4. A separate OSS Composer package that contains no host code and no proprietary code, where dependencies only point down: ui ← framework ← host adapters, proprietary packages and tooling. ← chosen

## Decision

The framework is its own OSS Composer package, versioned on its own. It contains no host code and no proprietary code. Its only MIDDAG dependency is ui. Every other MIDDAG package may depend on it, and it never depends on any of them. Governed concerns live in downstream proprietary packages. Host identity and configuration come from outside the package (FW-015). The boundary is a hard gate with zero allowed violations and no allowlist.

## Consequences

- (+) The same plumbing serves every host and every product, and its versions are independent of any product.
- (+) Third parties can build on it and write adapters for other hosts.
- (+) Because the boundary is mechanical, it can be checked in CI instead of in review.
- (−) A change that spans packages needs coordinated releases across repositories, and adapters may pin different framework minors.
- (−) Seams that only a downstream package fills, such as the signal dispatcher, can look incomplete to an OSS user.

## Enforcement

| Decision clause | Verification | State |
|---|---|---|
| No host namespace or host global in `src/` | deptrac `Host` layer and `bin/portability-scan.php`, run by `composer check:portability` | coded |
| No proprietary or unknown dependency | deptrac `--fail-on-uncovered` in `composer check:portability` | coded |
| Zero violations, no allowlist | `composer check:portability` in CI (`composer check`) | coded |
| Dependency direction across the ecosystem | doc `docs/architecture.md` §1 | coded |
| Historical portability audit | doc `docs-middag-dev:docs/framework/reference/portability-audit-historical.md` | coded |
