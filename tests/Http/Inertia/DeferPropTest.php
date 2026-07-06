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

use Middag\Framework\Http\Contract\DeferrableInterface;
use Middag\Framework\Http\Inertia\DeferProp;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * A prop deferred to a post-mount partial reload: it wraps a callable that is
 * only invoked on {@see DeferProp::resolve()} and announces its reload group and
 * rescue policy so the Inertia response can bucket and (optionally) drop it.
 *
 * @internal
 */
#[CoversClass(DeferProp::class)]
final class DeferPropTest extends TestCase
{
    #[Test]
    public function isADeferrableProp(): void
    {
        $prop = new DeferProp(static fn (): int => 1);

        $this->assertInstanceOf(DeferrableInterface::class, $prop);
    }

    #[Test]
    public function resolveInvokesTheWrappedCallbackAndReturnsItsValue(): void
    {
        $prop = new DeferProp(static fn (): string => 'resolved');

        $this->assertSame('resolved', $prop->resolve());
    }

    #[Test]
    public function resolveRunsTheCallbackOnEveryCall(): void
    {
        $calls = 0;
        $prop = new DeferProp(static function () use (&$calls): int {
            ++$calls;

            return $calls;
        });

        $this->assertSame(1, $prop->resolve());
        $this->assertSame(2, $prop->resolve());
    }

    #[Test]
    public function groupDefaultsToDefault(): void
    {
        $prop = new DeferProp(static fn (): int => 1);

        $this->assertSame('default', $prop->group());
    }

    #[Test]
    public function groupReturnsTheConfiguredGroup(): void
    {
        $prop = new DeferProp(static fn (): int => 1, 'attributes');

        $this->assertSame('attributes', $prop->group());
    }

    #[Test]
    public function rescueDefaultsToFalse(): void
    {
        $prop = new DeferProp(static fn (): int => 1);

        $this->assertFalse($prop->rescue());
    }

    #[Test]
    public function rescueReturnsTheConfiguredFlag(): void
    {
        $prop = new DeferProp(static fn (): int => 1, 'attributes', true);

        $this->assertTrue($prop->rescue());
    }
}
