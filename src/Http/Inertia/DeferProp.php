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
use Middag\Framework\Http\Contract\DeferrableInterface;

/**
 * A prop the client fetches automatically after the initial render (Inertia v3 `defer`).
 *
 * On the initial response the wrapped callable never runs; the prop is absent
 * from `props` and its key is announced under `deferredProps[<group>]`. The
 * Inertia client then issues one partial reload per group, on which the callable
 * resolves through the same lazy path as `optional()`. Build one through
 * {@see InertiaAdapter::defer()}:
 *
 * ```php
 * return InertiaAdapter::render('Dashboard', [
 *     'teams' => InertiaAdapter::defer(fn () => $this->teams(), 'attributes'),
 * ]);
 * ```
 *
 * With `rescue: true`, a failure during resolution drops the prop (reported in
 * `rescuedProps`) instead of failing the whole request.
 *
 * @internal
 */
final readonly class DeferProp implements DeferrableInterface
{
    public function __construct(
        private Closure $callback,
        private string $group = 'default',
        private bool $rescue = false,
    ) {}

    public function resolve(): mixed
    {
        return ($this->callback)();
    }

    public function group(): string
    {
        return $this->group;
    }

    public function rescue(): bool
    {
        return $this->rescue;
    }
}
