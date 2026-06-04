---
name: Feature request
about: Suggest an idea or enhancement for middag-io/framework
title: "[Feature] "
labels: enhancement
assignees: ""
---

## Problem / motivation

<!-- What problem does this solve? What are you trying to do that the framework
makes hard, awkward, or impossible today? Describe the use case, not just the
desired API. -->

## Proposed solution

<!-- Describe the behavior or API you would like. Sketch contracts, method
signatures, or example usage if it helps. -->

## Alternatives considered

<!-- Other approaches you weighed and why you ruled them out (workarounds,
userland implementations, third-party packages, doing nothing). -->

## Scope check

`middag-io/framework` is the generic, platform-agnostic plumbing: the DI kernel
and modules, HTTP, persistence/query abstractions, forms, the command bus, the
sync `HookManager`, logging, and the Inertia adapter. Host-specific behaviour
belongs in an adapter, not here. Please confirm where you believe this feature
belongs:

- **Generic framework plumbing (this repo)** — reusable across every host, with
  no host APIs and no domain-specific governance.
- **Host adapter / your own application** — host-specific wiring, or domain
  logic that builds on top of the framework rather than inside it.

<!-- Tick the one that fits, and add a sentence on why. -->

- [ ] This belongs in the OSS framework (generic plumbing).
- [ ] This is host-specific or application-level (an adapter / consumer).
- [ ] Not sure — please help me decide.

## Additional context

<!-- Links, prior art, related issues, version constraints, or anything else
that helps us understand the request. -->
