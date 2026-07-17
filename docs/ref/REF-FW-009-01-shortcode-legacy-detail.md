---
ref: REF-FW-009-01
adr: FW-009
title: 'Shortcode Parsing Detail, Security Model & Anti-Patterns (historical)'
lang: en
---

# REF-FW-009-01: Shortcode Parsing Detail, Security Model & Anti-Patterns (historical)

> Detail supporting [FW-009](../decisions/FW-009-shortcode-processing.md). Reconstructed from the `moodle-local_middag` legacy vault (ADR-704 + ref-704). **Historical only** — none of the mechanism below ships in `middag-php-framework`; preserved so the rationale is not lost if a host adapter ever wants to rebuild an equivalent.

## Interface shape (legacy)

```php
interface shortcode_manager_interface {
    public function register(string $tag, callable $callback): void;
    public function render(string $text): string;
    public function has(string $tag): bool;
    public function clear(): void; // testing/reset
}
```

## Parsing detail

An early-return check skipped all regex work when the literal substring `[middag` was absent from the input text — the cheapest possible fast path for the overwhelmingly common case of prose with no macros at all. Main regex: `/\[middag\s+(.*?)\]/i`; attribute parser: `/(\w+)\s*=\s*(["\'])(.*?)\2/`. Tags were case-insensitive by design.

## Security model

Output was sanitized by default via the host's own text-cleaning function (Moodle's `clean_text()`). A handler that legitimately needed structured HTML (an iframe embed, for example) declared `#[trusted_output]` to disable sanitization for its own output — detected via reflection and cached per tag — which explicitly transferred XSS responsibility to that handler's author.

## Failure handling (aligned with the reactive-model rule, FW-007)

A malformed macro, or a tag with no registered handler, was ignored silently (fail-safe) — a broken or unknown macro in user-authored prose should not break the whole page. An exception thrown by a **valid, registered** handler propagated normally, because rendering that handler's output is part of the contract the final HTML response promises its caller — the canonical example the legacy ADR-702 cited for "output composition is not lateral."

## Anti-patterns (legacy REF)

- Producing HTML output with no `#[trusted_output]` marker — it silently disappears after the default sanitization pass.
- Declaring `#[trusted_output]` without separately escaping any user-supplied data inside that output — a direct XSS hole, since the marker only disables the *automatic* sanitization, not the handler's own responsibility to escape untrusted fragments.
- A handler with database write side effects — shortcodes execute on every render of a piece of text, so a handler must be idempotent and read-only; a write-on-render handler would fire repeatedly with no user-initiated action.
