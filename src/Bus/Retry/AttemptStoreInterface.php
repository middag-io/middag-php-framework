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

use Throwable;

/**
 * Persistence boundary for a queue of {@see AttemptableInterface} rows.
 *
 * This package ships no implementation: the concrete store is a host detail
 * (a Moodle table, a plain SQL job table, …) that belongs in the consuming
 * application, not in this OSS package. This interface only fixes the shape
 * every backend must expose so a {@see RetryPolicyInterface} can be applied
 * uniformly regardless of where the row actually lives.
 *
 * @api
 */
interface AttemptStoreInterface
{
    /**
     * Atomically reserve item $id for processing by the calling worker.
     *
     * Must be safe under concurrent workers: exactly one caller may receive
     * the claimed item for a given un-claimed row. Returns null when the item
     * does not exist, is not currently eligible (e.g. `getAvailableAt()` is
     * still in the future), or another worker claimed it first.
     */
    public function claim(int $id): ?AttemptableInterface;

    /**
     * Record that item $id completed successfully.
     */
    public function recordSuccess(int $id): void;

    /**
     * Record that item $id's attempt failed with $e, and that it should next
     * become eligible at $availableAt (epoch seconds).
     */
    public function recordFailure(int $id, Throwable $e, int $availableAt): void;

    /**
     * Record that item $id has exhausted its retries and will not be
     * attempted again, keeping $e for diagnostics.
     */
    public function markDead(int $id, Throwable $e): void;
}
