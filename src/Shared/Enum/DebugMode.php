<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Shared\Enum;

/**
 * Debug levels definition.
 *
 * @api
 */
enum DebugMode: int
{
    case Disabled = 0;

    case Normal = 1;

    case Full = 2;

    /**
     * Check if the current configured mode satisfies the required level.
     *
     * @param int $configValue the value coming from settings (db/config)
     */
    public function isEnabledBy(int $configValue): bool
    {
        return $configValue >= $this->value;
    }
}
