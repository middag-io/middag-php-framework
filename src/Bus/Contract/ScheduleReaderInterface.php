<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Bus\Contract;

use Middag\Framework\Bus\Attribute\Schedule;

/**
 * Reads the `#[Schedule]` attribute off a set of command classes.
 *
 * The OSS default reflects each class and returns the enabled schedules so the
 * standalone runner can fire them without an adapter. Adapters that own their
 * native scheduler (Moodle `db/tasks.php`, WordPress cron) consume `#[Schedule]`
 * their own way and need not use this seam.
 *
 * @api
 */
interface ScheduleReaderInterface
{
    /**
     * @param iterable<class-string> $commandClasses
     *
     * @return array<class-string, Schedule> enabled schedules, keyed by command
     *                                       class; unannotated and disabled
     *                                       commands are omitted
     */
    public function read(iterable $commandClasses): array;
}
