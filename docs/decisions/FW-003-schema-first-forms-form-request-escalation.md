---
title: 'Validation declared as data'
status: accepted
date: 2026-04-13
last-updated: 2026-11-04
lang: en
domains: [framework, forms]
deciders: [leadership]
decision: 'A form schema or validated DTO is the single source of render and validation; FormRequest is an optional escalation.'
related: [FW-002, FW-004, FW-010]
supersedes: []
superseded_by: null
enforced_by:
  mdgstan: []
  docs: []
---

# FW-003: Validation declared as data

## Context

Fields used to be declared once for the UI and again for validation, and nothing tied the two together. A REST endpoint that accepted the same payload could not share the rules. Page clients and API clients also received validation errors in different shapes.

## Considered Options

1. Validation written per form, wherever the developer puts it.
2. A mandatory request-validation class for every form.
3. Conditional field behaviour as procedural callbacks that each renderer evaluates.
4. A declared schema (a form's schema, or a DTO marked as validated) as the single source of rendering and validation. A request-validation class is an optional escalation. Every style fails with one error shape (HTTP 422) through one parameter-resolver chain. ← chosen

## Decision

The declared schema is the single source of rendering and validation. It is either a form's schema or a DTO marked `#[ValidatedDto]`. A FormRequest is an optional escalation for cross-field rules, rules that need the database, or rules shared with a REST endpoint. It is never the default path. Every validation style fails with the framework's validation exception (HTTP 422) and the same error-map shape, and is resolved through the controller-parameter resolver chain. Conditional field behaviour is schema data, not code.

## Consequences

- (+) The UI and validation cannot drift, because only one declaration exists.
- (+) Page clients and API clients receive the same error contract.
- (+) Every renderer reads conditional behaviour from the same data.
- (−) Pairing a FormRequest with a form is a convention of the controller signature. The two resolvers are independent and the framework does not enforce the pairing.
- (−) Nothing checks automatically that a schema rule and a FormRequest rule for the same field agree.

## Enforcement

| Decision clause | Verification | State |
|---|---|---|
| Form lifecycle: hydrate and validate from the schema | tests `tests/Form/AbstractFormTest.php`, `tests/Http/Resolver/FormResolverTest.php` | coded |
| A validated DTO is hydrated and validated before the action | test `tests/Http/Resolver/ValidatedDtoResolverTest.php` | coded |
| One error shape with HTTP 422 | tests `tests/Http/ValidationErrorSerializerTest.php`, `tests/Exception/ExceptionHierarchyTest.php` | coded |
| FormRequest is an escalation, never the default | review | planned |
| Lifecycle, field and rule catalog, escalation rules | doc `docs-middag-dev:docs/framework/reference/form-lifecycle-and-validation.md` | coded |
