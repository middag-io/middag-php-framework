<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Http\Attribute;

use Attribute;

/**
 * Declares per-route middleware for a controller action.
 *
 * Applied to methods (per-action) or classes (default for all actions). Both
 * levels stack: class-level middleware run outermost, method-level innermost,
 * each in declaration order. The attribute is repeatable, so several
 * declarations accumulate.
 *
 * Each entry is a class-string resolved from the container (falling back to a
 * zero-argument `new` when unregistered) and MUST implement
 * {@see Middag\Framework\Http\Contract\RouteMiddlewareInterface}. The kernel
 * composes them around the action, where a middleware may short-circuit the
 * request or decorate the response.
 *
 * ```php
 * #[Middleware(RateLimitMiddleware::class)]
 * public function store(): Response { ... }
 * ```
 *
 * @api
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final readonly class Middleware
{
    /** @var list<class-string> */
    public array $middleware;

    /**
     * @param class-string ...$middleware Route middleware classes, applied in order
     */
    public function __construct(string ...$middleware)
    {
        $this->middleware = array_values($middleware);
    }
}
