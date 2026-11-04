---
title: 'Output sanitized by default; opt out with #[TrustedOutput]'
status: accepted
date: 2026-04-04
last-updated: 2026-11-04
lang: en
domains: [framework, content]
deciders: [leadership]
decision: 'Output is sanitized by default; a framework-owned #[TrustedOutput] marker opts out and adapters honour it.'
related: [FW-015]
supersedes: []
superseded_by: null
enforced_by:
  mdgstan: []
  docs: []
---

# FW-009: Output sanitized by default; opt out with #[TrustedOutput]

## Context

Code that produces markup for a host page (originally shortcode handlers) needs one safety posture across hosts. A small number of handlers really do need to emit structured HTML. The escaping itself happens in the host, so the framework can only declare intent.

## Considered Options

1. Output trusted by default.
2. A trust marker defined by each adapter.
3. A shortcode or macro engine shipped in the framework.
4. Output sanitized by default, with a framework-owned attribute as the only opt-out, which adapters honour. Macro engines are not a framework decision. ← chosen

## Decision

Output is sanitized by default. A handler that has to emit trusted markup declares it with the framework's `#[TrustedOutput]` attribute, and responsibility for XSS moves explicitly to that handler's author. The framework owns the marker and host adapters honour it. Whether a host offers a text-macro (shortcode) engine is an adapter or product decision, not a framework one.

## Consequences

- (+) One marker means the same thing on every host.
- (+) Every unescaped output has an author who is accountable for it.
- (−) The framework itself does not apply the marker. Each adapter has to honour it, and no cross-repository check confirms that it does.
- (−) Hosts that want macro syntax over prose content have to build it in their adapter.

## Enforcement

| Decision clause | Verification | State |
|---|---|---|
| Adapters honour the marker | review in each adapter | planned |
| No macro engine ships in the framework | review | planned |
| Historical shortcode design, kept for reference | doc `docs-middag-dev:docs/framework/reference/shortcode-legacy-detail.md` | coded |
