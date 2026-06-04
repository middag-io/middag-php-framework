<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Bus\Schedule\Fixture;

use Middag\Framework\Bus\Attribute\Schedule;
use Middag\Framework\Bus\Contract\CommandInterface;

/**
 * Scheduled command whose every cron field is `*` — always due.
 *
 * @internal
 */
#[Schedule]
final readonly class AlwaysScheduledCommand implements CommandInterface
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
