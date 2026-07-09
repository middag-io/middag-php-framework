<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Shared\Enum;

use Middag\Framework\Shared\Enum\DebugMode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DebugMode::class)]
final class DebugModeTest extends TestCase
{
    #[Test]
    public function casesCarryTheExpectedIntegerValues(): void
    {
        self::assertSame(0, DebugMode::Disabled->value);
        self::assertSame(1, DebugMode::Normal->value);
        self::assertSame(2, DebugMode::Full->value);
        self::assertCount(3, DebugMode::cases());
    }

    #[Test]
    public function fromResolvesBackingValue(): void
    {
        self::assertSame(DebugMode::Normal, DebugMode::from(1));
        self::assertSame(DebugMode::Full, DebugMode::tryFrom(2));
        self::assertNull(DebugMode::tryFrom(99));
    }

    #[Test]
    #[DataProvider('isEnabledByProvider')]
    public function isEnabledByComparesAgainstConfigValue(DebugMode $level, int $configValue, bool $expected): void
    {
        self::assertSame($expected, $level->isEnabledBy($configValue));
    }

    /**
     * @return iterable<string, array{DebugMode, int, bool}>
     */
    public static function isEnabledByProvider(): iterable
    {
        yield 'disabled satisfied by disabled config' => [DebugMode::Disabled, 0, true];

        yield 'normal not satisfied by disabled config' => [DebugMode::Normal, 0, false];

        yield 'normal satisfied by normal config' => [DebugMode::Normal, 1, true];

        yield 'normal satisfied by full config' => [DebugMode::Normal, 2, true];

        yield 'full not satisfied by normal config' => [DebugMode::Full, 1, false];

        yield 'full satisfied by full config' => [DebugMode::Full, 2, true];
    }
}
