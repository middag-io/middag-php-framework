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
 * Contract for platform-specific DDL generators.
 *
 * Each platform adapter (Moodle xmldb, WordPress dbDelta, raw SQL) implements
 * this interface to translate platform-agnostic schema descriptors into
 * executable DDL operations.
 *
 * @api
 */
interface SchemaBuilderAdapterInterface
{
    /**
     * Create a table from a schema descriptor.
     *
     * Called during install or when a table is missing.
     *
     * @param array<string, mixed> $descriptor schema descriptor from SchemaBuilder::table()
     */
    public function createTable(array $descriptor): void;

    /**
     * Drop an existing table.
     *
     * @param string $tableName physical table name (without platform prefix if applicable)
     */
    public function dropTable(string $tableName): void;

    /**
     * Add a column to an existing table.
     *
     * @param string               $tableName physical table name
     * @param array<string, mixed> $column    column descriptor (same format as in the schema descriptor)
     */
    public function addColumn(string $tableName, array $column): void;

    /**
     * Drop a column from an existing table.
     *
     * @param string $tableName  physical table name
     * @param string $columnName column to drop
     */
    public function dropColumn(string $tableName, string $columnName): void;

    /**
     * Add an index to an existing table.
     *
     * @param string               $tableName physical table name
     * @param array<string, mixed> $index     index descriptor (same format as in the schema descriptor)
     */
    public function addIndex(string $tableName, array $index): void;

    /**
     * Drop an index from an existing table.
     *
     * @param string $tableName physical table name
     * @param string $indexName index name to drop
     */
    public function dropIndex(string $tableName, string $indexName): void;

    /**
     * Check whether a table exists in the platform's database.
     *
     * @param string $tableName physical table name
     */
    public function tableExists(string $tableName): bool;

    /**
     * Check whether a column exists in a table.
     *
     * @param string $tableName  physical table name
     * @param string $columnName column name to check
     */
    public function columnExists(string $tableName, string $columnName): bool;
}
