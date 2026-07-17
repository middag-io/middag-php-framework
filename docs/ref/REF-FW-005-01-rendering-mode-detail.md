---
ref: REF-FW-005-01
adr: FW-005
title: 'Mode Selection Table & Rendering Anti-Patterns'
lang: en
---

# REF-FW-005-01: Mode Selection Table & Rendering Anti-Patterns

> Detail supporting [FW-005](../decisions/FW-005-multi-mode-rendering-model.md). Reconstructed from the `moodle-local_middag` legacy vault (ADR-803 + ref-803).

## Mode selection (legacy naming)

| Mode          | Legacy base                         | Output                    | Use                       |
|---------------|-------------------------------------|---------------------------|---------------------------|
| Host pages    | `abstract_controller`               | HTML `$OUTPUT` + Mustache | Admin, settings, reports  |
| SPA (Inertia) | `abstract_controller` + `inertia()` | JSON to React             | Dashboards, dynamic forms |
| API JSON      | `abstract_api_controller`           | Pure JSON                 | REST/AJAX/mobile          |

In the current codebase the host-page row is adapter composition rather than a distinct framework base class (see REF-FW-002-01) — `AbstractController` and `AbstractApiController` (`Http/Controller/`) are the two OSS base classes; the Inertia row is the same `AbstractController` paired with `InertiaAdapter::render()` (FW-006).

## Anti-patterns (legacy REF-803, not all explicit in the ADR body)

- Calling the host's native output renderer directly from an extension (`$OUTPUT->render()` in the Moodle vault) — bypasses the controller boundary.
- Setting page metadata directly on a host page global (`$PAGE->set_title()` in the Moodle vault) instead of going through a dedicated trait/contract — couples extension code to a host global that a standalone or WordPress target does not have.
- Reaching for Inertia on a simple admin-settings screen — overhead with no payoff when a plain host page would do.
- Putting non-trivial logic inside a server-side template (Mustache in the legacy vault) instead of the controller/renderable that prepares its data — template logic complexity is a smell regardless of which templating mechanism a given host adapter uses.

These read directly onto the current architecture: any host-page composition an adapter builds should keep page-identity concerns (title, breadcrumbs) behind a contract the framework or UI package defines, not a bare host global, for exactly the portability reason FW-011 formalises.
