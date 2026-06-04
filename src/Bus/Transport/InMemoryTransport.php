<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Bus\Transport;

use Middag\Framework\Bus\Command\CommandWorker;
use Middag\Framework\Bus\Contract\TransportInterface;
use Symfony\Component\Messenger\Envelope;

/**
 * In-process queue transport: enqueues messages in memory; a {@see CommandWorker}
 * drains them later, in the same process.
 *
 * Default async {@see TransportInterface} binding for deterministic tests and for
 * the "enqueue during the request, drain at the end" pattern — true async
 * semantics without a broker. The work does NOT run at dispatch time; something
 * must call {@see CommandWorker::drain()}. (For inline execution, route the
 * message to no transport at all — the bus then handles it synchronously.)
 *
 * State is per-instance and lost at process end. For durable async, bind a
 * persistent transport (Redis, Doctrine, AMQP, a host queue) on the same seam.
 *
 * @api
 */
final class InMemoryTransport implements TransportInterface
{
    /** @var list<Envelope> */
    private array $queue = [];

    public function send(Envelope $envelope): Envelope
    {
        $this->queue[] = $envelope;

        return $envelope;
    }

    /**
     * @return list<Envelope>
     */
    public function get(): iterable
    {
        return $this->queue;
    }

    public function ack(Envelope $envelope): void
    {
        $this->remove($envelope);
    }

    public function reject(Envelope $envelope): void
    {
        $this->remove($envelope);
    }

    private function remove(Envelope $target): void
    {
        $this->queue = array_values(array_filter(
            $this->queue,
            static fn (Envelope $envelope): bool => $envelope !== $target,
        ));
    }
}
