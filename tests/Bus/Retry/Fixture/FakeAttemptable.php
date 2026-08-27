<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Bus\Retry\Fixture;

use Middag\Framework\Bus\Retry\AttemptableInterface;

/**
 * Minimal immutable {@see AttemptableInterface} fixture for retry tests.
 *
 * @internal
 */
final readonly class FakeAttemptable implements AttemptableInterface
{
    public function __construct(
        private int $attempts = 0,
        private int $maxAttempts = 3,
        private ?int $availableAt = null,
    ) {}

    public function getAttempts(): int
    {
        return $this->attempts;
    }

    public function getMaxAttempts(): int
    {
        return $this->maxAttempts;
    }

    public function getAvailableAt(): ?int
    {
        return $this->availableAt;
    }
}
