---
title: 'Inertia server side inside the framework'
status: accepted
date: 2026-04-04
last-updated: 2026-11-04
lang: en
domains: [framework, http, rendering]
deciders: [leadership]
decision: 'The framework implements the Inertia server protocol, independent of the frontend framework, with no SSR.'
related: [FW-005, FW-016, middag-php-ui:UI-001]
supersedes: []
superseded_by: null
enforced_by:
  mdgstan: []
  docs: []
---

# FW-006: Inertia server side inside the framework

## Context

SPA screens need a server-side implementation of a client protocol: global data, SPA-safe redirects, partial reloads and asset versioning. ui describes pages and never knows the transport, so the protocol has to live somewhere else. It must not tie the server to one frontend framework.

## Considered Options

1. The protocol's server side in ui.
2. A wire format coupled to a specific frontend framework.
3. The Inertia protocol with server-side rendering (SSR).
4. The Inertia server side in the framework, independent of the frontend framework, SPA-only with no SSR. ← chosen

## Decision

The framework implements the server side of the Inertia protocol. It translates ui contracts and controller props into the wire format. The implementation does not depend on which frontend framework consumes it. There is no SSR. The static state of the protocol's entry points is covered by FW-016.

## Consequences

- (+) ui stays a pure contract package.
- (+) Changing the frontend framework does not touch the server side.
- (−) Without SSR, the first paint needs JavaScript.
- (−) Shared props are global data and tend to grow without discipline. Nothing limits them automatically.

## Enforcement

| Decision clause | Verification | State |
|---|---|---|
| Protocol behaviour (detection, redirects, partial reloads, deferred and merged props, versioning) | tests `tests/Http/Inertia/` | coded |
| Shared props are registered as lazy closures | review | planned |
| Shared props, redirect mechanics, partial-reload wire format | doc `docs-middag-dev:docs/framework/reference/inertia-protocol.md` | coded |
