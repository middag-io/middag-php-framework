<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Bus\Command;

use InvalidArgumentException;
use Middag\Framework\Bus\Command\WorkerLimits;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(WorkerLimits::class)]
final class WorkerLimitsTest extends TestCase
{
    #[Test]
    public function unlimitedHasNoStopCondition(): void
    {
        $limits = WorkerLimits::unlimited();

        self::assertFalse($limits->messageLimitReached(PHP_INT_MAX));
        self::assertFalse($limits->timeLimitReached(PHP_FLOAT_MAX));
        self::assertFalse($limits->memoryLimitReached(PHP_INT_MAX));
    }

    #[Test]
    public function messageLimitReachedAtTheBoundary(): void
    {
        $limits = new WorkerLimits(messageLimit: 5);

        self::assertFalse($limits->messageLimitReached(4));
        self::assertTrue($limits->messageLimitReached(5));
        self::assertTrue($limits->messageLimitReached(6));
    }

    #[Test]
    public function timeLimitReachedAtTheBoundary(): void
    {
        $limits = new WorkerLimits(timeLimitSeconds: 10);

        self::assertFalse($limits->timeLimitReached(9.9));
        self::assertTrue($limits->timeLimitReached(10.0));
        self::assertTrue($limits->timeLimitReached(10.1));
    }

    #[Test]
    public function memoryLimitReachedAtTheBoundary(): void
    {
        $limits = new WorkerLimits(memoryLimitBytes: 1024);

        self::assertFalse($limits->memoryLimitReached(1023));
        self::assertTrue($limits->memoryLimitReached(1024));
        self::assertTrue($limits->memoryLimitReached(1025));
    }

    #[Test]
    #[DataProvider('humanMemoryLimits')]
    public function fromCliOptionsParsesHumanMemoryLimitGrammar(string $input, int $expectedBytes): void
    {
        $limits = WorkerLimits::fromCliOptions(memoryLimit: $input);

        self::assertSame($expectedBytes, $limits->memoryLimitBytes);
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function humanMemoryLimits(): iterable
    {
        yield 'plain bytes' => ['1000', 1000];

        yield 'kibibytes' => ['512K', 512 * 1024];

        yield 'mebibytes, uppercase' => ['256M', 256 * 1024 * 1024];

        yield 'mebibytes, lowercase' => ['256m', 256 * 1024 * 1024];

        yield 'gibibytes' => ['1G', 1024 * 1024 * 1024];

        yield 'tolerates surrounding whitespace' => [' 64M ', 64 * 1024 * 1024];
    }

    #[Test]
    public function fromCliOptionsTreatsNullOrEmptyMemoryLimitAsUnlimited(): void
    {
        self::assertNull(WorkerLimits::fromCliOptions()->memoryLimitBytes);
        self::assertNull(WorkerLimits::fromCliOptions(memoryLimit: '')->memoryLimitBytes);
    }

    #[Test]
    public function fromCliOptionsPassesMessageAndTimeLimitThrough(): void
    {
        $limits = WorkerLimits::fromCliOptions(messageLimit: 100, timeLimitSeconds: 3600);

        self::assertSame(100, $limits->messageLimit);
        self::assertSame(3600, $limits->timeLimitSeconds);
    }

    #[Test]
    public function rejectsAnUnparseableMemoryLimit(): void
    {
        $this->expectException(InvalidArgumentException::class);

        WorkerLimits::fromCliOptions(memoryLimit: 'not-a-size');
    }

    #[Test]
    public function rejectsNegativeMessageLimit(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new WorkerLimits(messageLimit: -1);
    }

    #[Test]
    public function rejectsNegativeTimeLimit(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new WorkerLimits(timeLimitSeconds: -1);
    }

    #[Test]
    public function rejectsNegativeMemoryLimit(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new WorkerLimits(memoryLimitBytes: -1);
    }
}
