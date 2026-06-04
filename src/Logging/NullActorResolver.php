<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Logging;

use Middag\Framework\Logging\Contract\ActorResolverInterface;

/**
 * Null actor resolver — always returns `system`.
 *
 * Used by standalone apps without user authentication, or as a fallback
 * before request-scoped user context is established.
 *
 * @api
 */
final class NullActorResolver implements ActorResolverInterface
{
    public function resolve(): string
    {
        return 'system';
    }
}
