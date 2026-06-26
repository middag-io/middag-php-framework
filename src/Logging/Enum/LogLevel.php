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
    case EMERGENCY = 'emergency';

    case ALERT = 'alert';

    case CRITICAL = 'critical';

    case ERROR = 'error';

    case WARNING = 'warning';

    case NOTICE = 'notice';

    case INFO = 'info';

    case DEBUG = 'debug';

    /**
     * Numeric severity for filtering (Lower number = Higher severity).
     */
    public function severity(): int
    {
        return match ($this) {
            self::EMERGENCY => 0,
            self::ALERT => 1,
            self::CRITICAL => 2,
            self::ERROR => 3,
            self::WARNING => 4,
            self::NOTICE => 5,
            self::INFO => 6,
            self::DEBUG => 7,
        };
    }
}
