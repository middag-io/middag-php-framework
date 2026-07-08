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
        $this->assertSame('emergency', LogLevel::EMERGENCY->value);
        $this->assertSame('alert', LogLevel::ALERT->value);
        $this->assertSame('critical', LogLevel::CRITICAL->value);
        $this->assertSame('error', LogLevel::ERROR->value);
        $this->assertSame('warning', LogLevel::WARNING->value);
        $this->assertSame('notice', LogLevel::NOTICE->value);
        $this->assertSame('info', LogLevel::INFO->value);
        $this->assertSame('debug', LogLevel::DEBUG->value);
    }

    #[Test]
    public function severityReturnsCorrectNumericValues(): void
    {
        $this->assertSame(0, LogLevel::EMERGENCY->severity());
        $this->assertSame(1, LogLevel::ALERT->severity());
        $this->assertSame(2, LogLevel::CRITICAL->severity());
        $this->assertSame(3, LogLevel::ERROR->severity());
        $this->assertSame(4, LogLevel::WARNING->severity());
        $this->assertSame(5, LogLevel::NOTICE->severity());
        $this->assertSame(6, LogLevel::INFO->severity());
        $this->assertSame(7, LogLevel::DEBUG->severity());
    }

    #[Test]
    public function severityIsMonotonicallyIncreasingFromMostToLeastSevere(): void
    {
        $orderedLevels = [
            LogLevel::EMERGENCY,
            LogLevel::ALERT,
            LogLevel::CRITICAL,
            LogLevel::ERROR,
            LogLevel::WARNING,
            LogLevel::NOTICE,
            LogLevel::INFO,
            LogLevel::DEBUG,
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
                LogLevel::EMERGENCY->severity(),
                $level->severity(),
            );
        }
    }

    #[Test]
    public function debugIsTheLeastSevere(): void
    {
        foreach (LogLevel::cases() as $level) {
            $this->assertLessThanOrEqual(
                LogLevel::DEBUG->severity(),
                $level->severity(),
            );
        }
    }

    #[Test]
    public function canBeCreatedFromString(): void
    {
        $this->assertSame(LogLevel::EMERGENCY, LogLevel::from('emergency'));
        $this->assertSame(LogLevel::DEBUG, LogLevel::from('debug'));
    }

    #[Test]
    public function tryFromReturnsNullForUnknown(): void
    {
        $this->assertNull(LogLevel::tryFrom('verbose'));
        $this->assertNull(LogLevel::tryFrom(''));
    }
}
