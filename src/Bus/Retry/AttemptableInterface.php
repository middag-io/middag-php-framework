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

/**
 * A unit of work that carries its own retry bookkeeping.
 *
 * Deliberate departure from `symfony/messenger`'s `RetryStrategyInterface`,
 * which took `maxRetries` in the strategy's constructor (one ceiling for every
 * message the strategy handles). Here `getMaxAttempts()` lives on the item
 * instead, because in practice the item is a row in a queue table — the
 * ceiling is a column, and different rows are allowed different ceilings. The
 * policy stays a stateless, swappable collaborator; the item is the only
 * thing that knows its own history and its own limit.
 *
 * No host dependency: implementations live wherever the queue table does
 * (this package never implements the interface itself).
 *
 * @api
 */
interface AttemptableInterface
{
    /**
     * How many attempts have already been made, 0 before the first one.
     */
    public function getAttempts(): int;

    /**
     * The ceiling for this item. Once `getAttempts() >= getMaxAttempts()` the
     * item is exhausted and must not be retried again.
     */
    public function getMaxAttempts(): int;

    /**
     * Epoch timestamp (seconds) at which this item becomes eligible for the
     * next attempt, or null when it is eligible right now.
     */
    public function getAvailableAt(): ?int;
}
