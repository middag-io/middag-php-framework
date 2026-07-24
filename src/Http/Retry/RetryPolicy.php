<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Http\Retry;

use DateTimeImmutable;
use InvalidArgumentException;
use Middag\Framework\Http\Contract\RetryPolicyInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Clock\NativeClock;

/**
 * Default OSS retry policy: exponential backoff with a `Retry-After` override.
 *
 * Classifies failures — 429 and 5xx (plus transport errors with no status) are
 * retryable, every other status is terminal — and, for a retryable response,
 * returns the delay a non-blocking scheduler should wait. A server-supplied
 * `Retry-After` wins over the computed backoff (the server knows its own rate
 * limit); otherwise the delay is `base * multiplier^(attempt-1)`, capped at
 * `maxDelayMs`.
 *
 * The clock (PSR-20) is only consulted to resolve an HTTP-date `Retry-After`;
 * injecting it keeps that path deterministic under test.
 *
 * @api
 */
final readonly class RetryPolicy implements RetryPolicyInterface
{
    private ClockInterface $clock;

    /**
     * @param list<int> $retryableStatuses HTTP status codes that warrant a retry
     */
    public function __construct(
        private int $maxAttempts = 3,
        private int $baseDelayMs = 1000,
        private float $multiplier = 2.0,
        private int $maxDelayMs = 30_000,
        private array $retryableStatuses = [429, 500, 502, 503, 504],
        ?ClockInterface $clock = null,
    ) {
        if ($maxAttempts < 1) {
            throw new InvalidArgumentException('maxAttempts must be at least 1.');
        }
        if ($baseDelayMs < 0 || $maxDelayMs < 0) {
            throw new InvalidArgumentException('Delays must not be negative.');
        }
        $this->clock = $clock ?? new NativeClock();
    }

    public function shouldRetry(int $attempt, ?int $statusCode): bool
    {
        if ($attempt >= $this->maxAttempts) {
            return false;
        }

        // No status → transport-level failure (timeout, reset): retryable.
        return $statusCode === null || $this->isRetryableStatus($statusCode);
    }

    public function nextDelayMs(int $attempt, ?int $statusCode = null, ?string $retryAfter = null): ?int
    {
        if (!$this->shouldRetry($attempt, $statusCode)) {
            return null;
        }

        if ($retryAfter !== null && $retryAfter !== '') {
            $fromHeader = $this->parseRetryAfter($retryAfter);
            if ($fromHeader !== null) {
                return $fromHeader;
            }
        }

        return $this->backoffMs($attempt);
    }

    private function isRetryableStatus(int $statusCode): bool
    {
        return in_array($statusCode, $this->retryableStatuses, true);
    }

    /**
     * Exponential backoff for a 1-based attempt number, capped at maxDelayMs.
     */
    private function backoffMs(int $attempt): int
    {
        $exponent = max(0, $attempt - 1);
        $delay = $this->baseDelayMs * ($this->multiplier ** $exponent);

        return (int) min($delay, (float) $this->maxDelayMs);
    }

    /**
     * Parse a `Retry-After` value into milliseconds. Accepts a non-negative
     * integer number of seconds or an RFC 7231 HTTP-date; returns null when the
     * value is neither. A past date clamps to 0.
     */
    private function parseRetryAfter(string $retryAfter): ?int
    {
        $trimmed = trim($retryAfter);

        if ($trimmed !== '' && ctype_digit($trimmed)) {
            return (int) $trimmed * 1000;
        }

        $date = DateTimeImmutable::createFromFormat(DateTimeImmutable::RFC7231, $trimmed);
        if ($date === false) {
            return null;
        }

        $deltaSeconds = $date->getTimestamp() - $this->clock->now()->getTimestamp();

        return max(0, $deltaSeconds) * 1000;
    }
}
