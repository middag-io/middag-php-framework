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
 * Optional capability of a {@see SchemaBuilderAdapterInterface}: answer whether
 * a live table already carries an index over a given ordered column set.
 *
 * Kept separate from SchemaBuilderAdapterInterface so existing adapter
 * implementations keep working. MigrationRunner::upgrade() converges the
 * descriptor indexes of already-existing tables only through adapters that
 * implement this interface; the others keep the columns-only behaviour.
 *
 * @api
 */
interface IndexIntrospectionInterface
{
    /**
     * Check whether $tableName has an index (of any name, unique or not)
     * covering exactly $columns, in that order.
     *
     * @param string       $tableName physical table name
     * @param list<string> $columns   ordered column names
     */
    public function indexExistsOnColumns(string $tableName, array $columns): bool;
}
