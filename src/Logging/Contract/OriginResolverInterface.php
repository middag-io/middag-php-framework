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
 * Resolves the request origin for log lines.
 *
 * Adapters read host signals (CLI flags, remote address, queue runner context)
 * and return a short tag. When no origin can be determined, implementations
 * should return `'system'`.
 *
 * @api
 */
interface OriginResolverInterface
{
    /**
     * @return non-empty-string Identifier such as `cli`, `ip:1.2.3.4`, or `system`.
     */
    public function resolve(): string;
}
