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
        self::assertSame(0, DebugMode::DISABLED->value);
        self::assertSame(1, DebugMode::NORMAL->value);
        self::assertSame(2, DebugMode::FULL->value);
        self::assertCount(3, DebugMode::cases());
    }

    #[Test]
    public function fromResolvesBackingValue(): void
    {
        self::assertSame(DebugMode::NORMAL, DebugMode::from(1));
        self::assertSame(DebugMode::FULL, DebugMode::tryFrom(2));
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
        yield 'disabled satisfied by disabled config' => [DebugMode::DISABLED, 0, true];

        yield 'normal not satisfied by disabled config' => [DebugMode::NORMAL, 0, false];

        yield 'normal satisfied by normal config' => [DebugMode::NORMAL, 1, true];

        yield 'normal satisfied by full config' => [DebugMode::NORMAL, 2, true];

        yield 'full not satisfied by normal config' => [DebugMode::FULL, 1, false];

        yield 'full satisfied by full config' => [DebugMode::FULL, 2, true];
    }
}
