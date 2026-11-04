---
title: 'The OSS ships only the generic reactive seam'
status: accepted
date: 2026-03-29
last-updated: 2026-11-04
lang: en
domains: [framework, events]
deciders: [leadership]
decision: 'The OSS ships a contract-only signal dispatcher and a per-instance hook manager; governed dispatch lives downstream.'
related: [FW-008, FW-011, FW-016]
supersedes: []
superseded_by: null
enforced_by:
  mdgstan: []
  docs: []
---

# FW-007: The OSS ships only the generic reactive seam

## Context

Extensions need to react to things that already happened in two ways: typed publish/subscribe for domain code, and string-named actions and filters for casual third-party extension. Reliable delivery (signal hierarchy, discovery, reentrance protection, a persistent outbox) is governed infrastructure. The framework has to decide which of these it ships as OSS.

## Considered Options

1. Ship the whole governed engine in the framework.
2. A static, process-global hook registry.
3. Hook callbacks wrapped in an automatic try/catch.
4. A default host-event bridge implementation in the framework.
5. A contract-only signal dispatcher, a stable host-event bridge contract that the framework does not implement (the adapters do), and a per-instance action/filter hook manager that does not swallow callback exceptions. Governed dispatch lives in downstream proprietary packages. ← chosen

## Decision

The framework ships the generic reactive seam and nothing more. It defines a signal-dispatcher contract with no OSS implementation, and a stable public host-event bridge contract that the framework does not implement; each host adapter implements it over the host's native eventing. It ships a full action/filter hook manager that keeps its registry per instance, not in static globals, and lets callback exceptions propagate. Governed dispatch and delivery are concerns of downstream proprietary packages. Hook tags are free strings; the reference documents the naming convention the ecosystem follows, which is a convention and not a guarded rule.

## Consequences

- (+) The framework keeps the boundary set by FW-011: no governed delivery ships as OSS.
- (+) Two plugins in one process cannot overwrite each other's hooks.
- (+) Failures in callbacks belong to the callback's author and are never hidden.
- (−) An OSS consumer that needs durable or ordered delivery has to implement the signal contract itself or use a downstream package.
- (−) The static hook facade is a bounded exception to per-instance state, covered by FW-016.
- (−) Nothing validates hook tag names; a misspelled tag fails silently. An overridable naming policy with a guard remains an open proposal, not part of this decision.

## Enforcement

| Decision clause | Verification | State |
|---|---|---|
| The signal dispatcher and host-event bridge are contracts only; adapters implement the bridge | doc `docs-middag-dev:docs/framework/reference/hooks-public-surface.md` | planned |
| Per-instance hook registry | test `tests/Kernel/Manager/HookManagerTest.php` (`testInstancesDoNotShareState`) | coded |
| Callback exceptions are not swallowed | test `tests/Kernel/Manager/HookManagerTest.php` (`testDoActionDoesNotSwallowCallbackExceptions`, `testApplyFiltersDoesNotSwallowCallbackExceptions`) | coded |
| Public and internal hook surface, descriptive tag-naming convention | doc `docs-middag-dev:docs/framework/reference/hooks-public-surface.md` | planned |
| Legacy governed-engine mechanics, kept for reference | doc `docs-middag-dev:docs/framework/reference/signal-hook-legacy-mechanics.md` | coded |
