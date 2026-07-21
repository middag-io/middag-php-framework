---
id: FW-009
title: 'WordPress-Style Shortcode Processing'
status: accepted
date: 2026-04-04
lang: en
domains: [framework, content]
deciders: ['PENDING — original decider not recorded during the legacy-vault reconstruction; confirm with Michael Meneses before ratifying']
related: [FW-007]
supersedes: []
superseded_by: null
enforced_by:
  mdgstan: []
  docs: [framework/reference/shortcode-legacy-detail]
decision: 'No shortcode-parsing engine ships in this OSS framework; only the sanitize-by-default/#[TrustedOutput]-opt-out safety posture it originated survives, generalised as a framework-owned attribute.'
---

# FW-009: WordPress-Style Shortcode Processing

> [!NOTE]
> **Provenance.** Reconstructed from the `moodle-local_middag` legacy vault (`ADR-704`, decided 2026-04-04). This is an archaeology pass, not a new decision — dates and rationale are historical. **This mechanism does not exist in `middag-php-framework` today** — this ADR is preserved so the rationale behind one surviving piece of it (`#[TrustedOutput]`) is not lost.

## Context

Prose content (a host's text-filter pipeline) needed a WordPress-style macro syntax — `[middag type="xyz" attr1="val1"]` — that plugin code could register handlers for, with the same sanitize-by-default safety posture as any other user-facing output surface.

## Considered Options

1. **Auto-discovery registration** (consistent with every other reactive subsystem in the legacy design — hooks, async signals) — rejected in favor of manual registration only, a deliberate departure for this mechanism.
2. **Trusted-by-default output** for shortcode handlers — rejected; sanitize-by-default was kept as the safety posture, with an explicit opt-out marker for the exceptional case.
3. **Silent failure for all handler errors, including registered ones** — rejected; only a malformed macro or an unregistered tag fails silently, an exception from a valid registered handler still propagates, per the FW-007 failure-isolation rule.

## Decision

A `shortcode_manager`-equivalent parsed `[middag ...]` macros via regex, with **manual registration only** (`register(tag, callback)` at boot) — a deliberate departure from the auto-discovery pattern used by every other reactive subsystem (hooks, async signals) in the legacy design. Output was sanitized by default; a handler needing genuine structured HTML (iframes, etc.) opted out via a `#[trusted_output]`-style marker, explicitly shifting XSS responsibility onto that handler's author. Failure handling followed the same rule as the rest of the reactive model (FW-007): a malformed macro or an unregistered tag failed silently (fail-safe), but an exception thrown by a valid, registered handler propagated — because rendering that handler's output is part of the contract the final HTML response promises the caller.

## Consequences

- **Not present in this OSS repo.** A `grep -ri shortcode src/` in this framework returns nothing except the surviving trust-marker attribute (see below) — the macro-parsing engine itself did not carry over into the standalone package. Text-macro processing over prose content is inherently tied to a host's own content-authoring model (a Moodle text filter, a WordPress shortcode-like feature); a host-agnostic framework has no equivalent surface of its own to hang this on, so it is reasonable this was descoped rather than ported.
- **What did survive: the trust-marker concept, generalised.** `Shared/Attribute/TrustedOutput.php` (`#[TrustedOutput]`, listed as a framework-owned concern in `architecture.md` §9.2) keeps the exact safety posture this ADR introduced — sanitize by default, explicit developer opt-out for genuinely trusted markup — but decoupled from shortcode handlers specifically. Any renderer in the ecosystem that needs to declare "this output is intentionally unescaped" can use the same attribute now.
- This ADR is kept purely as historical record: if a product still wants WordPress-style macro processing over prose content, that belongs in a host adapter (where a text-filter/content-pipeline concept actually exists), not in this framework — same OSS/host-adapter boundary discussed in [FW-007](./FW-007-signal-hook-reactive-model.md).
- Any host-specific macro/shortcode re-implementation a product or adapter chooses to build is out of scope for this framework and this ADR.

## Enforcement

| Decision clause | Verification | State |
|---|---|---|
| No shortcode-parsing engine ships in `src/` | `grep -ri shortcode src/` returns nothing except `TrustedOutput` | planned |
| `#[TrustedOutput]` is the sole surviving safety-posture artifact | `Shared/Attribute/TrustedOutput.php` exists and is framework-owned per `architecture.md` §9.2 | coded |
| Full legacy parsing/security/anti-pattern detail preserved for historical reference | `docs-middag-dev`: `framework/reference/shortcode-legacy-detail` | coded |
