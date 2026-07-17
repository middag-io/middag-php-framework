---
ref: REF-FW-013-01
adr: FW-013
title: 'The Legacy Five-Families Catalog, Base-Class Table, a Citation Error & Anti-Patterns'
lang: en
---

# REF-FW-013-01: The Legacy Five-Families Catalog, Base-Class Table, a Citation Error & Anti-Patterns

> Detail supporting [FW-013](../decisions/FW-013-repository-persistence-boundary.md). Reconstructed from the `moodle-local_middag` legacy vault (ADR-503 + ref-503-01). **The five-families catalog below is historical context, not something this framework repository implements** — see the ADR's Consequences for why it now belongs to `middag-io/core`.

## The five families (legacy, product-specific — preserved for context only)

| Family (legacy)  | Schema                                                                        | Aggregate              | Description                            |
|------------------|-------------------------------------------------------------------------------|------------------------|----------------------------------------|
| Current state    | EAV (`middag_items` + `middag_itemmeta`)                                      | `domain/item`          | Current state with extensible metadata |
| Revision history | EAV (`middag_item_revision` + `middag_item_revision_meta`)                    | `domain/item_revision` | Structural version history             |
| Audit trail      | Relational (`middag_audit_log`, `middag_audit_diff`, `middag_audit_snapshot`) | `domain/audit`         | Operation traceability                 |
| Job governance   | Relational (`middag_job`, `middag_job_attempt`)                               | `domain/job`           | Observability, retry, correlation      |
| Activity feed    | Relational (`middag_activity_feed`)                                           | `domain/activity_feed` | Activity timeline for UI/notifications |

EAV applied to the two "current state"/"revision" families (extensible metadata per type); audit, job and activity feed were plain relational tables with a fixed schema. The rule for extensions building on top of the legacy plugin: use exclusively these five families — never create a bespoke table of your own inside the host plugin.

External plugins with their own tables were a different case: they could declare their own schema and still use the framework's query engine — their own EAV pair (`from('yourplugin_items', 'yourplugin_itemmeta')`), dedicated non-EAV tables (`table_repository` + `from('yourplugin_table')` with no meta table), or both at once. The query engine's default fallback remained the host plugin's own `middag_items`/`middag_itemmeta`.

## Repository base classes per family (legacy)

| Family (legacy)  | Extension base class                   | Framework base class                                            | Consumed via                   |
|------------------|----------------------------------------|-----------------------------------------------------------------|--------------------------------|
| Current state    | `base\domain\item_repository`          | `framework\domain\item\item_repository`                         | Direct repository              |
| Revision history | `base\domain\item_revision_repository` | `framework\domain\item_revision\item_revision_repository`       | Direct repository              |
| Audit trail      | — (framework only)                     | `framework\domain\audit\audit_*_repository`                     | `facade\audit_log_service`     |
| Job governance   | — (framework only)                     | `framework\domain\job\job_repository`, `job_attempt_repository` | `facade\job_service`           |
| Activity feed    | — (framework only)                     | `framework\domain\activity_feed\activity_feed_repository`       | `facade\activity_feed_service` |

Extensions used only the first two families directly through a base class; the other three were consumed exclusively through a service/facade, never a direct repository call — a narrower surface for the families with more governance built in.

## A citation error worth preserving (non-normative, but avoids chasing a phantom file)

The legacy ADR-503 body cited "see REF-503-02 for an implementation guide" — **that file does not exist**. The real REF companion is `ref-503-01-repository-patterns.md`, confirmed by directory listing at the time of the original synthesis. Treat this as an error in the original ADR's own citation, to be corrected whenever the legacy vault itself is edited — not as a pointer to a document that was somehow lost. Everything "REF-503-02" would supposedly have covered (a per-family repository implementation guide) is already in `ref-503-01`.

## Cross-reference: cache decoration (a separate, older legacy decision)

A repository could be wrapped by a cache decorator combining request-level (in-memory) and a persistent cache layer, keyed as `{aggregate}_{id}` and invalidated on write. That mechanism comes from a different, earlier legacy ADR (ADR-303) that belongs to a Moodle-specific document, not this one — mentioned here only because it depends on the same rule this ADR states: caching decorates a repository, it is never the repository's own responsibility.

## Anti-patterns (legacy REF-503-01)

- `$DB->get_record()` (or any host global data-access call) directly inside an extension.
- A repository returning a bare `stdClass` instead of a proper mapped domain object.
- An extension creating its own table inside the host plugin's schema instead of using one of the sanctioned families (a rule specific to the legacy plugin's own product schema, not a framework-level constraint).
- A repository for one family reaching into another family's table directly.
- A service embedding raw SQL instead of going through a repository.
- A repository with no accompanying interface — breaks the "repository and interface belong to the aggregate" rule this ADR states.
