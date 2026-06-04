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
 * Contract for a serializable command — the unit of executable work.
 *
 * Commands carry the payload needed by a handler to perform a discrete action.
 * They must support round-trip serialization for persistence in jobs and adhoc tasks.
 *
 * @api
 */
interface CommandInterface
{
    /**
     * Serialize the command to a primitives-only payload for persistence.
     *
     * @return array<string, mixed>
     */
    public function toPayload(): array;

    /**
     * Reconstruct the command from a persisted payload.
     *
     * @param array<string, mixed> $payload
     */
    public static function fromPayload(array $payload): static;
}
