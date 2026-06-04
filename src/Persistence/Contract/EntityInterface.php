<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Persistence\Contract;

use JsonSerializable;

/**
 * The base contract for all domain entities.
 * Ensures basic identification and serialization capabilities.
 *
 * @api
 */
interface EntityInterface extends JsonSerializable
{
    /**
     * Get the entity unique identifier.
     * Returns null if the entity has not been persisted yet.
     *
     * @return null|int
     */
    public function getId(): ?int;

    /**
     * Convert the entity state to a plain associative array.
     * Useful for DTO conversion, logging, or debugging.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
