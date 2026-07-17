---
ref: REF-FW-011-01
adr: FW-011
title: 'The Historical Portability Audit (Score, Violations, Accepted Exceptions, Blockers)'
lang: en
---

# REF-FW-011-01: The Historical Portability Audit (Score, Violations, Accepted Exceptions, Blockers)

> Detail supporting [FW-011](../decisions/FW-011-framework-composer-portability.md). Reconstructed from the `moodle-local_middag` legacy vault (ADR-927 + ref-927). **Preserved as a historical snapshot, not a current-state report** — the constraint this audit measured is now enforced structurally by this repo's very existence as a standalone package (see the ADR's Consequences).

## The audit that ADR-927's own normative text did not have

The legacy REF-927 (unlike the purely normative ADR body) contained a real, quantified audit of the plugin's `framework/` code against the five extraction rules — the single most actionable finding across the whole legacy vault, because it named exact files rather than describing an aspiration.

## Portability score at audit time

| Dimension               | Score (legacy audit) |
|-------------------------|----------------------|
| Namespace isolation     | 100%                 |
| Deptrac boundary        | 90%                  |
| Moodle boundary         | 40%                  |
| Hard-coded paths        | 30%                  |
| require_once discipline | 85%                  |
| **Average**             | **69%**              |

## Named violations at audit time

- **Host globals used outside the `moodle/` adapter layer (4 files):** `shared/util/helper.php` (`$OUTPUT`, `$PAGE`), `infrastructure/adapter/inertia/inertia_response.php` (`$PAGE`), `infrastructure/adapter/inertia/inertia_shared_props.php` (`$USER`, `$PAGE`), `infrastructure/logging/file_logger.php` (`$USER`).
- **Hard-coded paths (6 files):** `kernel/router.php`, `kernel/container_factory.php`, `kernel/http/plugin_aware_url_generator.php`, `kernel/http/abstract_controller.php`, `infrastructure/adapter/inertia/inertia_response.php` (a CSS path), `service/extension_service.php` (a binary/Pix path).
- **Accepted exceptions (justified, not counted as violations):** `kernel/container_factory.php` reading `$DB`/`$CFG` directly — justified because this runs at bootstrap, before the DI container exists to inject anything (a separate, older decision, ADR-926, governs this specific carve-out); `shared/util/environment.php` reading `$CFG` for environment detection, on the same rationale.

## Blockers named for the extraction

(1) The Inertia adapter and `shared/util/helper` needed a refactor to receive host globals via an injected adapter rather than reading them directly; (2) entry-point URLs, asset paths and binary paths needed to read from injected configuration instead of being hard-coded; (3) `kernel/` needed to accept the host's plugin identity via DI rather than inferring it from a global constant.

## Reading this today

Every one of the three blockers maps directly onto a mechanism this framework repository now ships as a first-class contract: blocker 1 → the bridge-contract pattern (`HostComponentContextInterface`, `UserContextResolverInterface`, etc., `architecture.md` §5); blocker 2 → `ConfigResolverInterface` (`EnvConfigResolver` default); blocker 3 → `BootstrapInterface::getProjectRoot()`/`platform()` feeding `ContainerFactory::build()` (`bootstrap.md` §1). Whether the exact five-file violation list was fixed file-by-file before this package was assembled, or the package was written fresh against the bridge-contract pattern from the start, is not established by this pass — but the audit's own blockers read, today, like a punch list that has since been closed by design rather than by a follow-up patch.
