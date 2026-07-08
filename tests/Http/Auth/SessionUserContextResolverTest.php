<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Http\Auth;

use Middag\Framework\Bus\Contract\UserContextResolverInterface;
use Middag\Framework\Http\Auth\SessionAuthenticator;
use Middag\Framework\Http\Auth\SessionUserContextResolver;
use Middag\Framework\Http\Session\ArraySession;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(SessionUserContextResolver::class)]
final class SessionUserContextResolverTest extends TestCase
{
    #[Test]
    public function satisfiesTheBusContract(): void
    {
        $resolver = new SessionUserContextResolver(new SessionAuthenticator(new ArraySession()));

        $this->assertInstanceOf(UserContextResolverInterface::class, $resolver);
    }

    #[Test]
    public function returnsNullWhenUnauthenticated(): void
    {
        $resolver = new SessionUserContextResolver(new SessionAuthenticator(new ArraySession()));

        $this->assertNull($resolver->getCurrentUserId());
    }

    #[Test]
    public function returnsAuthenticatedUserId(): void
    {
        $auth = new SessionAuthenticator(new ArraySession());
        $auth->login(1234);

        $this->assertSame(1234, (new SessionUserContextResolver($auth))->getCurrentUserId());
    }
}
