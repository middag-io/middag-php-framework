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
 * Scheduled command marked disabled — the reader must skip it.
 *
 * @internal
 */
#[Schedule(disabled: true)]
final readonly class DisabledScheduledCommand implements CommandInterface
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
