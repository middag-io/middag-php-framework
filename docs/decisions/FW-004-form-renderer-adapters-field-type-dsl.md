---
title: 'The renderer seam lives in ui; host widgets only in adapters'
status: accepted
date: 2026-04-13
last-updated: 2026-11-04
lang: en
domains: [framework, forms]
deciders: [leadership]
decision: 'Render targets implement the ui renderer interface over a closed field vocabulary; host widgets exist only in adapters.'
related: [FW-003, FW-015, middag-php-ui:UI-001]
supersedes: []
superseded_by: null
enforced_by:
  mdgstan: []
  docs: []
---

# FW-004: The renderer seam lives in ui; host widgets only in adapters

## Context

A form schema renders to more than one target: a host's native form widgets and an SPA payload. Host widget types must not leak into code that has to stay host-agnostic, and the shape a renderer emits is part of the UI wire contract, which belongs to ui. Without a fixed field vocabulary, every new UI need would add a new field variant.

## Considered Options

1. A renderer interface owned by the framework.
2. No fixed boundary: each renderer reads and exposes host types directly in the form layer.
3. Host renderers, such as a Moodle mform renderer, shipped inside the framework.
4. Render targets implement ui's renderer interface against a closed, versioned field vocabulary behind a single factory. The framework ships only the host-neutral SPA renderer, and host widget renderers live only in adapters. ← chosen

## Decision

The renderer seam is `FormRendererInterface` in ui. Every render target implements it. The framework ships one host-neutral renderer, for Inertia. Renderers that produce a host's native widgets exist only in that host's adapter. The renderer consumes a closed, versioned field vocabulary that is built through a single factory, which validates names when a field is constructed. The catalog lives in the reference.

## Consequences

- (+) No host widget type can enter the form layer, and adding a renderer never touches schema code.
- (+) Field types grow in one place instead of as unrelated classes.
- (−) The framework depends on ui for the renderer interface.
- (−) Mapping every field and modifier onto host widgets, the hardest part of the work, is done again in each adapter.

## Enforcement

| Decision clause | Verification | State |
|---|---|---|
| The framework renderer implements ui's interface | test `tests/Form/Renderer/InertiaRendererTest.php` | coded |
| A single factory with name validation at construction | tests `tests/Form/FieldFactoryTest.php`, `tests/Form/Field/AbstractFieldTest.php` | coded |
| No host type or host renderer in the framework | test `tests/Architecture/NoHostAdapterDirectoryTest.php`; deptrac `Host` layer in `composer check:portability` | coded |
| Field catalog and anti-corruption rules | doc `docs-middag-dev:docs/framework/reference/form-field-catalog-and-anti-corruption.md` | coded |
