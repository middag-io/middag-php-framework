<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Database\Contract;

use Throwable;

/**
 * Contract for a transaction manager offering atomic and graceful boundaries.
 *
 * The atomic boundary is the classic "run inside a transaction; roll back and
 * re-throw on failure". The graceful boundary instead returns the caught
 * Throwable when the platform's outermost transaction can be rolled back
 * cleanly, and re-throws for nested/doomed transactions the platform cannot
 * neutralize. This lets domain code run inside a transaction without every
 * failure tearing down the host request cycle.
 *
 * Host adapters (e.g. Moodle's delegated transactions, WordPress) implement
 * this against their own transaction API.
 *
 * @api
 */
interface TransactionManagerInterface
{
    /**
     * Execute a callback inside an atomic transaction boundary.
     *
     * The transaction rolls back and the exception propagates on any failure.
     *
     * @template T
     *
     * @param callable(): T $operation Business logic to run
     *
     * @return T
     *
     * @throws Throwable Any exception thrown by the operation
     */
    public function executeAtomic(callable $operation): mixed;

    /**
     * Execute a callback inside a transaction boundary, catching failures.
     *
     * When the transaction is the outermost one, a failure is rolled back
     * cleanly and the Throwable is returned instead of propagating. When the
     * transaction is nested (an inner boundary the platform dooms), the
     * Throwable is re-thrown to honor the platform's cascading-rollback
     * constraint.
     *
     * @template T
     *
     * @param callable(): T $operation Business logic to run
     *
     * @return T|Throwable the Throwable on a graceful rollback, otherwise the result
     */
    public function executeGraceful(callable $operation): mixed;
}
