<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Bus;

use Middag\Framework\Bus\Contract\UserContextResolverInterface;

/**
 * Null user-context resolver for standalone apps without auth.
 *
 * Always returns null. Swap for a session/JWT/OIDC-backed impl when the
 * app introduces real authentication.
 *
 * @api
 */
final class NullUserContextResolver implements UserContextResolverInterface
{
    public function getCurrentUserId(): ?int
    {
        return null;
    }
}
