<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Logging\Contract;

/**
 * Resolves the current actor identifier for log lines.
 *
 * Adapters implement this to read the host's user context (e.g. Moodle `$USER`,
 * WordPress `wp_get_current_user`, CLI sentinel). When no actor can be determined,
 * implementations should return `'system'`.
 *
 * @api
 */
interface ActorResolverInterface
{
    /**
     * @return non-empty-string identifier such as `user:42`, `cli`, or `system`
     */
    public function resolve(): string;
}
