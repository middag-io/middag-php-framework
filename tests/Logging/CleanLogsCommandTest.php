<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Logging;

use Middag\Framework\Bus\Attribute\Schedule;
use Middag\Framework\Bus\Contract\CommandInterface;
use Middag\Framework\Logging\CleanLogsCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @internal
 */
#[CoversClass(CleanLogsCommand::class)]
final class CleanLogsCommandTest extends TestCase
{
    public function testImplementsCommandInterface(): void
    {
        $command = new CleanLogsCommand();

        self::assertInstanceOf(CommandInterface::class, $command);
    }

    public function testPayloadRoundTripIsEmpty(): void
    {
        $command = new CleanLogsCommand();

        self::assertSame([], $command->toPayload());
        self::assertEquals($command, CleanLogsCommand::fromPayload([]));
    }

    public function testCarriesScheduleAttribute(): void
    {
        $attributes = (new ReflectionClass(CleanLogsCommand::class))
            ->getAttributes(Schedule::class);

        self::assertCount(1, $attributes);

        $schedule = $attributes[0]->newInstance();
        self::assertSame('0', $schedule->minute);
        self::assertSame('4', $schedule->hour);
    }
}
