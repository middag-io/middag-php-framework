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

use Middag\Framework\Bus\Attribute\Schedule;
use Middag\Framework\Bus\Contract\CommandInterface;

/**
 * Scheduled cleanup of framework-owned log storage (PSR-3 sink rotation, file logs).
 *
 * Handler resolves the storage adapter from container; this command carries no payload.
 *
 * @api
 */
#[Schedule(minute: '0', hour: '4', day: '*', month: '*', dayOfWeek: '*')]
final readonly class CleanLogsCommand implements CommandInterface
{
    /**
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return [];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromPayload(array $payload): static
    {
        return new self();
    }
}
