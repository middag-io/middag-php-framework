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

use Middag\Framework\Bus\Command\CommandSerializer;
use Middag\Framework\Bus\Contract\TransportInterface;
use Symfony\Component\Messenger\Envelope;
use Traversable;

/**
 * Transport shaped like the persistent DB lane: it stores rows as the raw
 * `['body' => ..., 'headers' => ...]` arrays {@see CommandSerializer} writes and
 * decodes them inside `get()`, one row at a time — exactly where a poison row
 * surfaces in production (framework#108).
 *
 * @internal
 */
final class EncodedRowTransport implements TransportInterface
{
    /** @var list<Envelope> */
    public array $acked = [];

    /** @var list<Envelope> */
    public array $rejected = [];

    private readonly CommandSerializer $serializer;

    /**
     * @param list<array<string, mixed>> $rows raw encoded envelopes, in claim order
     */
    public function __construct(private array $rows)
    {
        $this->serializer = new CommandSerializer();
    }

    public function send(Envelope $envelope): Envelope
    {
        $this->rows[] = $this->serializer->encode($envelope);

        return $envelope;
    }

    public function get(): iterable
    {
        return $this->claim();
    }

    public function ack(Envelope $envelope): void
    {
        $this->acked[] = $envelope;
    }

    public function reject(Envelope $envelope): void
    {
        $this->rejected[] = $envelope;
    }

    private function claim(): Traversable
    {
        while ($this->rows !== []) {
            yield $this->serializer->decode(array_shift($this->rows));
        }
    }
}
