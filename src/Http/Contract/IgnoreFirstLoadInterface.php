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
 * Marker for a prop whose value must NOT resolve on the first load.
 *
 * A prop carrying this marker is skipped on a full page load and on a normal
 * (non-partial) Inertia visit — its closure is never invoked there. It resolves
 * only when its key is explicitly requested in a partial reload
 * ({@see https://inertiajs.com/partial-reloads} `X-Inertia-Partial-Data`) for
 * the matching component, so partials save compute, not just transfer.
 *
 * Shared seam: implemented by {@see OptionalProp} and by the
 * deferred-prop value object. {@see InertiaResponse::resolveProps()} branches on
 * this interface alone, so any future first-load-skipping prop type plugs in by
 * implementing it.
 *
 * @internal
 */
interface IgnoreFirstLoadInterface
{
    /**
     * Resolve the deferred value (invoke the wrapped callable).
     */
    public function resolve(): mixed;
}
