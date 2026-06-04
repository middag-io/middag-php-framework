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

/**
 * Contract for resolving the current user context.
 *
 * Adapters implement using their platform's user system:
 * - Moodle: user_support::get_current_user_id()
 * - WordPress: get_current_user_id()
 *
 * @api
 */
interface UserContextResolverInterface
{
    /**
     * Get the current authenticated user's ID, or null if not authenticated.
     */
    public function getCurrentUserId(): ?int;
}
