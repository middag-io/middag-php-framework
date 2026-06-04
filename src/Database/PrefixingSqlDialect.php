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
 * Decorator that prepends a fixed table prefix to logical names.
 *
 * Wraps any {@see SqlDialectInterface} and prefixes the logical table name in
 * {@see self::table()} before delegating to the inner dialect; every other
 * method passes through verbatim. Useful standalone or in development (no host
 * adapter) where the database uses a shared prefix such as `wp_`/`mdl_`:
 *
 * ```php
 * $dialect = new PrefixingSqlDialect(new StandardSqlDialect(), 'mysite_');
 * $dialect->table('middag_items'); // 'mysite_middag_items'
 * ```
 *
 * Host adapters keep owning their own physical prefixing (`{table}` bracing on
 * Moodle, `wp_` on WordPress), so this decorator is meant to wrap the prefixless
 * default dialect, not a host dialect that already prefixes.
 *
 * @api
 */
final readonly class PrefixingSqlDialect implements SqlDialectInterface
{
    public function __construct(
        private SqlDialectInterface $inner,
        private string $prefix,
    ) {}

    public function table(string $logicalName): string
    {
        return $this->inner->table($this->prefix . $logicalName);
    }

    public function inClause(array $values, string $prefix = 'p'): array
    {
        return $this->inner->inClause($values, $prefix);
    }

    public function compareText(string $column): string
    {
        return $this->inner->compareText($column);
    }

    public function limitOffset(?int $limit, ?int $offset): string
    {
        return $this->inner->limitOffset($limit, $offset);
    }

    public function lockClause(string $mode): string
    {
        return $this->inner->lockClause($mode);
    }

    public function upsertClause(array $uniqueBy, array $update): string
    {
        return $this->inner->upsertClause($uniqueBy, $update);
    }
}
