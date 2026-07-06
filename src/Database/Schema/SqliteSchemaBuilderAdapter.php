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

use Middag\Framework\Database\Contract\ConnectionInterface;
use Middag\Framework\Database\Contract\SchemaBuilderAdapterInterface;
use Middag\Framework\Exception\MiddagPersistenceException;
use Throwable;

/**
 * SQLite DDL adapter for standalone deployments using ext-pdo_sqlite.
 *
 * Differs from {@see MysqlSchemaBuilderAdapter}
 * (which targets MySQL syntax) by emitting SQLite-native column types and using
 * `INTEGER PRIMARY KEY AUTOINCREMENT` instead of `AUTO_INCREMENT`.
 *
 * @api
 */
final readonly class SqliteSchemaBuilderAdapter implements SchemaBuilderAdapterInterface
{
    public function __construct(private ConnectionInterface $connection) {}

    public function createTable(array $descriptor): void
    {
        $table = $descriptor['name'];
        $cols = [];
        $hasSequence = false;

        foreach ($descriptor['columns'] ?? [] as $col) {
            if (!empty($col['sequence'])) {
                $cols[] = sprintf('%s INTEGER PRIMARY KEY AUTOINCREMENT', $col['name']);
                $hasSequence = true;

                continue;
            }

            $cols[] = $this->columnDdl($col);
        }

        // A sequence column carries an inline PRIMARY KEY; otherwise honour a
        // composite PK declared in descriptor['keys'] as a table constraint.
        if (!$hasSequence) {
            $primaryKey = $this->primaryKeyColumns($descriptor);

            if ($primaryKey !== []) {
                $cols[] = sprintf('PRIMARY KEY (%s)', implode(', ', $primaryKey));
            }
        }

        $sql = sprintf('CREATE TABLE IF NOT EXISTS %s (%s)', $table, implode(', ', $cols));
        $this->connection->execute($sql);

        // SQLite has no inline INDEX clause: emit declared indexes after the
        // table (idempotent via CREATE INDEX IF NOT EXISTS in addIndex()).
        foreach ($descriptor['indexes'] ?? [] as $index) {
            $this->addIndex($table, $index);
        }
    }

    public function dropTable(string $tableName): void
    {
        $this->connection->execute(sprintf('DROP TABLE IF EXISTS %s', $tableName));
    }

    public function addColumn(string $tableName, array $column): void
    {
        $this->connection->execute(sprintf('ALTER TABLE %s ADD COLUMN %s', $tableName, $this->columnDdl($column)));
    }

    public function dropColumn(string $tableName, string $columnName): void
    {
        $this->connection->execute(sprintf('ALTER TABLE %s DROP COLUMN %s', $tableName, $columnName));
    }

    public function addIndex(string $tableName, array $index): void
    {
        $unique = empty($index['unique']) ? '' : 'UNIQUE';
        $cols = implode(', ', $this->indexFields($index));
        // Qualify the descriptor's (table-local) index name with the table.
        // MIDDAG descriptors name indexes per table (idiomatic for MySQL/Moodle,
        // whose index namespace is per-table), but SQLite namespaces indexes per
        // schema, so a bare name reused across tables (e.g. `status`, `userid`)
        // collides — and `CREATE INDEX IF NOT EXISTS` silently no-ops the
        // duplicate, dropping the second table's index. The auto-generated
        // fallback is already table-qualified. Mirrors DbalSchemaBuilderAdapter.
        $explicit = (string) ($index['name'] ?? '');
        $name = $explicit !== ''
            ? sprintf('%s_%s', $tableName, $explicit)
            : sprintf('idx_%s_%s', $tableName, str_replace(', ', '_', $cols));
        $this->connection->execute(sprintf('CREATE %s INDEX IF NOT EXISTS %s ON %s (%s)', $unique, $name, $tableName, $cols));
    }

    public function dropIndex(string $tableName, string $indexName): void
    {
        $this->connection->execute(sprintf('DROP INDEX IF EXISTS %s', $indexName));
    }

    public function tableExists(string $tableName): bool
    {
        try {
            $row = $this->connection->fetch(
                "SELECT name FROM sqlite_master WHERE type = 'table' AND name = ?",
                [$tableName],
            );

            return $row !== null;
        } catch (Throwable) {
            return false;
        }
    }

    public function columnExists(string $tableName, string $columnName): bool
    {
        try {
            $rows = $this->connection->fetchAll(sprintf('PRAGMA table_info(%s)', $tableName));
            foreach ($rows as $row) {
                if (($row['name'] ?? null) === $columnName) {
                    return true;
                }
            }

            return false;
        } catch (Throwable) {
            return false;
        }
    }

    /** @param array<string, mixed> $col */
    private function columnDdl(array $col): string
    {
        $name = $col['name'];
        $type = $this->mapType($col);
        $null = empty($col['notnull']) ? 'NULL' : 'NOT NULL';
        $default = '';

        if (array_key_exists('default', $col)) {
            $default = 'DEFAULT ' . (is_numeric($col['default'])
                ? (string) $col['default']
                : "'" . addslashes((string) $col['default']) . "'");
        }

        return trim(sprintf('%s %s %s %s', $name, $type, $null, $default));
    }

    /** @param array<string, mixed> $col */
    private function mapType(array $col): string
    {
        $type = strtolower((string) ($col['type'] ?? 'text'));

        return match ($type) {
            'int', 'integer', 'bigint', 'smallint' => 'INTEGER',
            'char', 'varchar' => 'TEXT', // SQLite has no fixed-length string types
            'text', 'longtext' => 'TEXT',
            'float', 'number', 'decimal', 'numeric' => 'REAL',
            'datetime', 'date' => 'TEXT',
            'boolean', 'bool' => 'INTEGER',
            'blob', 'binary' => 'BLOB',
            default => 'TEXT',
        };
    }

    /**
     * Resolve index column names, tolerating both descriptor keys in use
     * ('fields' and 'columns'). An index with no resolvable columns is a
     * malformed descriptor: throw rather than emit a corrupt empty `()`.
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
     * typed 'primary'). Returns [] when none — the caller then relies on the
     * sequence column's inline PRIMARY KEY instead.
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
