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

use Middag\Framework\Bus\Retry\AttemptStamp;
use Middag\Framework\Tests\Bus\Retry\Fixture\FakeAttemptable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\NonSendableStampInterface;

/**
 * Pins the core#164 F4 transport contract: a drainable transport binds an
 * Envelope to its retry row by attaching {@see AttemptStamp} in `get()`. The
 * stamp must never survive a send/serialize round trip — exactly like
 * Symfony's own `ReceivedStamp` — which is what
 * {@see NonSendableStampInterface} guarantees.
 *
 * @internal
 */
#[CoversClass(AttemptStamp::class)]
final class AttemptStampTest extends TestCase
{
    #[Test]
    public function exposesTheRowIdAndTheAttemptableItem(): void
    {
        $item = new FakeAttemptable(attempts: 2, maxAttempts: 5);
        $stamp = new AttemptStamp(42, $item);

        self::assertSame(42, $stamp->getId());
        self::assertSame($item, $stamp->getItem());
    }

    #[Test]
    public function isNonSendableLikeReceivedStamp(): void
    {
        self::assertInstanceOf(NonSendableStampInterface::class, new AttemptStamp(1, new FakeAttemptable()));
    }

    #[Test]
    public function roundTripsThroughAnEnvelopeAndIsRetrievableViaLast(): void
    {
        $stamp = new AttemptStamp(7, new FakeAttemptable());
        $envelope = (new Envelope(new stdClass()))->with($stamp);

        self::assertSame($stamp, $envelope->last(AttemptStamp::class));
    }
}
