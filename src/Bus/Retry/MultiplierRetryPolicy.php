<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Bus\Retry;

use InvalidArgumentException;
use Throwable;

/**
 * Default OSS retry policy: exponential backoff by a fixed multiplier, capped
 * at a maximum delay, with optional jitter.
 *
 * An item is retryable while `getAttempts() < getMaxAttempts()`. The
 * uncapped delay is `delayMilliseconds * multiplier^(attempts-1)`; `attempts`
 * is 0 right after the very first failure (see {@see AttemptableInterface}),
 * and a negative exponent there would shrink the delay below the base
 * instead of starting the backoff at it, so an attempts count below 1 is
 * treated as 1 for this computation.
 *
 * @api
 */
final readonly class MultiplierRetryPolicy implements RetryPolicyInterface
{
    public function __construct(
        private int $delayMilliseconds,
        private float $multiplier,
        private int $maxDelayMilliseconds,
        private bool $jitter = true,
    ) {
        if ($delayMilliseconds < 0) {
            throw new InvalidArgumentException('delayMilliseconds must not be negative.');
        }
        if ($multiplier <= 0.0) {
            throw new InvalidArgumentException('multiplier must be greater than zero.');
        }
        if ($maxDelayMilliseconds < 0) {
            throw new InvalidArgumentException('maxDelayMilliseconds must not be negative.');
        }
    }

    public function isRetryable(AttemptableInterface $item, ?Throwable $e = null): bool
    {
        return $item->getAttempts() < $item->getMaxAttempts();
    }

    public function getWaitingTime(AttemptableInterface $item, ?Throwable $e = null): int
    {
        $capped = $this->cappedDelayMs($item->getAttempts());

        if (!$this->jitter) {
            return $capped;
        }

        // "Full jitter" (AWS Architecture Blog, "Exponential Backoff And
        // Jitter"): sample uniformly from [0, capped] instead of adding a
        // smaller jitter on top of the full backoff ("equal jitter"). Full
        // jitter spreads retries the most across the window, which is what
        // actually breaks up a thundering herd of workers that all failed at
        // the same instant — the property we want for a shared retry policy.
        return random_int(0, $capped);
    }

    private function cappedDelayMs(int $attempts): int
    {
        $exponent = max(1, $attempts) - 1;
        $delay = $this->delayMilliseconds * ($this->multiplier ** $exponent);

        return (int) min($delay, (float) $this->maxDelayMilliseconds);
    }
}
