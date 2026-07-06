<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Http\Inertia;

use Middag\Framework\Http\Contract\IgnoreFirstLoadInterface;
use Middag\Framework\Http\Inertia\OptionalProp;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * An Inertia `optional()` prop: a value object wrapping a closure that only runs
 * when the owning key is requested in a partial reload. The wrapper itself is a
 * thin lazy holder — {@see OptionalProp::resolve()} invokes the callback on
 * demand and returns whatever it produces.
 *
 * @internal
 */
#[CoversClass(OptionalProp::class)]
final class OptionalPropTest extends TestCase
{
    #[Test]
    public function resolveInvokesTheWrappedCallbackAndReturnsItsValue(): void
    {
        $prop = new OptionalProp(static fn (): array => ['stats' => 42]);

        self::assertSame(['stats' => 42], $prop->resolve());
    }

    #[Test]
    public function theCallbackIsNotInvokedUntilResolveIsCalled(): void
    {
        $calls = 0;
        $prop = new OptionalProp(static function () use (&$calls): string {
            ++$calls;

            return 'value';
        });

        self::assertSame(0, $calls, 'construction must not run the closure');

        $prop->resolve();

        self::assertSame(1, $calls);
    }

    #[Test]
    public function resolveReInvokesTheCallbackOnEachCall(): void
    {
        $calls = 0;
        $prop = new OptionalProp(static function () use (&$calls): int {
            return ++$calls;
        });

        self::assertSame(1, $prop->resolve());
        self::assertSame(2, $prop->resolve());
    }

    #[Test]
    public function resolvePreservesNullReturnedByTheCallback(): void
    {
        $prop = new OptionalProp(static fn (): mixed => null);

        self::assertNull($prop->resolve());
    }

    #[Test]
    public function resolvePropagatesAnExceptionThrownByTheCallback(): void
    {
        $prop = new OptionalProp(static function (): never {
            throw new RuntimeException('boom');
        });

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('boom');

        $prop->resolve();
    }

    #[Test]
    public function itIsAnIgnoreFirstLoadProp(): void
    {
        $prop = new OptionalProp(static fn (): string => 'x');

        self::assertInstanceOf(IgnoreFirstLoadInterface::class, $prop);
    }
}
