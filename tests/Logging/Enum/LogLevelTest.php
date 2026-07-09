<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Logging\Enum;

use Middag\Framework\Logging\Enum\LogLevel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(LogLevel::class)]
final class LogLevelTest extends TestCase
{
    #[Test]
    public function allExpectedCasesExist(): void
    {
        $values = array_column(LogLevel::cases(), 'value');

        $this->assertContains('emergency', $values);
        $this->assertContains('alert', $values);
        $this->assertContains('critical', $values);
        $this->assertContains('error', $values);
        $this->assertContains('warning', $values);
        $this->assertContains('notice', $values);
        $this->assertContains('info', $values);
        $this->assertContains('debug', $values);
    }

    #[Test]
    public function totalLevelCount(): void
    {
        $this->assertCount(8, LogLevel::cases());
    }

    #[Test]
    public function enumValuesMatchPsr3Strings(): void
    {
        $this->assertSame('emergency', LogLevel::Emergency->value);
        $this->assertSame('alert', LogLevel::Alert->value);
        $this->assertSame('critical', LogLevel::Critical->value);
        $this->assertSame('error', LogLevel::Error->value);
        $this->assertSame('warning', LogLevel::Warning->value);
        $this->assertSame('notice', LogLevel::Notice->value);
        $this->assertSame('info', LogLevel::Info->value);
        $this->assertSame('debug', LogLevel::Debug->value);
    }

    #[Test]
    public function severityReturnsCorrectNumericValues(): void
    {
        $this->assertSame(0, LogLevel::Emergency->severity());
        $this->assertSame(1, LogLevel::Alert->severity());
        $this->assertSame(2, LogLevel::Critical->severity());
        $this->assertSame(3, LogLevel::Error->severity());
        $this->assertSame(4, LogLevel::Warning->severity());
        $this->assertSame(5, LogLevel::Notice->severity());
        $this->assertSame(6, LogLevel::Info->severity());
        $this->assertSame(7, LogLevel::Debug->severity());
    }

    #[Test]
    public function severityIsMonotonicallyIncreasingFromMostToLeastSevere(): void
    {
        $orderedLevels = [
            LogLevel::Emergency,
            LogLevel::Alert,
            LogLevel::Critical,
            LogLevel::Error,
            LogLevel::Warning,
            LogLevel::Notice,
            LogLevel::Info,
            LogLevel::Debug,
        ];
        $counter = count($orderedLevels);

        for ($i = 1; $i < $counter; ++$i) {
            $this->assertGreaterThan(
                $orderedLevels[$i - 1]->severity(),
                $orderedLevels[$i]->severity(),
                sprintf(
                    '%s severity (%d) should be greater than %s severity (%d)',
                    $orderedLevels[$i]->name,
                    $orderedLevels[$i]->severity(),
                    $orderedLevels[$i - 1]->name,
                    $orderedLevels[$i - 1]->severity(),
                ),
            );
        }
    }

    #[Test]
    public function emergencyIsTheMostSevere(): void
    {
        foreach (LogLevel::cases() as $level) {
            $this->assertGreaterThanOrEqual(
                LogLevel::Emergency->severity(),
                $level->severity(),
            );
        }
    }

    #[Test]
    public function debugIsTheLeastSevere(): void
    {
        foreach (LogLevel::cases() as $level) {
            $this->assertLessThanOrEqual(
                LogLevel::Debug->severity(),
                $level->severity(),
            );
        }
    }

    #[Test]
    public function canBeCreatedFromString(): void
    {
        $this->assertSame(LogLevel::Emergency, LogLevel::from('emergency'));
        $this->assertSame(LogLevel::Debug, LogLevel::from('debug'));
    }

    #[Test]
    public function tryFromReturnsNullForUnknown(): void
    {
        $this->assertNull(LogLevel::tryFrom('verbose'));
        $this->assertNull(LogLevel::tryFrom(''));
    }
}
