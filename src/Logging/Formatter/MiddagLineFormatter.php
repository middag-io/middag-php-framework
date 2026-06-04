<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Logging\Formatter;

use Monolog\Formatter\LineFormatter;

/**
 * Stable line format kept compatible with external log parsers
 * (datetime/origin/actor/level regex) so existing tooling keeps parsing
 * without changes.
 *
 * Shape: `[datetime] [origin] [actor] LEVEL: message {context-json}`
 *
 * @api
 */
final class MiddagLineFormatter extends LineFormatter
{
    public const MIDDAG_FORMAT = "[%datetime%] [%extra.origin%] [%extra.actor%] %level_name%: %message%%context%\n";

    public function __construct()
    {
        parent::__construct(
            format: self::MIDDAG_FORMAT,
            dateFormat: 'Y-m-d H:i:s',
            allowInlineLineBreaks: false,
            ignoreEmptyContextAndExtra: true,
        );
    }
}
