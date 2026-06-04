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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Per-route middleware that wraps a single controller action.
 *
 * Distinct from the PSR-15 pipeline in {@see Middag\Framework\Http\Middleware} —
 * those run globally in front of the kernel and speak PSR-7. This contract is
 * HttpFoundation-native so it composes directly with the kernel's internal
 * Request → Response dispatch and with action signatures, without a per-route
 * PSR-7 bridge.
 *
 * Declare middleware on an action (or controller class) with the
 * {@see Middag\Framework\Http\Attribute\Middleware} attribute; the kernel
 * resolves each entry from the container and composes them around the action,
 * outermost first (class-level before method-level). A middleware may inspect or
 * decorate the request, short-circuit by returning its own {@see Response}, or
 * call `$next($request)` to continue the chain and post-process the result.
 *
 * Note: the action's arguments are resolved before the chain runs, so a request
 * mutated mid-chain reaches later middleware and shapes the response, but not the
 * already-resolved action arguments.
 *
 * @api
 */
interface RouteMiddlewareInterface
{
    /**
     * Process the request, optionally delegating to the rest of the chain.
     *
     * @param callable(Request): Response $next the next middleware, or the action
     */
    public function process(Request $request, callable $next): Response;
}
