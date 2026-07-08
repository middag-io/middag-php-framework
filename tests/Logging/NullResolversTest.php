<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Logging;

use Middag\Framework\Bus\NullUserContextResolver;
use Middag\Framework\Logging\NullActorResolver;
use Middag\Framework\Logging\NullOriginResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(NullActorResolver::class)]
#[CoversClass(NullOriginResolver::class)]
#[CoversClass(NullUserContextResolver::class)]
final class NullResolversTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SERVER['REMOTE_ADDR']);
    }

    public function testActorResolverAlwaysReturnsSystem(): void
    {
        self::assertSame('system', (new NullActorResolver())->resolve());
    }

    public function testOriginResolverReturnsCliUnderCliSapi(): void
    {
        if (PHP_SAPI !== 'cli') {
            self::markTestSkipped('only meaningful under CLI SAPI');
        }

        self::assertSame('cli', (new NullOriginResolver())->resolve());
    }

    public function testOriginResolverReturnsIpWhenRemoteAddrSet(): void
    {
        if (PHP_SAPI === 'cli') {
            self::markTestSkipped('PHP_SAPI=cli short-circuits ip branch');
        }

        $_SERVER['REMOTE_ADDR'] = '203.0.113.7';
        self::assertSame('ip:203.0.113.7', (new NullOriginResolver())->resolve());
    }

    public function testUserContextResolverReturnsNull(): void
    {
        self::assertNull((new NullUserContextResolver())->getCurrentUserId());
    }
}
