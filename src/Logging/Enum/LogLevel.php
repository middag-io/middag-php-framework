<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Logging\Enum;

/**
 * Log severity levels (PSR-3 / RFC 5424 aligned).
 *
 * Ordered from most severe (EMERGENCY) to least severe (DEBUG).
 *
 * @api
 */
enum LogLevel: string
{
    case Emergency = 'emergency';

    case Alert = 'alert';

    case Critical = 'critical';

    case Error = 'error';

    case Warning = 'warning';

    case Notice = 'notice';

    case Info = 'info';

    case Debug = 'debug';

    /**
     * Numeric severity for filtering (Lower number = Higher severity).
     */
    public function severity(): int
    {
        return match ($this) {
            self::Emergency => 0,
            self::Alert => 1,
            self::Critical => 2,
            self::Error => 3,
            self::Warning => 4,
            self::Notice => 5,
            self::Info => 6,
            self::Debug => 7,
        };
    }
}
