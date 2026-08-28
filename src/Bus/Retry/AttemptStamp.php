<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Bus\Retry;

use Middag\Framework\Bus\Command\CommandWorker;
use Symfony\Component\Messenger\Stamp\NonSendableStampInterface;

/**
 * Binds a received {@see Envelope} to the queue row that carries its retry
 * bookkeeping.
 *
 * `TransportInterface::get()` yields Envelopes; `AttemptableInterface`
 * (attempts/maxAttempts/availableAt) and {@see AttemptStoreInterface}
 * (claim/recordSuccess/recordFailure/markDead by row id) live one level
 * below, on the queue row itself. This stamp is the seam that lets
 * {@see CommandWorker} reach the row from the
 * envelope it just received, without `AttemptStoreInterface` or
 * `AttemptableInterface` growing a method — both are `@api` and every
 * implementor would break.
 *
 * Transport contract (binding on any drainable transport that wants retry
 * bookkeeping): `get()` MUST attach `new AttemptStamp($id, $item)` to every
 * Envelope it yields, where `$id` is the row id `AttemptStoreInterface`
 * expects back (recordSuccess/recordFailure/markDead) and `$item` is the
 * `AttemptableInterface` returned by the claim that produced this envelope.
 * When a transport has no retry bookkeeping (e.g. {@see InMemoryTransport}),
 * it simply never attaches this stamp — the worker treats its absence as
 * "nothing to record" and falls back to a plain ack/reject drain.
 *
 * In-process only, exactly like Symfony's own {@see ReceivedStamp}: it
 * carries a live object graph (the row), not wire data, so it implements
 * {@see NonSendableStampInterface} and is stripped before a message is ever
 * sent/serialized to a transport.
 *
 * @api
 */
final readonly class AttemptStamp implements NonSendableStampInterface
{
    public function __construct(
        private int $id,
        private AttemptableInterface $item,
    ) {}

    /**
     * The row id to pass back to {@see AttemptStoreInterface}.
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * The claimed row, for {@see RetryPolicyInterface} to classify.
     */
    public function getItem(): AttemptableInterface
    {
        return $this->item;
    }
}
