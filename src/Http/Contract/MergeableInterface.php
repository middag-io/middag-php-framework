<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Http\Contract;

/**
 * Marker for a prop the client MERGES into its existing value instead of replacing it (Inertia v3 `merge`).
 *
 * Unlike {@see IgnoreFirstLoadInterface} props, a mergeable prop resolves and is
 * present on every render (full and partial) — the marker only tells the client
 * HOW to apply it on a follow-up partial reload: shallow- or deep-merge, and (for
 * paginated feeds) which key to match array items on so appends/prepends dedupe.
 *
 * {@see InertiaResponse::resolveProps()} resolves the value through the normal
 * path and, unless the request opted the key out via `X-Inertia-Reset`, announces
 * it under the page object's `mergeProps` / `deepMergeProps` (and `matchPropsOn`).
 * Implemented by {@see MergeProp}; build one through {@see InertiaAdapter::merge()}
 * / {@see InertiaAdapter::deepMerge()}.
 *
 * @internal
 */
interface MergeableInterface
{
    /**
     * Resolve the prop value (invoke the wrapped callable, or return the raw value).
     */
    public function resolve(): mixed;

    /**
     * Whether the client should DEEP-merge (recursive) rather than shallow-merge.
     */
    public function deep(): bool;

    /**
     * Match keys for pagination dedup, relative to this prop.
     *
     * Each entry is emitted as `"{propKey}.{matchKey}"` under the page object's
     * `matchPropsOn`, so the client matches array items on that path when
     * appending/prepending a paginated page instead of duplicating rows.
     *
     * @return list<string>
     */
    public function matchOn(): array;
}
