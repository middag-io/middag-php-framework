<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Http;

use Middag\Framework\Http\Response\CacheHeaderApplier;
use Middag\Framework\Tests\Http\Fixture\CachedController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * #[Cache] HTTP caching headers are applied to a cacheable response, ignored
 * otherwise.
 *
 * @internal
 */
#[CoversClass(CacheHeaderApplier::class)]
final class CacheHeaderApplierTest extends TestCase
{
    public function testAppliesCacheHeadersFromTheAttribute(): void
    {
        $response = new Response('ok');

        CacheHeaderApplier::apply(new CachedController(), 'fresh', $response);

        self::assertSame(3600, $response->getMaxAge());
        self::assertTrue($response->headers->hasCacheControlDirective('public'));
        self::assertContains('Accept-Encoding', $response->getVary());
    }

    public function testNoOpWhenActionHasNoAttribute(): void
    {
        $response = new Response('ok');

        CacheHeaderApplier::apply(new CachedController(), 'plain', $response);

        self::assertNull($response->getMaxAge());
    }

    public function testNoOpForNonCacheableStatus(): void
    {
        $response = new Response('', 500);

        CacheHeaderApplier::apply(new CachedController(), 'fresh', $response);

        self::assertNull($response->getMaxAge());
    }
}
