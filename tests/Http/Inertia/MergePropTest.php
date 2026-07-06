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

use Middag\Framework\Http\Contract\MergeableInterface;
use Middag\Framework\Http\Inertia\MergeProp;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * A prop the Inertia v3 client merges into its existing value instead of
 * replacing it. The value object records the raw/closure value plus the
 * shallow-vs-deep flag and the pagination match keys.
 *
 * @internal
 */
#[CoversClass(MergeProp::class)]
final class MergePropTest extends TestCase
{
    #[Test]
    public function resolveReturnsRawValueWhenItIsNotAClosure(): void
    {
        $prop = new MergeProp(['a', 'b']);

        self::assertSame(['a', 'b'], $prop->resolve());
    }

    #[Test]
    public function resolveInvokesTheWrappedClosureAndReturnsItsResult(): void
    {
        $calls = 0;
        $prop = new MergeProp(static function () use (&$calls): string {
            ++$calls;

            return 'lazy-value';
        });

        self::assertSame('lazy-value', $prop->resolve());
        self::assertSame(1, $calls, 'the closure must be invoked exactly once per resolve()');
    }

    #[Test]
    public function deepDefaultsToShallowMerge(): void
    {
        $prop = new MergeProp('value');

        self::assertFalse($prop->deep());
    }

    #[Test]
    public function deepReflectsTheConstructorFlag(): void
    {
        $prop = new MergeProp('value', deep: true);

        self::assertTrue($prop->deep());
    }

    #[Test]
    public function matchOnDefaultsToAnEmptyList(): void
    {
        $prop = new MergeProp('value');

        self::assertSame([], $prop->matchOn());
    }

    #[Test]
    public function matchOnReturnsTheConstructorMatchKeys(): void
    {
        $prop = new MergeProp('value', matchOn: ['id', 'slug']);

        self::assertSame(['id', 'slug'], $prop->matchOn());
    }

    #[Test]
    public function implementsTheMergeableContract(): void
    {
        self::assertInstanceOf(MergeableInterface::class, new MergeProp('value'));
    }
}
