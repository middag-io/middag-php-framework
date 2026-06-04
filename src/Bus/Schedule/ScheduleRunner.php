<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Bus\Schedule;

use Middag\Framework\Bus\Contract\CommandInterface;
use Middag\Framework\Bus\Contract\MessageBusInterface;
use Middag\Framework\Bus\Contract\ScheduleReaderInterface;
use Psr\Clock\ClockInterface;

/**
 * Fires due scheduled commands through the {@see MessageBusInterface}.
 *
 * Call {@see self::run()} on each minute tick (an OS/host cron line, or a
 * symfony/console worker): it reads the enabled `#[Schedule]`s off the given
 * command classes and dispatches every one whose cron fields match the current
 * minute. Dispatch goes through the same bus as everything else, so a scheduled
 * command also marked `#[AsMessage]` is queued, not run inline.
 *
 * Scheduled commands MUST be constructible with no arguments (a cron trigger
 * carries no per-invocation payload). The runner ignores `Schedule::$exclusive`
 * (concurrency control is a host/locking concern, not the OSS pipe).
 *
 * @api
 */
final readonly class ScheduleRunner
{
    public function __construct(
        private MessageBusInterface $bus,
        private ScheduleReaderInterface $reader,
        private CronFieldMatcher $matcher,
        private ClockInterface $clock,
    ) {}

    /**
     * Dispatch every scheduled command due in the current minute.
     *
     * @param iterable<class-string<CommandInterface>> $commandClasses candidate commands (e.g. discovered)
     *
     * @return list<class-string> the command classes dispatched this tick
     */
    public function run(iterable $commandClasses): array
    {
        $now = $this->clock->now();
        $dispatched = [];

        foreach ($this->reader->read($commandClasses) as $class => $schedule) {
            if (!$this->matcher->matches($schedule, $now)) {
                continue;
            }

            $this->bus->dispatch(new $class());
            $dispatched[] = $class;
        }

        return $dispatched;
    }
}
