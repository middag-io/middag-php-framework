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

use DateTimeInterface;
use Middag\Framework\Bus\Attribute\Schedule;

/**
 * Evaluates a {@see Schedule}'s cron fields against a point in time.
 *
 * Supports the common cron field grammar per field: `*`, a single value `N`,
 * a list `a,b,c`, a range `a-b`, and steps `* /k` / `a-b/k` / `N/k`. The value
 * `R` (adapter random) is treated as `*`. All five fields are ANDed (matching
 * the Moodle task model), so there is NO standard-cron day-of-month/day-of-week
 * OR rule. Day-of-week accepts `7` as Sunday (== `0`).
 *
 * Intended for minute-granularity ticks: a true result means "this schedule is
 * due in the current minute".
 *
 * @api
 */
final readonly class CronFieldMatcher
{
    public function matches(Schedule $schedule, DateTimeInterface $now): bool
    {
        return $this->field($schedule->minute, (int) $now->format('i'))
            && $this->field($schedule->hour, (int) $now->format('G'))
            && $this->field($schedule->day, (int) $now->format('j'))
            && $this->field($schedule->month, (int) $now->format('n'))
            && $this->dayOfWeekField($schedule->dayOfWeek, (int) $now->format('w'));
    }

    /**
     * Day-of-week accepts cron's `7` for Sunday; PHP's `w` reports Sunday as `0`.
     */
    private function dayOfWeekField(string $expr, int $weekday): bool
    {
        if ($this->field($expr, $weekday)) {
            return true;
        }

        return $weekday === 0 && $this->field($expr, 7);
    }

    private function field(string $expr, int $value): bool
    {
        if ($expr === '*' || $expr === 'R') {
            return true;
        }

        foreach (explode(',', $expr) as $part) {
            if ($this->partMatches(trim($part), $value)) {
                return true;
            }
        }

        return false;
    }

    private function partMatches(string $part, int $value): bool
    {
        $step = 1;

        if (str_contains($part, '/')) {
            $segments = explode('/', $part, 2);
            if (!isset($segments[1]) || !ctype_digit($segments[1])) {
                return false;
            }
            $part = $segments[0];
            $step = (int) $segments[1];
            if ($step <= 0) {
                return false;
            }
        }

        if ($part === '*') {
            return $value % $step === 0;
        }

        if (str_contains($part, '-')) {
            $bounds = explode('-', $part, 2);
            if (!isset($bounds[1]) || !ctype_digit($bounds[0]) || !ctype_digit($bounds[1])) {
                return false;
            }
            $low = (int) $bounds[0];
            $high = (int) $bounds[1];

            return $value >= $low && $value <= $high && ($value - $low) % $step === 0;
        }

        if (!ctype_digit($part)) {
            return false;
        }
        $single = (int) $part;

        return $step === 1
            ? $value === $single
            : $value >= $single && ($value - $single) % $step === 0;
    }
}
