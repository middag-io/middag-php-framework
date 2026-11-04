---
title: 'Multi-mode rendering, no template engine'
status: accepted
date: 2026-04-05
last-updated: 2026-11-04
lang: en
domains: [framework, http, rendering]
deciders: [leadership]
decision: 'Host page, Inertia SPA and JSON API coexist, chosen per controller; the framework ships no template engine.'
related: [FW-006, FW-002, middag-php-ui:UI-002]
supersedes: []
superseded_by: null
enforced_by:
  mdgstan: []
  docs: []
---

# FW-005: Multi-mode rendering, no template engine

## Context

A trivial settings screen does not justify a full SPA round-trip, and a rich screen is too much for a bare server-rendered page. The framework also has to decide whether page composition belongs to it or to the UI contract package.

## Considered Options

1. Every screen through the SPA.
2. Every screen as server-rendered HTML only.
3. A template engine owned by the framework, in the style of Blade or Twig.
4. Three modes that coexist, chosen per controller, with no template engine: page composition belongs to ui's page contract. ← chosen

## Decision

Three rendering modes coexist: a host page, an Inertia SPA page and a JSON API. Each controller picks its mode through the base class it extends and the response it returns. One extension may mix all three, and the SPA is never mandatory. The framework does not build a template engine. Page composition is ui's declarative page contract, and HTML in the host's look is the host adapter's job.

## Consequences

- (+) Each screen uses the lightest mode that fits.
- (+) Composition is defined once, in ui, so no framework templating layer duplicates it.
- (−) Host-page mode depends on the adapter to produce HTML in the host's theme.
- (−) Table and widget builders are not part of the framework. Consumers use ui's builders.

## Enforcement

| Decision clause | Verification | State |
|---|---|---|
| Page and API controller bases | tests `tests/Http/Controller/AbstractControllerTest.php`, `tests/Http/Controller/AbstractApiControllerTest.php` | coded |
| No template engine dependency | review of `composer.json` | planned |
| Mode-selection table and rendering anti-patterns | doc `docs-middag-dev:docs/framework/reference/rendering-mode-selection.md` | coded |
