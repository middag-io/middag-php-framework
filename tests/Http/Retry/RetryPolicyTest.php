<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Http\Retry;

use InvalidArgumentException;
use Middag\Framework\Http\Retry\RetryPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * Deterministic failure classification, exponential backoff, and Retry-After
 * handling (issue #56). No host dependency; the HTTP-date clock is pinned.
 *
 * @internal
 */
#[CoversClass(RetryPolicy::class)]
final class RetryPolicyTest extends TestCase
{
    #[Test]
    #[DataProvider('statusClassification')]
    public function classifiesRetryableVersusTerminalStatuses(?int $status, bool $expected): void
    {
        // Attempt 1 of 3 — attempts are not the limiting factor here.
        self::assertSame($expected, (new RetryPolicy())->shouldRetry(1, $status));
    }

    /**
     * @return iterable<string, array{null|int, bool}>
     */
    public static function statusClassification(): iterable
    {
        yield '429 rate limit' => [429, true];

        yield '500 server error' => [500, true];

        yield '502 bad gateway' => [502, true];

        yield '503 unavailable' => [503, true];

        yield '504 gateway timeout' => [504, true];

        yield 'transport error (no status)' => [null, true];

        yield '400 bad request' => [400, false];

        yield '401 unauthorized' => [401, false];

        yield '404 not found' => [404, false];

        yield '418 teapot' => [418, false];
    }

    #[Test]
    public function stopsRetryingOnceAttemptsAreExhausted(): void
    {
        $policy = new RetryPolicy(maxAttempts: 3);

        self::assertTrue($policy->shouldRetry(2, 503));
        self::assertFalse($policy->shouldRetry(3, 503), 'the last attempt is not retried again');
        self::assertNull($policy->nextDelayMs(3, 503), 'exhausted attempts yield no delay');
    }

    #[Test]
    public function terminalStatusYieldsNoDelay(): void
    {
        self::assertNull((new RetryPolicy())->nextDelayMs(1, 404));
    }

    #[Test]
    public function exponentialBackoffGrowsAndCaps(): void
    {
        $policy = new RetryPolicy(maxAttempts: 10, baseDelayMs: 1000, multiplier: 2.0, maxDelayMs: 5000);

        self::assertSame(1000, $policy->nextDelayMs(1, 503), 'attempt 1 = base');
        self::assertSame(2000, $policy->nextDelayMs(2, 503), 'attempt 2 = base * 2');
        self::assertSame(4000, $policy->nextDelayMs(3, 503), 'attempt 3 = base * 4');
        self::assertSame(5000, $policy->nextDelayMs(4, 503), 'attempt 4 caps at maxDelayMs');
    }

    #[Test]
    public function retryAfterInSecondsIsHonoredOverBackoff(): void
    {
        $policy = new RetryPolicy(baseDelayMs: 1000);

        self::assertSame(120_000, $policy->nextDelayMs(1, 429, '120'), 'Retry-After seconds win over backoff');
    }

    #[Test]
    public function retryAfterAsHttpDateIsResolvedAgainstTheClock(): void
    {
        $now = 1_700_000_000;
        $policy = new RetryPolicy(clock: new MockClock('@' . $now));

        // 30 seconds in the future, RFC 7231 format.
        $future = gmdate('D, d M Y H:i:s \G\M\T', $now + 30);
        self::assertSame(30_000, $policy->nextDelayMs(1, 503, $future));
    }

    #[Test]
    public function retryAfterInThePastClampsToZero(): void
    {
        $now = 1_700_000_000;
        $policy = new RetryPolicy(clock: new MockClock('@' . $now));

        $past = gmdate('D, d M Y H:i:s \G\M\T', $now - 60);
        self::assertSame(0, $policy->nextDelayMs(1, 503, $past));
    }

    #[Test]
    public function unparseableRetryAfterFallsBackToBackoff(): void
    {
        $policy = new RetryPolicy(baseDelayMs: 1000);

        self::assertSame(1000, $policy->nextDelayMs(1, 503, 'not-a-date'), 'garbage Retry-After ignored');
    }

    #[Test]
    public function rejectsInvalidConfiguration(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new RetryPolicy(maxAttempts: 0);
    }
}
