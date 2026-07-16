<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Shared\Util;

use Middag\Framework\Shared\Util\Arr;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Arr::class)]
final class ArrTest extends TestCase
{
    #[Test]
    public function addsMissingKeyWithNonNullValue(): void
    {
        self::assertSame(['a' => 1, 'b' => 2], Arr::mergePreferNonNull(['a' => 1], ['b' => 2]));
    }

    #[Test]
    public function addsMissingKeyEvenWhenTheNewValueIsNull(): void
    {
        self::assertSame(['a' => 1, 'b' => null], Arr::mergePreferNonNull(['a' => 1], ['b' => null]));
    }

    #[Test]
    public function overwritesExistingKeyWithNonNullValue(): void
    {
        self::assertSame(['a' => 9], Arr::mergePreferNonNull(['a' => 1], ['a' => 9]));
    }

    #[Test]
    public function keepsExistingValueWhenTheNewValueIsNull(): void
    {
        self::assertSame(['a' => 1], Arr::mergePreferNonNull(['a' => 1], ['a' => null]));
    }

    #[Test]
    public function overwritesWithFalseBecauseOnlyNullIsSkipped(): void
    {
        self::assertSame(['flag' => false], Arr::mergePreferNonNull(['flag' => true], ['flag' => false]));
    }

    #[Test]
    public function returnsTheDefaultUnchangedForAnEmptyOverrideArray(): void
    {
        self::assertSame(['a' => 1, 'b' => 2], Arr::mergePreferNonNull(['a' => 1, 'b' => 2], []));
    }
}
