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
 * MySQL/MariaDB DDL adapter for zero-dependency standalone deployments.
 *
 * Translates MIDDAG schema descriptors to MySQL-flavoured SQL (AUTO_INCREMENT,
 * TINYINT, DOUBLE) and executes them via a ConnectionInterface. For PostgreSQL
 * or engine-agnostic DDL, bind {@see DbalSchemaBuilderAdapter} instead (opt-in
 * doctrine/dbal), which emits the correct per-engine syntax.
 *
 * Platform adapters (Moodle xmldb, WordPress dbDelta) provide their own
 * SchemaBuilderAdapterInterface implementations.
 *
 * @api
 */
final readonly class MysqlSchemaBuilderAdapter implements SchemaBuilderAdapterInterface
{
    public function __construct(private ConnectionInterface $connection) {}

    public function createTable(array $descriptor): void
    {
        $table = $descriptor['name'];
        $cols = [];
        $sequenceKey = null;

        foreach ($descriptor['columns'] ?? [] as $col) {
            $line = $this->columnDdl($col);
            $cols[] = $line;

            if (!empty($col['sequence'])) {
                $sequenceKey = (string) $col['name'];
            }
        }

        // A sequence column owns the PRIMARY KEY (AUTO_INCREMENT must be keyed);
        // otherwise honour a composite PK declared in descriptor['keys'].
        $primaryKey = $sequenceKey !== null ? [$sequenceKey] : $this->primaryKeyColumns($descriptor);

        if ($primaryKey !== []) {
            $cols[] = sprintf('PRIMARY KEY (%s)', implode(', ', $primaryKey));
        }

        foreach ($descriptor['indexes'] ?? [] as $idx) {
            $unique = empty($idx['unique']) ? '' : 'UNIQUE ';
            $fields = implode(', ', $this->indexFields($idx));
            $idxName = $idx['name'];
            $cols[] = sprintf('%sINDEX %s (%s)', $unique, $idxName, $fields);
        }

        $colsSql = implode(",\n  ", $cols);
        $this->connection->execute("CREATE TABLE IF NOT EXISTS {$table} (\n  {$colsSql}\n)");
    }

    public function dropTable(string $tableName): void
    {
        $this->connection->execute('DROP TABLE IF EXISTS ' . $tableName);
    }

    public function addColumn(string $tableName, array $column): void
    {
        $ddl = $this->columnDdl($column);
        $this->connection->execute(sprintf('ALTER TABLE %s ADD COLUMN %s', $tableName, $ddl));
    }

    public function dropColumn(string $tableName, string $columnName): void
    {
        $this->connection->execute(sprintf('ALTER TABLE %s DROP COLUMN %s', $tableName, $columnName));
    }

    public function addIndex(string $tableName, array $index): void
    {
        $unique = empty($index['unique']) ? '' : 'UNIQUE ';
        $fields = implode(', ', $this->indexFields($index));
        $name = $index['name'];
        $this->connection->execute(sprintf('CREATE %sINDEX %s ON %s (%s)', $unique, $name, $tableName, $fields));
    }

    public function dropIndex(string $tableName, string $indexName): void
    {
        // MySQL/MariaDB reject `IF EXISTS` on DROP INDEX (valid only on
        // DROP TABLE/DATABASE), so emit the bare statement and swallow the
        // "index does not exist" error to keep the call idempotent.
        try {
            $this->connection->execute(sprintf('DROP INDEX %s ON %s', $indexName, $tableName));
        } catch (Throwable) {
            // Index already absent — nothing to drop.
        }
    }

    /**
     * Reports whether $tableName exists.
     *
     * Probes information_schema.tables; engines that lack that catalogue
     * (SQLite and SQLite-like) throw, so on any Throwable it falls back to
     * `SELECT 1 FROM <table> WHERE 1=0` — a no-row probe that succeeds when
     * the table is present and throws again (→ false) when it is not.
     *
     * @param string $tableName unqualified table name to look up
     */
    public function tableExists(string $tableName): bool
    {
        try {
            $row = $this->connection->fetch(
                'SELECT 1 FROM information_schema.tables WHERE table_name = ? LIMIT 1',
                [$tableName]
            );

            return $row !== null;
        } catch (Throwable) {
            // Fallback for SQLite
            try {
                $this->connection->execute(sprintf('SELECT 1 FROM %s WHERE 1=0', $tableName));

                return true;
            } catch (Throwable) {
                return false;
            }
        }
    }

    public function columnExists(string $tableName, string $columnName): bool
    {
        try {
            $row = $this->connection->fetch(
                'SELECT 1 FROM information_schema.columns WHERE table_name = ? AND column_name = ? LIMIT 1',
                [$tableName, $columnName]
            );

            return $row !== null;
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
        $auto = empty($col['sequence']) ? '' : 'AUTO_INCREMENT';
        $default = '';

        if (isset($col['default']) && empty($col['sequence'])) {
            $default = 'DEFAULT ' . (is_numeric($col['default'])
                ? $col['default']
                : "'" . addslashes((string) $col['default']) . "'");
        }

        return trim(sprintf('%s %s %s %s %s', $name, $type, $null, $auto, $default));
    }

    /** @param array<string, mixed> $col */
    private function mapType(array $col): string
    {
        $type = strtolower((string) ($col['type'] ?? 'text'));
        $len = $col['length'] ?? null;

        return match ($type) {
            'int', 'integer' => $len !== null ? sprintf('INT(%s)', $len) : 'INT',
            'bigint' => 'BIGINT',
            'smallint' => 'SMALLINT',
            'char', 'varchar' => 'VARCHAR(' . ($len ?? 255) . ')',
            'text', 'longtext' => 'TEXT',
            'float', 'number' => 'DOUBLE',
            'decimal', 'numeric' => 'DECIMAL(' . ($col['length'] ?? 10) . ',' . ($col['decimals'] ?? 2) . ')',
            'datetime' => 'DATETIME',
            'date' => 'DATE',
            'boolean', 'bool' => 'TINYINT(1)',
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
