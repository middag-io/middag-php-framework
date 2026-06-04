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
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-middleware fixture that short-circuits with 403 without calling `$next`,
 * proving a middleware can block before the action ever runs.
 *
 * @internal
 */
final class BlockMiddleware implements RouteMiddlewareInterface
{
    public function process(Request $request, callable $next): JsonResponse
    {
        return new JsonResponse(['blocked' => true], Response::HTTP_FORBIDDEN);
    }
}
