<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Http\Fixture;

use Middag\Framework\Http\Contract\RouteMiddlewareInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-middleware fixture: stamps `inner` on the X-Chain header after the action
 * resolves. Method-level, so it runs INSIDE {@see OuterMiddleware} (class-level).
 *
 * @internal
 */
final class InnerMiddleware implements RouteMiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        $response = $next($request);
        $response->headers->set('X-Chain', trim(($response->headers->get('X-Chain') ?? '') . ' inner'));

        return $response;
    }
}
