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
use Symfony\Component\HttpKernel\Attribute\Cache;

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

    public function testPrivateVisibilityIsApplied(): void
    {
        $controller = new class {
            #[Cache(public: false)]
            public function action(): void {}
        };
        $response = new Response('ok');

        CacheHeaderApplier::apply($controller, 'action', $response);

        self::assertTrue($response->headers->hasCacheControlDirective('private'));
    }

    public function testSharedMaxAgeMustRevalidateAndExpiresAreApplied(): void
    {
        $controller = new class {
            #[Cache(expires: '2030-01-01 00:00:00', smaxage: 60, mustRevalidate: true)]
            public function action(): void {}
        };
        $response = new Response('ok');

        CacheHeaderApplier::apply($controller, 'action', $response);

        self::assertSame('60', $response->headers->getCacheControlDirective('s-maxage'));
        self::assertTrue($response->headers->hasCacheControlDirective('must-revalidate'));
        self::assertNotNull($response->getExpires());
        self::assertSame('2030', $response->getExpires()->format('Y'));
    }

    public function testMaxAgeFromNumericStringIsCoerced(): void
    {
        $controller = new class {
            #[Cache(maxage: '120')]
            public function action(): void {}
        };
        $response = new Response('ok');

        CacheHeaderApplier::apply($controller, 'action', $response);

        self::assertSame(120, $response->getMaxAge());
    }

    public function testMaxAgeFromRelativeDateStringIsResolvedToSeconds(): void
    {
        $controller = new class {
            #[Cache(maxage: '+1 hour')]
            public function action(): void {}
        };
        $response = new Response('ok');

        CacheHeaderApplier::apply($controller, 'action', $response);

        // strtotime('+1 hour') - now ≈ 3600, allowing a few seconds of clock drift.
        self::assertGreaterThan(3590, $response->getMaxAge());
        self::assertLessThanOrEqual(3600, $response->getMaxAge());
    }

    public function testClassLevelAttributeIsUsedWhenTheMethodHasNone(): void
    {
        $controller = new #[Cache(maxage: 300)] class {
            public function action(): void {}
        };
        $response = new Response('ok');

        CacheHeaderApplier::apply($controller, 'action', $response);

        self::assertSame(300, $response->getMaxAge());
    }
}
