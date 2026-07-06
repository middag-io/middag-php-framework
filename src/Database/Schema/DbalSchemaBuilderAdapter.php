<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Database\Schema;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Middag\Framework\Database\Contract\SchemaBuilderAdapterInterface;
use Middag\Framework\Exception\MiddagPersistenceException;

/**
 * Engine-agnostic DDL adapter that delegates to doctrine/dbal.
 *
 * One adapter for every database DBAL supports (PostgreSQL, MySQL/MariaDB,
 * SQLite, SQL Server, Oracle…): DBAL emits the correct per-engine DDL from the
 * platform-agnostic MIDDAG schema descriptor, so PostgreSQL gets real `SERIAL`/
 * identity columns and boolean types instead of the MySQL-flavoured output of
 * {@see MysqlSchemaBuilderAdapter}.
 *
 * Opt-in: install `doctrine/dbal` (suggested) and bind a DBAL {@see Connection}.
 * The hand-rolled {@see MysqlSchemaBuilderAdapter}/{@see SqliteSchemaBuilderAdapter}
 * remain as zero-dependency fallbacks; bind this one on the same
 * {@see SchemaBuilderAdapterInterface} seam to gain multi-engine coverage.
 *
 * @api
 */
final readonly class DbalSchemaBuilderAdapter implements SchemaBuilderAdapterInterface
{
    public function __construct(private Connection $connection) {}

    public function createTable(array $descriptor): void
    {
        // Idempotent, mirroring the bespoke adapters' CREATE TABLE IF NOT EXISTS.
        if ($this->tableExists((string) $descriptor['name'])) {
            return;
        }

        $table = new Table((string) $descriptor['name']);
        $sequenceKey = null;

        foreach ($descriptor['columns'] ?? [] as $col) {
            $table->addColumn((string) $col['name'], $this->mapType($col), $this->columnOptions($col));

            if (!empty($col['sequence'])) {
                $sequenceKey = (string) $col['name'];
            }
        }

        // A sequence column owns the PRIMARY KEY (autoincrement must be keyed);
        // otherwise honour a composite PK declared in descriptor['keys'].
        $primaryKey = $sequenceKey !== null ? [$sequenceKey] : $this->primaryKeyColumns($descriptor);

        if ($primaryKey !== []) {
            $table->setPrimaryKey($primaryKey);
        }

        foreach ($descriptor['indexes'] ?? [] as $index) {
            $fields = $this->indexFields($index);
            $name = $this->qualifiedIndexName((string) $descriptor['name'], $index);

            if (empty($index['unique'])) {
                $table->addIndex($fields, $name);
            } else {
                $table->addUniqueIndex($fields, $name);
            }
        }

        $this->connection->createSchemaManager()->createTable($table);
    }

    public function dropTable(string $tableName): void
    {
        if ($this->tableExists($tableName)) {
            $this->connection->createSchemaManager()->dropTable($tableName);
        }
    }

    public function addColumn(string $tableName, array $column): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        $current = $schemaManager->introspectTable($tableName);
        $new = new Column((string) $column['name'], Type::getType($this->mapType($column)), $this->columnOptions($column));

        $schemaManager->alterTable(new TableDiff($current, addedColumns: [$new]));
    }

    public function dropColumn(string $tableName, string $columnName): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        $current = $schemaManager->introspectTable($tableName);

        if (!$current->hasColumn($columnName)) {
            return;
        }

        $schemaManager->alterTable(new TableDiff($current, droppedColumns: [$current->getColumn($columnName)]));
    }

    public function addIndex(string $tableName, array $index): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        $current = $schemaManager->introspectTable($tableName);
        $new = new Index((string) ($index['name'] ?? null) ?: null, $this->indexFields($index), !empty($index['unique']));

        $schemaManager->alterTable(new TableDiff($current, addedIndexes: [$new]));
    }

    public function dropIndex(string $tableName, string $indexName): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        $current = $schemaManager->introspectTable($tableName);

        if (!$current->hasIndex($indexName)) {
            return;
        }

        $schemaManager->alterTable(new TableDiff($current, droppedIndexes: [$current->getIndex($indexName)]));
    }

    public function tableExists(string $tableName): bool
    {
        return $this->connection->createSchemaManager()->tableExists($tableName);
    }

    public function columnExists(string $tableName, string $columnName): bool
    {
        $schemaManager = $this->connection->createSchemaManager();

        return $schemaManager->tableExists($tableName)
            && $schemaManager->introspectTable($tableName)->hasColumn($columnName);
    }

    /**
     * Map a MIDDAG column descriptor to its DBAL type name.
     *
     * @param array<string, mixed> $col
     */
    private function mapType(array $col): string
    {
        return match (strtolower((string) ($col['type'] ?? 'text'))) {
            'int', 'integer' => Types::INTEGER,
            'bigint' => Types::BIGINT,
            'smallint' => Types::SMALLINT,
            'char', 'varchar' => Types::STRING,
            'text', 'longtext' => Types::TEXT,
            'float', 'number' => Types::FLOAT,
            'decimal', 'numeric' => Types::DECIMAL,
            'datetime' => Types::DATETIME_MUTABLE,
            'date' => Types::DATE_MUTABLE,
            'boolean', 'bool' => Types::BOOLEAN,
            'blob', 'binary' => Types::BLOB,
            default => Types::TEXT,
        };
    }

    /**
     * Build the DBAL column options from a MIDDAG column descriptor.
     *
     * @param array<string, mixed> $col
     *
     * @return array<string, mixed>
     */
    private function columnOptions(array $col): array
    {
        $options = ['notnull' => !empty($col['notnull'])];
        $type = strtolower((string) ($col['type'] ?? 'text'));

        if (!empty($col['sequence'])) {
            $options['autoincrement'] = true;
            $options['notnull'] = true;
        } elseif (array_key_exists('default', $col)) {
            $options['default'] = $col['default'];
        }

        if (in_array($type, ['char', 'varchar'], true)) {
            $options['length'] = (int) ($col['length'] ?? 255);
        }

        if (in_array($type, ['decimal', 'numeric'], true)) {
            $options['precision'] = (int) ($col['length'] ?? 10);
            $options['scale'] = (int) ($col['decimals'] ?? 2);
        }

        return $options;
    }

    /**
     * Qualify a descriptor's (table-local) index name with the table name so the
     * emitted DDL is globally unique. MIDDAG descriptors name indexes per table
     * (idiomatic for MySQL/Moodle, whose index namespace is per-table), but the
     * engines this adapter targets — PostgreSQL, SQLite — namespace indexes per
     * schema, so a bare name reused across tables (e.g. `status`, `userid`)
     * collides. Prefixing with the table keeps the descriptors engine-neutral
     * while the DDL stays valid everywhere. Returns null when the descriptor
     * names no index, letting DBAL auto-generate a unique name.
     *
     * @param array<string, mixed> $index
     */
    private function qualifiedIndexName(string $tableName, array $index): ?string
    {
        $name = (string) ($index['name'] ?? '');

        return $name === '' ? null : $tableName . '_' . $name;
    }

    /**
     * Resolve index column names, tolerating both descriptor keys in use
     * ('fields' in the MySQL adapter, 'columns' in the SQLite adapter). An
     * index with no resolvable columns is a malformed descriptor: throw rather
     * than hand DBAL an empty column list.
     *
     * @param array<string, mixed> $index
     *
     * @return list<string>
     *
     * @throws MiddagPersistenceException when neither key yields columns
     */
    private function indexFields(array $index): array
    {
        $fields = $index['fields'] ?? $index['columns'] ?? [];
        $fields = is_array($fields) ? array_values(array_map('strval', $fields)) : [];

        if ($fields === []) {
            throw new MiddagPersistenceException(sprintf(
                'Index "%s" must declare at least one column (descriptor key "fields" or "columns").',
                $index['name'] ?? '(unnamed)',
            ));
        }

        return $fields;
    }

    /**
     * Composite PRIMARY KEY columns from descriptor['keys'] (entries explicitly
     * typed 'primary'). Returns [] when none — the caller then falls back to the
     * sequence-derived single-column key.
     *
     * Only an explicit `type => 'primary'` is treated as the primary key. Other
     * entries — foreign keys, or keys with no `type` — are skipped, so a foreign
     * key declared without a type is never mis-emitted as a PRIMARY KEY, and a
     * mixed/ordered keys array still yields the real primary regardless of order.
     *
     * @param array<string, mixed> $descriptor
     *
     * @return list<string>
     */
    private function primaryKeyColumns(array $descriptor): array
    {
        foreach ($descriptor['keys'] ?? [] as $key) {
            if (!is_array($key)) {
                continue;
            }
            if (($key['type'] ?? null) !== 'primary') {
                continue;
            }
            $fields = $key['fields'] ?? $key['columns'] ?? [];
            $fields = is_array($fields) ? array_values(array_map('strval', $fields)) : [];

            if ($fields !== []) {
                return $fields;
            }
        }

        return [];
    }
}
