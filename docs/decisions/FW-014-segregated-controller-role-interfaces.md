---
id: FW-014
title: 'Segregated Controller Role Interfaces (ISP)'
status: accepted
date: 2026-07-24
lang: en
domains: [framework, http]
deciders: ['Michael Meneses']
related: [FW-012]
supersedes: []
superseded_by: null
enforced_by:
  mdgstan: []
  docs: []
decision: 'Split the monolithic `Http\Contract\ControllerInterface` into three host-neutral role interfaces — `ContainerAwareInterface` (container wiring), `RequestHandlingInterface` (the kernel request → preHandle → handle cycle), and `AuthorizationAwareInterface` (the #[Auth]-derived login/capability gate) — and redefine `ControllerInterface` as the empty union of the three. Its total method surface is unchanged (non-breaking), but an adapter whose dispatch model is not the kernel single-request cycle can now implement only the roles it supports instead of the full contract.'
---

# FW-014: Segregated Controller Role Interfaces (ISP)

## Context

`ControllerInterface` bundled three unrelated responsibilities into one contract: container wiring (`setContainer`), the request lifecycle (`setRequest` / `preHandle` / `handle`), and the auth gate (`setRequireLogin` / `setRequireCapabilities`). This fits the standalone kernel and the Moodle adapter, where a page controller and its REST sibling both run the same single request → `handle()` → response cycle (`AbstractApiController extends AbstractController` in both).

It does **not** fit the WordPress adapter's REST stack. WordPress REST is a registration model: a controller calls `register_rest_route` to register N endpoints, each with its own callback and `permission_callback`, dispatched by the WP REST server against a `WP_REST_Request` and returning a `WP_REST_Response`/`WP_Error`. There is no single `handle()` / `setRequest(Request)` cycle, and a permission failure must be reported as `WP_Error`, not by halting. So `AbstractWpRestController` cannot honestly implement the monolithic `ControllerInterface`: it would inherit `handle()` / `setRequest()` that never run and a `setRequireCapabilities()` whose page-shaped enforcement is wrong for REST.

The question this ADR answers: is that mismatch a boundary working as designed, or a framework gap? Verdict: partly a gap. The framework must never model WordPress REST's registration lifecycle (that would violate the OSS × host boundary), but the *monolithic* contract is a genuine Interface-Segregation smell — the framework already ships smaller role seams elsewhere (`CapabilityRequirementAwareInterface`, `PublicRouteAwareInterface`, `AuthenticatorInterface`), so `ControllerInterface` was the unsegregated exception.

## Considered Options

1. **Keep the monolithic `ControllerInterface`** — adapters that don't fit implement their own unrelated contract (WP `RestControllerInterface`). Rejected: forces a host REST base to either fake the lifecycle methods or share nothing, and leaves the ISP smell in place.
2. **Grow `ControllerInterface` with host-shaped flexibility** (register-routes hooks, optional lifecycle) so every host fits one contract. Rejected: leaks a host idiom (WP REST registration) upward into the OSS framework — a boundary violation.
3. **Segregate into host-neutral role interfaces, with `ControllerInterface` as their union** ← chosen. Pure ISP: `ContainerAwareInterface`, `RequestHandlingInterface`, `AuthorizationAwareInterface`. `ControllerInterface extends` all three, so its total surface — and therefore every existing implementation (`AbstractController`, Moodle's `MoodleControllerInterface`) — is unchanged. An adapter can adopt only the roles that fit its dispatch model.

## Decision

`ControllerInterface` is redefined as `interface ControllerInterface extends ContainerAwareInterface, RequestHandlingInterface, AuthorizationAwareInterface {}` (option 3). The three roles are host-neutral and carry no host types. This is **non-breaking**: reflection over `ControllerInterface` still exposes all six methods, `AbstractController` implements it unchanged, and Moodle's `MoodleControllerInterface extends ControllerInterface` is unaffected.

The framework does **not** grow any WordPress-REST-shaped surface (option 2 rejected): the WP REST stack keeps its own adapter-owned `RestControllerInterface`. What segregation buys is composition — a host REST base may now implement `ContainerAwareInterface` alone to receive the container, without inheriting the request lifecycle it does not run. The interface fixes signatures, not enforcement: a page controller may redirect/halt on an auth failure while a REST controller reports it in its own protocol.

## Consequences

- Existing code compiles and passes unchanged; no implementor edits required.
- Adapters gain a menu of roles to compose. The WordPress adapter's page-controller base (`AbstractWpController`, wordpress#39) extends the framework `AbstractController` (full contract); its REST base can compose only the roles that fit.
- The three roles are the seam any future non-kernel dispatch model (queue consumers, CLI controllers) composes from, instead of faking `handle()`.
- The de/para of controller bases across framework / Moodle / WordPress that motivated this split is recorded in the WordPress adapter's first ADR (wordpress#40).
