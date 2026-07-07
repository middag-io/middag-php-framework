<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Http\Contract;

use Middag\Framework\Http\Auth\CapabilityReference;

/**
 * A host or domain object that describes a capability beyond a bare string key.
 *
 * @api
 */
interface CapabilityDefinitionInterface
{
    public function capabilityReference(): CapabilityReference;

    /**
     * Host-specific metadata consumed by adapters or build tools.
     *
     * @return array<string, mixed>
     */
    public function capabilityOptions(): array;
}
