<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Bus\Retry;

use InvalidArgumentException;
use Middag\Framework\Bus\Retry\MultiplierRetryPolicy;
use Middag\Framework\Tests\Bus\Retry\Fixture\FakeAttemptable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Retryable-boundary classification and exponential backoff (core#164 F1).
 * Jitter is only asserted as a bounded range — the exact value is random by
 * design — while every backoff-shape assertion pins `jitter: false` so the
 * suite stays deterministic.
 *
 * @internal
 */
#[CoversClass(MultiplierRetryPolicy::class)]
final class MultiplierRetryPolicyTest extends TestCase
{
    #[Test]
    #[DataProvider('retryableBoundaries')]
    public function isRetryableAtTheAttemptsBoundary(int $attempts, int $maxAttempts, bool $expected): void
    {
        $policy = new MultiplierRetryPolicy(delayMilliseconds: 100, multiplier: 2.0, maxDelayMilliseconds: 10_000);
        $item = new FakeAttemptable(attempts: $attempts, maxAttempts: $maxAttempts);

        self::assertSame($expected, $policy->isRetryable($item));
    }

    /**
     * @return iterable<string, array{int, int, bool}>
     */
    public static function retryableBoundaries(): iterable
    {
        yield 'attempts below max' => [1, 3, true];

        yield 'attempts one below max' => [2, 3, true];

        yield 'attempts equal to max' => [3, 3, false];

        yield 'attempts past max' => [4, 3, false];

        yield 'zero attempts, positive max' => [0, 3, true];

        yield 'zero max, never retryable' => [0, 0, false];
    }

    #[Test]
    public function backoffGrowsWithTheMultiplierAndIsDeterministicWithoutJitter(): void
    {
        $policy = new MultiplierRetryPolicy(
            delayMilliseconds: 1000,
            multiplier: 2.0,
            maxDelayMilliseconds: 100_000,
            jitter: false,
        );

        self::assertSame(1000, $policy->getWaitingTime(new FakeAttemptable(attempts: 1)), 'attempt 1 = base');
        self::assertSame(2000, $policy->getWaitingTime(new FakeAttemptable(attempts: 2)), 'attempt 2 = base * multiplier');
        self::assertSame(4000, $policy->getWaitingTime(new FakeAttemptable(attempts: 3)), 'attempt 3 = base * multiplier^2');
        self::assertSame(8000, $policy->getWaitingTime(new FakeAttemptable(attempts: 4)), 'attempt 4 = base * multiplier^3');
    }

    #[Test]
    public function backoffRespectsTheMaxDelayCap(): void
    {
        $policy = new MultiplierRetryPolicy(
            delayMilliseconds: 1000,
            multiplier: 2.0,
            maxDelayMilliseconds: 5000,
            jitter: false,
        );

        self::assertSame(4000, $policy->getWaitingTime(new FakeAttemptable(attempts: 3)), 'below the cap');
        self::assertSame(5000, $policy->getWaitingTime(new FakeAttemptable(attempts: 4)), 'would be 8000, capped to 5000');
        self::assertSame(5000, $policy->getWaitingTime(new FakeAttemptable(attempts: 10)), 'stays capped for later attempts');
    }

    #[Test]
    public function zeroAttemptsIsTreatedAsTheFirstAttemptAndDoesNotBreakTheFormula(): void
    {
        $policy = new MultiplierRetryPolicy(
            delayMilliseconds: 1000,
            multiplier: 2.0,
            maxDelayMilliseconds: 100_000,
            jitter: false,
        );

        self::assertSame(
            $policy->getWaitingTime(new FakeAttemptable(attempts: 1)),
            $policy->getWaitingTime(new FakeAttemptable(attempts: 0)),
            'attempts=0 (right after the first failure) behaves like attempts=1, not a negative exponent',
        );
    }

    #[Test]
    public function fullJitterStaysWithinZeroAndTheCappedDelay(): void
    {
        $policy = new MultiplierRetryPolicy(delayMilliseconds: 1000, multiplier: 2.0, maxDelayMilliseconds: 5000);
        $item = new FakeAttemptable(attempts: 4); // uncapped would be 8000, capped to 5000

        // Sample repeatedly: a range assertion (not an exact value) keeps
        // this deterministic despite the randomness under test.
        for ($i = 0; $i < 50; ++$i) {
            $delay = $policy->getWaitingTime($item);
            self::assertGreaterThanOrEqual(0, $delay);
            self::assertLessThanOrEqual(5000, $delay);
        }
    }

    #[Test]
    public function rejectsNegativeDelayMilliseconds(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new MultiplierRetryPolicy(delayMilliseconds: -1, multiplier: 2.0, maxDelayMilliseconds: 1000);
    }

    #[Test]
    public function rejectsNonPositiveMultiplier(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new MultiplierRetryPolicy(delayMilliseconds: 100, multiplier: 0.0, maxDelayMilliseconds: 1000);
    }

    #[Test]
    public function rejectsNegativeMaxDelayMilliseconds(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new MultiplierRetryPolicy(delayMilliseconds: 100, multiplier: 2.0, maxDelayMilliseconds: -1);
    }
}
