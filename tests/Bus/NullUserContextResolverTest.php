<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Bus;

use Middag\Framework\Bus\Contract\UserContextResolverInterface;
use Middag\Framework\Bus\NullUserContextResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(NullUserContextResolver::class)]
final class NullUserContextResolverTest extends TestCase
{
    #[Test]
    public function alwaysResolvesToNoUser(): void
    {
        $resolver = new NullUserContextResolver();

        self::assertNull($resolver->getCurrentUserId());
    }

    #[Test]
    public function fulfilsTheResolverContract(): void
    {
        self::assertInstanceOf(
            UserContextResolverInterface::class,
            new NullUserContextResolver(),
        );
    }
}
