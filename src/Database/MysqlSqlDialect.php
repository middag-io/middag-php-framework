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
 * MySQL/MariaDB SQL dialect for the {@see PdoConnectionAdapter}.
 *
 * The MySQL family disagrees with ANSI/SQLite on three idioms the generic
 * repositories emit as raw SQL, so this dialect owns them:
 *  - `limitOffset()` uses the documented max-rows sentinel for "offset without
 *    limit" (`LIMIT 18446744073709551615 OFFSET n`) instead of SQLite's `-1`;
 *  - `upsertClause()` emits `ON DUPLICATE KEY UPDATE` instead of `ON CONFLICT`;
 *  - `lockClause()` defaults to `FOR UPDATE` for any non-share mode.
 *
 * `table()`, `compareText()` and `inClause()` are the ANSI-compatible defaults
 * (verbatim names, named placeholders). A host adapter that also runs on the
 * MySQL family (e.g. Moodle) can extend this dialect and override only the
 * host-specific name/text/IN idioms, inheriting the MySQL SQL verbatim.
 *
 * Non-final so host adapters can extend it; readonly so a `final readonly`
 * subclass stays legal.
 *
 * @api
 */
readonly class MysqlSqlDialect implements SqlDialectInterface
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
        $hasLimit = $limit !== null && $limit >= 0;
        $hasOffset = $offset !== null && $offset > 0;

        if (!$hasLimit && !$hasOffset) {
            return '';
        }

        if (!$hasLimit) {
            // MySQL/MariaDB require a row-count when OFFSET is present; use the
            // documented max-rows sentinel so "offset without limit" stays valid.
            return ' LIMIT 18446744073709551615 OFFSET ' . $offset;
        }

        if (!$hasOffset) {
            return ' LIMIT ' . $limit;
        }

        return ' LIMIT ' . $limit . ' OFFSET ' . $offset;
    }

    public function lockClause(string $mode): string
    {
        return match ($mode) {
            'share' => ' FOR SHARE',
            default => ' FOR UPDATE',
        };
    }

    public function upsertClause(array $uniqueBy, array $update): string
    {
        if ($update === []) {
            // "do nothing" on conflict — MySQL has no NOTHING, so no-op the PK column.
            $noop = $uniqueBy[0] ?? null;

            return $noop === null ? '' : ' ON DUPLICATE KEY UPDATE ' . $noop . ' = ' . $noop;
        }

        $assignments = [];
        foreach ($update as $column) {
            $assignments[] = $column . ' = VALUES(' . $column . ')';
        }

        // $uniqueBy is implicit on MySQL (any unique/PK collision triggers the update);
        // the column list is honoured by the engine, not the SQL text.
        return ' ON DUPLICATE KEY UPDATE ' . implode(', ', $assignments);
    }
}
