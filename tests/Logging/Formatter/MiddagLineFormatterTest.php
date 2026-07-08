<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Logging\Formatter;

use DateTimeImmutable;
use Middag\Framework\Logging\Formatter\MiddagLineFormatter;
use Monolog\Formatter\LineFormatter;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The stable MIDDAG line format: datetime/origin/actor/level layout consumed by
 * external log parsers.
 *
 * @internal
 */
#[CoversClass(MiddagLineFormatter::class)]
final class MiddagLineFormatterTest extends TestCase
{
    #[Test]
    public function isALineFormatterWithTheDocumentedFormat(): void
    {
        self::assertInstanceOf(LineFormatter::class, new MiddagLineFormatter());
        self::assertSame(
            "[%datetime%] [%extra.origin%] [%extra.actor%] %level_name%: %message%%context%\n",
            MiddagLineFormatter::MIDDAG_FORMAT,
        );
    }

    #[Test]
    public function formatsARecordWithOriginActorLevelAndMessage(): void
    {
        $formatter = new MiddagLineFormatter();

        $record = new LogRecord(
            datetime: new DateTimeImmutable('2026-07-08 09:30:00'),
            channel: 'core/system',
            level: Level::Info,
            message: 'user logged in',
            context: [],
            extra: ['origin' => 'cli', 'actor' => 'system'],
        );

        self::assertSame(
            "[2026-07-08 09:30:00] [cli] [system] INFO: user logged in\n",
            $formatter->format($record),
        );
    }

    #[Test]
    public function omitsEmptyContextFromTheLine(): void
    {
        $formatter = new MiddagLineFormatter();

        $record = new LogRecord(
            datetime: new DateTimeImmutable('2026-07-08 09:30:00'),
            channel: 'core/system',
            level: Level::Warning,
            message: 'heads up',
            context: [],
            extra: ['origin' => 'ip:203.0.113.7', 'actor' => 'user:42'],
        );

        $line = $formatter->format($record);

        self::assertStringContainsString('[ip:203.0.113.7] [user:42] WARNING: heads up', $line);
        self::assertStringEndsWith("\n", $line);
    }
}
