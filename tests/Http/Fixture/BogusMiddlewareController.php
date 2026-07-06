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

use Middag\Framework\Http\Attribute\Middleware;
use stdClass;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Controller fixture whose #[Middleware] names a class that does NOT implement
 * RouteMiddlewareInterface — so the kernel must reject it with a RuntimeException.
 *
 * @internal
 */
#[Middleware(stdClass::class)]
final class BogusMiddlewareController
{
    public function run(): JsonResponse
    {
        return new JsonResponse(['ok' => true]);
    }
}
