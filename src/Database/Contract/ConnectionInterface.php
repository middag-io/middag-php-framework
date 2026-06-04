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

/**
 * Minimal platform-agnostic database connection contract.
 *
 * Standalone apps and adapters implement this; platform-specific
 * connections (e.g. WordPress $wpdb, Moodle $DB) wrap their own APIs.
 *
 * @api
 */
interface ConnectionInterface
{
    /**
     * Execute a write statement (INSERT, UPDATE, DELETE, DDL).
     *
     * @param array<int|string, mixed> $params
     */
    public function execute(string $sql, array $params = []): int;

    /**
     * Fetch a single row, or null when no rows match.
     *
     * @param array<int|string, mixed> $params
     *
     * @return null|array<string, mixed>
     */
    public function fetch(string $sql, array $params = []): ?array;

    /**
     * Fetch all matching rows.
     *
     * @param array<int|string, mixed> $params
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchAll(string $sql, array $params = []): array;

    /**
     * Execute a callable inside a transaction.
     *
     * If the callable throws, the transaction is rolled back.
     */
    public function transaction(callable $work): mixed;
}
