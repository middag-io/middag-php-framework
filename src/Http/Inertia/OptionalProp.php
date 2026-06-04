<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Http\Inertia;

use Closure;
use Middag\Framework\Http\Contract\IgnoreFirstLoadInterface;

/**
 * A prop whose closure runs only when the key is requested in a partial reload.
 *
 * The Inertia v3 `optional()` semantics: the wrapped callable never executes on
 * a full page load nor on a normal Inertia visit. It runs once, and only once,
 * when the request is a partial reload for the owning component and the prop key
 * appears in `X-Inertia-Partial-Data`. Build one through
 * {@see InertiaAdapter::optional()} and pass it as a prop value:
 *
 * ```php
 * return InertiaAdapter::render('Dashboard', [
 *     'stats' => InertiaAdapter::optional(fn () => $this->expensiveStats()),
 * ]);
 * ```
 *
 * @internal
 */
final readonly class OptionalProp implements IgnoreFirstLoadInterface
{
    public function __construct(private Closure $callback) {}

    public function resolve(): mixed
    {
        return ($this->callback)();
    }
}
