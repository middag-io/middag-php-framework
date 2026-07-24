<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Http\Contract;

/**
 * Decides whether an outbound HTTP attempt should be retried and, if so, how
 * long to wait — without ever blocking.
 *
 * The policy is pure arithmetic over the attempt number, the response status,
 * and an optional `Retry-After` header. It returns a delay in milliseconds; a
 * scheduler (or the async command bus) is responsible for the actual wait, so
 * no `sleep()` ever runs in the calling code. It has no host dependency.
 *
 * @api
 */
interface RetryPolicyInterface
{
    /**
     * Should attempt number $attempt (1-based) be retried given $statusCode?
     *
     * A null $statusCode models a transport-level failure (connection reset,
     * timeout) with no HTTP response — treated as retryable. Returns false once
     * attempts are exhausted or the status is terminal (e.g. a 4xx other than
     * 429).
     */
    public function shouldRetry(int $attempt, ?int $statusCode): bool;

    /**
     * Delay in milliseconds before the next attempt, or null when no further
     * retry should happen (terminal status or attempts exhausted).
     *
     * When $retryAfter (the raw `Retry-After` header — either a number of
     * seconds or an RFC 7231 HTTP-date) is present on a retryable response it is
     * honored; otherwise an exponential backoff is used.
     */
    public function nextDelayMs(int $attempt, ?int $statusCode = null, ?string $retryAfter = null): ?int;
}
