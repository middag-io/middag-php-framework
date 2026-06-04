<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Shared\Contract;

use JsonSerializable;

/**
 * Marker contract for all DTOs exchanged between layers.
 *
 * DTOs must be serializable to array/JSON for transport without exposing
 * internal entities directly.
 *
 * @api
 */
interface DtoInterface extends JsonSerializable
{
    /**
     * Convert object to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
