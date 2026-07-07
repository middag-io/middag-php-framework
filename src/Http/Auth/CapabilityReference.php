<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Http\Auth;

/**
 * Host-neutral reference to a capability or permission key.
 *
 * @api
 */
final readonly class CapabilityReference
{
    public function __construct(
        public string $key,
        public ?string $host = null,
    ) {}
}
