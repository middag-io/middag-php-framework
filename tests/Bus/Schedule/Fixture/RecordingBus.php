<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Bus\Schedule\Fixture;

use Middag\Framework\Bus\Contract\MessageBusInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * Test double that records dispatched messages instead of handling them.
 *
 * @internal
 */
final class RecordingBus implements MessageBusInterface
{
    /** @var list<object> */
    public array $dispatched = [];

    /**
     * @param array<StampInterface> $stamps
     */
    public function dispatch(object $message, array $stamps = []): Envelope
    {
        $this->dispatched[] = $message;

        return new Envelope($message, $stamps);
    }
}
