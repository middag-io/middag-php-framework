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
use Middag\Framework\Tests\Bus\Fixture\RecordCommand;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Traversable;

/**
 * Transport whose get() yields one good Envelope, then throws
 * {@see MessageDecodingFailedException} *while advancing the generator to the
 * next item* — reproducing the sharp edge core#164 F4 calls out: a `foreach`
 * gives {@see CommandWorker} no chance to catch
 * that between loop bodies, only manual iterator stepping does.
 *
 * @internal
 */
final class ThrowingIterationTransport implements TransportInterface
{
    /** @var list<Envelope> */
    public array $acked = [];

    /** @var list<Envelope> */
    public array $rejected = [];

    public function send(Envelope $envelope): Envelope
    {
        return $envelope;
    }

    public function get(): iterable
    {
        return $this->messages();
    }

    public function ack(Envelope $envelope): void
    {
        $this->acked[] = $envelope;
    }

    public function reject(Envelope $envelope): void
    {
        $this->rejected[] = $envelope;
    }

    private function messages(): Traversable
    {
        yield new Envelope(new RecordCommand('first'));

        throw new MessageDecodingFailedException('poison payload');
    }
}
