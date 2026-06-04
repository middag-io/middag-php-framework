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

use Middag\Framework\Bus\Attribute\Schedule;
use Middag\Framework\Bus\Contract\ScheduleReaderInterface;
use ReflectionClass;

/**
 * Default {@see ScheduleReaderInterface}: reflects `#[Schedule]` off each command
 * class, dropping unannotated and disabled commands.
 *
 * @api
 */
final readonly class ScheduleReader implements ScheduleReaderInterface
{
    public function read(iterable $commandClasses): array
    {
        $scheduled = [];

        foreach ($commandClasses as $class) {
            $attributes = (new ReflectionClass($class))->getAttributes(Schedule::class);

            if ($attributes === []) {
                continue;
            }

            $schedule = $attributes[0]->newInstance();

            if ($schedule->disabled) {
                continue;
            }

            $scheduled[$class] = $schedule;
        }

        return $scheduled;
    }
}
