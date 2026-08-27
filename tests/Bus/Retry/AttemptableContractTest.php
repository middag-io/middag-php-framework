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

use Middag\Framework\Bus\Retry\AttemptableInterface;
use Middag\Framework\Tests\Bus\Retry\Fixture\FakeAttemptable;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Shape check for {@see AttemptableInterface}
 * (core#164 F1): a plain getter contract, exercised through a fixture since
 * the interface itself has no behavior of its own.
 *
 * @internal
 */
#[CoversNothing]
final class AttemptableContractTest extends TestCase
{
    #[Test]
    public function exposesAttemptsMaxAttemptsAndAvailableAt(): void
    {
        $item = new FakeAttemptable(attempts: 2, maxAttempts: 5, availableAt: 1_700_000_000);

        self::assertSame(2, $item->getAttempts());
        self::assertSame(5, $item->getMaxAttempts());
        self::assertSame(1_700_000_000, $item->getAvailableAt());
    }

    #[Test]
    public function defaultsToZeroAttemptsAndAvailableNow(): void
    {
        $item = new FakeAttemptable();

        self::assertSame(0, $item->getAttempts());
        self::assertNull($item->getAvailableAt(), 'null availableAt means eligible right now');
    }
}
