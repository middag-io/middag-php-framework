<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Bus\Schedule;

use DateTimeImmutable;
use Middag\Framework\Bus\Attribute\Schedule;
use Middag\Framework\Bus\Schedule\CronFieldMatcher;
use Middag\Framework\Bus\Schedule\ScheduleReader;
use Middag\Framework\Bus\Schedule\ScheduleRunner;
use Middag\Framework\Kernel\ServiceProvider;
use Middag\Framework\Logging\CleanLogsCommand;
use Middag\Framework\Tests\Bus\Fixture\RecordCommand;
use Middag\Framework\Tests\Bus\Schedule\Fixture\AlwaysScheduledCommand;
use Middag\Framework\Tests\Bus\Schedule\Fixture\DisabledScheduledCommand;
use Middag\Framework\Tests\Bus\Schedule\Fixture\RecordingBus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * The OSS standalone scheduler: a cron-field matcher, a `#[Schedule]` reader,
 * and a runner that dispatches due commands through the bus.
 *
 * @internal
 */
#[CoversClass(CronFieldMatcher::class)]
#[CoversClass(ScheduleReader::class)]
#[CoversClass(ScheduleRunner::class)]
#[CoversClass(ServiceProvider::class)]
final class ScheduleSupportTest extends TestCase
{
    public function testWildcardAndRandomAlwaysMatch(): void
    {
        $matcher = new CronFieldMatcher();
        $now = new DateTimeImmutable('2026-06-03 13:37:00');

        self::assertTrue($matcher->matches(new Schedule(), $now));
        self::assertTrue($matcher->matches(new Schedule(minute: 'R', hour: 'R'), $now));
    }

    public function testExactMinuteAndHour(): void
    {
        $matcher = new CronFieldMatcher();
        $schedule = new Schedule(minute: '0', hour: '4');

        self::assertTrue($matcher->matches($schedule, new DateTimeImmutable('2026-06-03 04:00:00')));
        self::assertFalse($matcher->matches($schedule, new DateTimeImmutable('2026-06-03 04:01:00')));
        self::assertFalse($matcher->matches($schedule, new DateTimeImmutable('2026-06-03 05:00:00')));
    }

    public function testStepRangeAndList(): void
    {
        $matcher = new CronFieldMatcher();
        $at = static fn (int $minute): DateTimeImmutable => new DateTimeImmutable(sprintf('2026-06-03 10:%02d:00', $minute));

        self::assertTrue($matcher->matches(new Schedule(minute: '*/15'), $at(30)));
        self::assertFalse($matcher->matches(new Schedule(minute: '*/15'), $at(7)));
        self::assertTrue($matcher->matches(new Schedule(minute: '0,30'), $at(30)));
        self::assertFalse($matcher->matches(new Schedule(minute: '0,30'), $at(15)));
        self::assertTrue($matcher->matches(new Schedule(minute: '0-5'), $at(3)));
        self::assertFalse($matcher->matches(new Schedule(minute: '0-5'), $at(6)));
    }

    public function testDayOfWeekSevenIsSunday(): void
    {
        $matcher = new CronFieldMatcher();
        $sunday = (new DateTimeImmutable('2026-06-01 12:00:00'))->modify('next sunday');
        self::assertSame('0', $sunday->format('w'));

        self::assertTrue($matcher->matches(new Schedule(dayOfWeek: '7'), $sunday));
        self::assertTrue($matcher->matches(new Schedule(dayOfWeek: '0'), $sunday));
        self::assertFalse($matcher->matches(new Schedule(dayOfWeek: '1'), $sunday));
    }

    public function testReaderSkipsUnscheduledAndDisabled(): void
    {
        $result = (new ScheduleReader())->read([
            CleanLogsCommand::class,
            AlwaysScheduledCommand::class,
            DisabledScheduledCommand::class,
            RecordCommand::class,
        ]);

        self::assertArrayHasKey(CleanLogsCommand::class, $result);
        self::assertArrayHasKey(AlwaysScheduledCommand::class, $result);
        self::assertArrayNotHasKey(DisabledScheduledCommand::class, $result);
        self::assertArrayNotHasKey(RecordCommand::class, $result);
        self::assertCount(2, $result);
    }

    public function testRunnerDispatchesOnlyDueCommands(): void
    {
        $bus = new RecordingBus();
        $runner = new ScheduleRunner(
            $bus,
            new ScheduleReader(),
            new CronFieldMatcher(),
            new MockClock('2026-06-03 04:00:00'),
        );

        $dispatched = $runner->run([
            AlwaysScheduledCommand::class,
            DisabledScheduledCommand::class,
            RecordCommand::class,
            CleanLogsCommand::class,
        ]);

        // Always-due + CleanLogs (minute 0, hour 4 at 04:00) fire; disabled and
        // unscheduled never do.
        self::assertContains(AlwaysScheduledCommand::class, $dispatched);
        self::assertContains(CleanLogsCommand::class, $dispatched);
        self::assertCount(2, $dispatched);
        self::assertCount(2, $bus->dispatched);
    }

    public function testRunnerSkipsCommandsNotDueThisMinute(): void
    {
        $bus = new RecordingBus();
        $runner = new ScheduleRunner(
            $bus,
            new ScheduleReader(),
            new CronFieldMatcher(),
            new MockClock('2026-06-03 03:00:00'),
        );

        $dispatched = $runner->run([AlwaysScheduledCommand::class, CleanLogsCommand::class]);

        // CleanLogs is due at 04:00, not 03:00.
        self::assertSame([AlwaysScheduledCommand::class], $dispatched);
    }

    public function testWiredContainerResolvesScheduleRunner(): void
    {
        $container = new ContainerBuilder();
        ServiceProvider::register($container, __DIR__);
        $container->compile();

        self::assertInstanceOf(ScheduleRunner::class, $container->get(ScheduleRunner::class));
    }
}
