---
title: 'Why a separate FRAMEWORK package exists'
status: accepted
date: 2026-04-11
last-updated: 2026-11-13
lang: en
domains: [framework, portability]
deciders: [leadership]
decision: 'The framework is a separate OSS package with no host and no proprietary code; dependencies only point down.'
related: [FW-015, FW-012, middag-php-ui:UI-001, docs-middag-ops:ADR-003, docs-middag-ops:ADR-025, docs-middag-ops:ADR-032]
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

## Dependency constraints and the "libs from the host" variant

A host that already ships a shared copy of a Symfony or PSR package can only satisfy the
framework's requirement if the requirement is as wide as the code actually needs. So a constraint
here states the lowest version the code works on, never the newest one available: a floor raised
for no reason silently rules out a host, and the vendor-prefixing the Moodle plugin does today is
the workaround for exactly that. `symfony/deprecation-contracts` is the current example — nothing
in `src/` calls `trigger_deprecation()`, and every other package in the graph accepts `^2.5|^3`.

Dropping the prefix and consuming the host's own copies is not a framework decision, and it is not
viable today: the proprietary libs need `firebase/php-jwt ^7` (a security release) while Moodle
ships 6.10–6.11 in every supported version, Monolog, `google/auth` and `symfony/string` are absent
from the older ones, and the official 5.2/5.3 ZIPs ship without `vendor/`. **Revisit trigger:** the
minimum supported Moodle is 5.3 or newer **and** Moodle ships `firebase/php-jwt` 7.x. Until both
hold, the closed-list prefixing stands and the framework's part is only to keep its constraints at
their real floor.

## Enforcement

| Decision clause | Verification | State |
|---|---|---|
| No host namespace or host global in `src/` | deptrac `Host` layer and `bin/portability-scan.php`, run by `composer check:portability` | coded |
| No proprietary or unknown dependency | deptrac `--fail-on-uncovered` in `composer check:portability` | coded |
| Zero violations, no allowlist | `composer check:portability` in CI (`composer check`) | coded |
| Dependency direction across the ecosystem | doc `docs/architecture.md` §1 | coded |
| Historical portability audit | doc `docs-middag-dev:docs/framework/reference/portability-audit-historical.md` | coded |
