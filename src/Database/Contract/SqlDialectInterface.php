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
 * SQL dialect helpers for raw queries built by generic repositories.
 *
 * Abstracts the few platform-specific SQL idioms a repository needs when it
 * cannot use the record helpers on {@see RecordConnectionInterface} and must
 * emit raw SQL (joins, IN clauses, text comparison). The ANSI dialect is a
 * near-passthrough; the Moodle dialect emits `{tablename}` table bracing and
 * `sql_compare_text()` so the same repository SQL runs on either platform.
 *
 * @api
 */
interface SqlDialectInterface
{
    /**
     * Render a logical table name for use inside a raw SQL string.
     *
     * ANSI: passthrough (the physical/prefixed name is used as-is).
     * Moodle: wraps in braces, e.g. `middag_items` → `{middag_items}`.
     */
    public function table(string $logicalName): string;

    /**
     * Build a parameterized IN clause for the given values.
     *
     * @param array<int, mixed> $values Values to match
     * @param string            $prefix Named-parameter prefix
     *
     * @return array{0: string, 1: array<string, mixed>} [sqlFragment, params],
     *                                                   e.g. ['IN (:p0,:p1)', ['p0' => 1, 'p1' => 2]]
     */
    public function inClause(array $values, string $prefix = 'p'): array;

    /**
     * Render a column reference suitable for comparing TEXT/CLOB values.
     *
     * ANSI: passthrough. Moodle: wraps in `sql_compare_text()`.
     */
    public function compareText(string $column): string;

    /**
     * Compile the trailing LIMIT/OFFSET clause for the given (nullable) bounds.
     *
     * Engines disagree on offset-without-limit: SQLite needs the `LIMIT -1`
     * sentinel, PostgreSQL accepts a bare `OFFSET n` (or `LIMIT ALL OFFSET n`),
     * MySQL needs a max-row sentinel. Owning the clause here lets each dialect
     * emit valid SQL instead of the query builder hard-coding one engine's idiom.
     * The returned string carries its own leading space so callers append it
     * directly; an empty string means "no bounds".
     *
     * @return string e.g. ' LIMIT 10 OFFSET 20', ' LIMIT 10', ''
     *
     * @since 0.6.0 required method; external implementers must add it
     */
    public function limitOffset(?int $limit, ?int $offset): string;

    /**
     * Compile the row-locking clause for a SELECT, or '' when unsupported.
     *
     * `$mode` is 'update' (exclusive, FOR UPDATE) or 'share' (shared, FOR
     * SHARE). The returned string carries its own leading space so callers
     * append it directly; engines without row-lock syntax (e.g. SQLite) return
     * '' so the query stays valid. Only meaningful inside a transaction.
     *
     * @param string $mode 'update' | 'share'
     *
     * @since 0.9.0 required method; external implementers must add it
     */
    public function lockClause(string $mode): string;

    /**
     * Compile the conflict-resolution clause for an INSERT upsert.
     *
     * `$uniqueBy` are the columns whose collision triggers the update (the
     * unique/primary-key target); `$update` are the columns overwritten on
     * conflict (an empty list means "do nothing"). The returned string carries
     * its own leading space so callers append it directly after the VALUES list.
     *
     * Standard/SQLite/PostgreSQL: ` ON CONFLICT (a) DO UPDATE SET b = excluded.b`;
     * MySQL dialects emit ` ON DUPLICATE KEY UPDATE b = VALUES(b)` instead.
     *
     * @param list<string> $uniqueBy
     * @param list<string> $update
     *
     * @since 0.10.0 required method; external implementers must add it
     */
    public function upsertClause(array $uniqueBy, array $update): string;
}
