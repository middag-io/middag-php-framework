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

use Middag\Framework\Bus\Retry\AttemptStoreInterface;
use Middag\Framework\Tests\Bus\Retry\Fixture\FakeAttemptable;
use Middag\Framework\Tests\Bus\Retry\Fixture\InMemoryAttemptStore;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Shape check for {@see AttemptStoreInterface}
 * (core#164 F1), exercised through an in-memory stub — the real
 * implementation is a host detail that lives outside this OSS package.
 *
 * @internal
 */
#[CoversNothing]
final class AttemptStoreContractTest extends TestCase
{
    #[Test]
    public function claimReturnsTheItemOnce(): void
    {
        $store = new InMemoryAttemptStore();
        $item = new FakeAttemptable();
        $store->seed(1, $item);

        self::assertSame($item, $store->claim(1), 'first claim wins');
        self::assertNull($store->claim(1), 'a second claim finds the row already taken');
    }

    #[Test]
    public function claimReturnsNullForAnUnknownId(): void
    {
        self::assertNull((new InMemoryAttemptStore())->claim(404));
    }

    #[Test]
    public function recordSuccessAndMarkDeadAreTracked(): void
    {
        $store = new InMemoryAttemptStore();

        $store->recordSuccess(1);
        $store->markDead(2, new RuntimeException('exhausted'));

        self::assertSame([1], $store->succeeded);
        self::assertSame([2], $store->dead);
    }

    #[Test]
    public function recordFailureKeepsTheExceptionAndAvailableAt(): void
    {
        $store = new InMemoryAttemptStore();
        $exception = new RuntimeException('transient');

        $store->recordFailure(3, $exception, 1_700_000_060);

        self::assertSame($exception, $store->failures[3]['exception']);
        self::assertSame(1_700_000_060, $store->failures[3]['availableAt']);
    }
}
