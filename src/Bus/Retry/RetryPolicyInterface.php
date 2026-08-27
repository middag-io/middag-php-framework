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

use Throwable;

/**
 * Decides whether a queued item should be retried and, if so, how long to
 * wait — pure arithmetic over an {@see AttemptableInterface}, with no
 * `sleep()` and no host dependency.
 *
 * Unlike {@see \Middag\Framework\Http\Contract\RetryPolicyInterface} (which
 * classifies an HTTP response), this policy is backend-agnostic: it is meant
 * to be shared across unrelated queue implementations (e.g. a Moodle-backed
 * table and a plain SQL job table) that only agree on the
 * {@see AttemptableInterface} shape.
 *
 * @api
 */
interface RetryPolicyInterface
{
    /**
     * Should $item be attempted again?
     *
     * $e, the exception from the failed attempt when one is available, is
     * accepted so implementations may classify by failure type (e.g. a
     * non-retryable domain exception) in addition to the attempt count; the
     * OSS {@see MultiplierRetryPolicy} in this package ignores it and looks
     * only at the attempt count.
     */
    public function isRetryable(AttemptableInterface $item, ?Throwable $e = null): bool;

    /**
     * Delay, in milliseconds, before $item's next attempt.
     *
     * Callers should only call this when {@see isRetryable()} is true; a
     * policy is not required to return a meaningful value otherwise.
     */
    public function getWaitingTime(AttemptableInterface $item, ?Throwable $e = null): int;
}
