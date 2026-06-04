<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Database;

use Middag\Framework\Database\Contract\SqlDialectInterface;

/**
 * Standard (ANSI) SQL dialect for the {@see PdoConnectionAdapter}.
 *
 * A near-passthrough dialect: table names and text columns are used verbatim,
 * and IN clauses are emitted with named placeholders (`:p0`, `:p1`, …)
 * compatible with PDO. Host adapters (Moodle, WordPress) ship their own
 * dialect implementing the same contract.
 *
 * @api
 */
final readonly class StandardSqlDialect implements SqlDialectInterface
{
    public function table(string $logicalName): string
    {
        return $logicalName;
    }

    public function inClause(array $values, string $prefix = 'p'): array
    {
        if ($values === []) {
            // Match nothing: a self-contradictory predicate keeps callers' SQL valid.
            return ['IN (NULL) AND 1 = 0', []];
        }

        $placeholders = [];
        $params = [];

        foreach (array_values($values) as $index => $value) {
            $name = $prefix . $index;
            $placeholders[] = ':' . $name;
            $params[$name] = $value;
        }

        return ['IN (' . implode(', ', $placeholders) . ')', $params];
    }

    public function compareText(string $column): string
    {
        return $column;
    }

    public function limitOffset(?int $limit, ?int $offset): string
    {
        if ($limit !== null && $offset !== null) {
            return sprintf(' LIMIT %d OFFSET %d', $limit, $offset);
        }

        if ($limit !== null) {
            return sprintf(' LIMIT %d', $limit);
        }

        if ($offset !== null) {
            // SQLite requires a LIMIT before OFFSET; -1 means "no upper bound".
            // Engine-specific dialects (PostgreSQL, MySQL) implement their own
            // SqlDialectInterface and emit LIMIT ALL / a max-row sentinel instead.
            return sprintf(' LIMIT -1 OFFSET %d', $offset);
        }

        return '';
    }

    public function lockClause(string $mode): string
    {
        return match ($mode) {
            'update' => ' FOR UPDATE',
            'share' => ' FOR SHARE',
            default => '',
        };
    }

    public function upsertClause(array $uniqueBy, array $update): string
    {
        $conflict = implode(', ', $uniqueBy);

        if ($update === []) {
            return sprintf(' ON CONFLICT (%s) DO NOTHING', $conflict);
        }

        $assignments = implode(', ', array_map(
            static fn (string $column): string => sprintf('%s = excluded.%s', $column, $column),
            $update,
        ));

        return sprintf(' ON CONFLICT (%s) DO UPDATE SET %s', $conflict, $assignments);
    }
}
