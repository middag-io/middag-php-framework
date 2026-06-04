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
 * Record-oriented persistence contract.
 *
 * Extends the raw SQL {@see ConnectionInterface} with table/record helpers
 * (insert, update, delete, find, findAll, cursor) so generic repositories can
 * persist domain records without writing SQL by hand. Platform adapters map
 * these to their native record APIs (the host DB layer / native query API);
 * the ANSI implementation builds parameterized SQL.
 *
 * Records and conditions are plain assoc arrays at this boundary; adapters
 * convert to/from their native row shape (e.g. host stdClass/object rows).
 *
 * @api
 */
interface RecordConnectionInterface extends ConnectionInterface
{
    /**
     * Insert a record and return the new primary key.
     *
     * @param array<string, mixed> $record Column => value pairs (no 'id')
     *
     * @return int Newly generated identifier
     */
    public function insert(string $table, array $record): int;

    /**
     * Update an existing record, keyed by its 'id' element.
     *
     * @param array<string, mixed> $record Column => value pairs including 'id'
     */
    public function update(string $table, array $record): void;

    /**
     * Delete records matching the given conditions.
     *
     * @param array<string, mixed> $conditions Column => value equality conditions
     */
    public function delete(string $table, array $conditions): void;

    /**
     * Fetch a single record matching the conditions, or null when none match.
     *
     * @param array<string, mixed> $conditions Column => value equality conditions
     *
     * @return null|array<string, mixed> Single record as an assoc array or null
     */
    public function find(string $table, array $conditions): ?array;

    /**
     * Fetch all records matching the conditions.
     *
     * @param array<string, mixed> $conditions Column => value equality conditions
     *
     * @return array<int, array<string, mixed>> List of records as assoc arrays
     */
    public function findAll(string $table, array $conditions = []): array;

    /**
     * Stream rows for a raw SQL query (recordset semantics).
     *
     * Callers iterate assoc rows lazily; adapters back this with an unbuffered
     * cursor / native recordset to keep memory bounded on large result sets.
     *
     * @param array<int|string, mixed> $params
     *
     * @return iterable<int, array<string, mixed>>
     */
    public function cursor(string $sql, array $params = []): iterable;
}
