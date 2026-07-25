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
 * Route-middleware fixture standing in for a chain entry declared on the route
 * defaults (a registrar/group declaration) rather than by a `#[Middleware]`
 * attribute.
 *
 * Stamps `group` on the X-Chain header after the chain resolves, so its position
 * relative to {@see OuterMiddleware} (class attribute) and {@see InnerMiddleware}
 * (method attribute) is observable — and so a duplicate declaration would show up
 * as a second `group` stamp.
 *
 * @internal
 */
final class GroupMiddleware implements RouteMiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        $response = $next($request);
        $response->headers->set('X-Chain', trim(($response->headers->get('X-Chain') ?? '') . ' group'));

        return $response;
    }
}
