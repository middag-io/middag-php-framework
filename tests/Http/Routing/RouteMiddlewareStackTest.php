<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Http\Routing;

use InvalidArgumentException;
use Middag\Framework\Http\Routing\RouteMiddlewareStack;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * The merge algebra of a route's middleware chain: route defaults first
 * (outermost), attribute ids appended inside them, duplicates collapsed to their
 * outermost position, exclusions subtracted from the whole merged list, and a
 * malformed default rejected instead of silently dropped.
 *
 * @internal
 */
#[CoversClass(RouteMiddlewareStack::class)]
final class RouteMiddlewareStackTest extends TestCase
{
    #[Test]
    public function aRouteDeclaringNeitherDefaultYieldsAnEmptyChain(): void
    {
        $stack = RouteMiddlewareStack::fromRouteDefaults('home', ['_controller' => 'x', 'id' => '42']);

        self::assertSame([], $stack->ids());
        self::assertSame('home', $stack->routeName);
    }

    #[Test]
    public function theDeclaredOrderOfTheRouteDefaultIsPreserved(): void
    {
        $stack = RouteMiddlewareStack::fromRouteDefaults('admin.index', [
            RouteMiddlewareStack::MIDDLEWARE_DEFAULT => ['auth.mw', 'throttle.mw', 'csrf.mw'],
        ]);

        self::assertSame(['auth.mw', 'throttle.mw', 'csrf.mw'], $stack->ids());
    }

    #[Test]
    public function appendedAttributeIdsRunInsideTheRouteDefaultOnes(): void
    {
        $stack = RouteMiddlewareStack::fromRouteDefaults('admin.store', [
            RouteMiddlewareStack::MIDDLEWARE_DEFAULT => ['group.mw'],
        ]);

        // Route defaults are the broadest scope, so they stay outermost; the
        // attribute chain (class then method) folds in after them.
        self::assertSame(
            ['group.mw', 'class.mw', 'method.mw'],
            $stack->append(['class.mw', 'method.mw'])->ids(),
        );
    }

    #[Test]
    public function anIdDeclaredByBothSourcesRunsOnceAtItsOutermostPosition(): void
    {
        $stack = RouteMiddlewareStack::fromRouteDefaults('admin.store', [
            RouteMiddlewareStack::MIDDLEWARE_DEFAULT => ['auth.mw', 'throttle.mw'],
        ]);

        // `auth.mw` is named by the defaults AND by the attribute: it keeps the
        // outer slot the defaults gave it and never runs twice.
        self::assertSame(
            ['auth.mw', 'throttle.mw', 'audit.mw'],
            $stack->append(['auth.mw', 'audit.mw'])->ids(),
        );
    }

    #[Test]
    public function anIdRepeatedWithinTheRouteDefaultCollapsesToo(): void
    {
        $stack = RouteMiddlewareStack::fromRouteDefaults('admin.index', [
            RouteMiddlewareStack::MIDDLEWARE_DEFAULT => ['auth.mw', 'throttle.mw', 'auth.mw'],
        ]);

        self::assertSame(['auth.mw', 'throttle.mw'], $stack->ids());
    }

    #[Test]
    public function anExclusionRemovesAnIdInheritedFromTheRouteDefault(): void
    {
        $stack = RouteMiddlewareStack::fromRouteDefaults('admin.webhook', [
            RouteMiddlewareStack::MIDDLEWARE_DEFAULT => ['auth.mw', 'csrf.mw'],
            RouteMiddlewareStack::WITHOUT_MIDDLEWARE_DEFAULT => ['csrf.mw'],
        ]);

        self::assertSame(['auth.mw'], $stack->ids());
    }

    #[Test]
    public function anExclusionAlsoRemovesAnIdContributedByAnAttribute(): void
    {
        // The attribute half of the chain is invisible at registration time, which
        // is exactly why the exclusion list travels alongside the inclusion list
        // instead of being pre-subtracted by the registrar.
        $stack = RouteMiddlewareStack::fromRouteDefaults('admin.webhook', [
            RouteMiddlewareStack::WITHOUT_MIDDLEWARE_DEFAULT => ['csrf.mw'],
        ]);

        self::assertSame(['auth.mw'], $stack->append(['csrf.mw', 'auth.mw'])->ids());
    }

    #[Test]
    public function excludingEveryDeclaredIdYieldsAnEmptyChain(): void
    {
        $stack = RouteMiddlewareStack::fromRouteDefaults('admin.raw', [
            RouteMiddlewareStack::MIDDLEWARE_DEFAULT => ['auth.mw'],
            RouteMiddlewareStack::WITHOUT_MIDDLEWARE_DEFAULT => ['auth.mw', 'csrf.mw'],
        ]);

        self::assertSame([], $stack->ids());
    }

    #[Test]
    public function appendingNothingReturnsTheSameInstance(): void
    {
        $stack = RouteMiddlewareStack::fromRouteDefaults('home', [
            RouteMiddlewareStack::MIDDLEWARE_DEFAULT => ['auth.mw'],
        ]);

        self::assertSame($stack, $stack->append([]));
    }

    #[Test]
    public function appendingLeavesTheOriginalStackUntouched(): void
    {
        $stack = RouteMiddlewareStack::fromRouteDefaults('home', [
            RouteMiddlewareStack::MIDDLEWARE_DEFAULT => ['auth.mw'],
        ]);

        $stack->append(['audit.mw']);

        self::assertSame(['auth.mw'], $stack->ids());
    }

    #[Test]
    public function aMiddlewareDefaultThatIsNotAListIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Route "admin.index" declares a malformed "_middleware" route default');

        RouteMiddlewareStack::fromRouteDefaults('admin.index', [
            RouteMiddlewareStack::MIDDLEWARE_DEFAULT => 'auth.mw',
        ]);
    }

    #[Test]
    public function aNonStringMiddlewareIdIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Route "admin.index" declares a malformed middleware id in the "_middleware" route default: expected a non-empty string, got stdClass.');

        RouteMiddlewareStack::fromRouteDefaults('admin.index', [
            RouteMiddlewareStack::MIDDLEWARE_DEFAULT => ['auth.mw', new stdClass()],
        ]);
    }

    #[Test]
    public function anEmptyMiddlewareIdIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('got an empty string');

        RouteMiddlewareStack::fromRouteDefaults('admin.index', [
            RouteMiddlewareStack::MIDDLEWARE_DEFAULT => ['   '],
        ]);
    }

    #[Test]
    public function aMalformedExclusionListIsRejectedToo(): void
    {
        // A silently ignored exclusion would keep a middleware the developer
        // explicitly removed — the opposite failure, equally wrong.
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('malformed "_without_middleware" route default');

        RouteMiddlewareStack::fromRouteDefaults('admin.index', [
            RouteMiddlewareStack::WITHOUT_MIDDLEWARE_DEFAULT => 42,
        ]);
    }
}
