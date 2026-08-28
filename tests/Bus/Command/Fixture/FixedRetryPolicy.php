<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Bus\Command\Fixture;

use Middag\Framework\Bus\Command\CommandWorker;
use Middag\Framework\Bus\Retry\AttemptableInterface;
use Middag\Framework\Bus\Retry\MultiplierRetryPolicy;
use Middag\Framework\Bus\Retry\RetryPolicyInterface;
use Throwable;

/**
 * Retry policy stub with a hard-coded verdict and waiting time — isolates
 * {@see CommandWorker} tests from
 * {@see MultiplierRetryPolicy}'s own math.
 *
 * @internal
 */
final readonly class FixedRetryPolicy implements RetryPolicyInterface
{
    public function __construct(
        private bool $retryable,
        private int $waitingTimeMilliseconds = 0,
    ) {}

    public function isRetryable(AttemptableInterface $item, ?Throwable $e = null): bool
    {
        return $this->retryable;
    }

    public function getWaitingTime(AttemptableInterface $item, ?Throwable $e = null): int
    {
        return $this->waitingTimeMilliseconds;
    }
}
