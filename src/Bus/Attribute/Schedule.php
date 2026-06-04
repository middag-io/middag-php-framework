<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Bus\Attribute;

use Attribute;

/**
 * Declares a periodic cron trigger for a command (platform-agnostic DSL).
 *
 * Fields follow cron-style notation. The value `'R'` is also accepted in any
 * field — adapters that implement random scheduling (Moodle `db/tasks.php`)
 * pass it through verbatim; others may treat it as `'*'`.
 *
 * Adapters convert this attribute into their native scheduling primitive
 * (e.g. Moodle: the adapter's task-definition builder emits the
 * `db/tasks.php` row shape; WordPress: `wp_schedule_event` payload). The
 * `$exclusive` flag is a host-neutral "do not run concurrently" hint the
 * adapter maps to its own locking primitive.
 *
 * @api
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Schedule
{
    public function __construct(
        public string $minute = '*',
        public string $hour = '*',
        public string $day = '*',
        public string $month = '*',
        public string $dayOfWeek = '*',
        public bool $disabled = false,
        public bool $exclusive = false,
    ) {}
}
