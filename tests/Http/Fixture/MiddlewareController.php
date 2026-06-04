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
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Controller fixture for the #[Middleware] per-route chain tests.
 *
 * Class-level {@see OuterMiddleware} applies to every action; method-level
 * middleware run inside it.
 *
 * @internal
 */
#[Middleware(OuterMiddleware::class)]
final class MiddlewareController
{
    #[Middleware(InnerMiddleware::class)]
    public function run(): JsonResponse
    {
        return new JsonResponse(['ok' => true]);
    }

    #[Middleware(BlockMiddleware::class)]
    public function blocked(): JsonResponse
    {
        // Never reached: BlockMiddleware short-circuits before the action.
        return new JsonResponse(['ok' => true]);
    }
}
