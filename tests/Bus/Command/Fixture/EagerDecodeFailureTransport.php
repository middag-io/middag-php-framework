<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Bus\Command\Fixture;

use Middag\Framework\Bus\Command\CommandWorker;
use Middag\Framework\Bus\Contract\TransportInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;

/**
 * Transport whose get() call itself throws {@see MessageDecodingFailedException},
 * before any iterator exists — the shape of the symfony/messenger 7.4 Doctrine
 * and Redis receivers, which decode every fetched row eagerly inside get(),
 * reject the unreadable one and rethrow. {@see CommandWorker} must survive it
 * the same way it survives a failure raised while iterating.
 *
 * @internal
 */
final class EagerDecodeFailureTransport implements TransportInterface
{
    public int $getCalls = 0;

    public function send(Envelope $envelope): Envelope
    {
        return $envelope;
    }

    public function get(): iterable
    {
        ++$this->getCalls;

        throw new MessageDecodingFailedException('poison row rejected by the receiver');
    }

    public function ack(Envelope $envelope): void {}

    public function reject(Envelope $envelope): void {}
}
