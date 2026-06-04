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
use Middag\Framework\Database\Contract\VersionTrackerInterface;

/**
 * Zero-dependency {@see VersionTrackerInterface} implementation backed by an
 * ANSI SQL table — portable across MySQL/MariaDB and SQLite (plain DDL/DML over
 * {@see ConnectionInterface}, no dialect coupling despite the name).
 *
 * The `$libKey` constructor argument namespaces each lib's schema version as a
 * distinct row (keyed by `lib_key`) in the shared `_middag_schema_versions`
 * table, so independent libs track their versions side by side. The table is
 * auto-created on construction via the ensure-table path
 * (`CREATE TABLE IF NOT EXISTS`).
 *
 * Instantiated directly as a standalone primitive.
 *
 * @api
 */
final readonly class MysqlVersionTracker implements VersionTrackerInterface
{
    private const TABLE = '_middag_schema_versions';

    public function __construct(
        private ConnectionInterface $connection,
        private string $libKey,
    ) {
        $this->ensureTable();
    }

    /**
     * Return this lib's installed schema version, or 0 when its row is absent.
     *
     * @api
     */
    public function getVersion(): int
    {
        $row = $this->connection->fetch(
            'SELECT version FROM ' . self::TABLE . ' WHERE lib_key = ?',
            [$this->libKey]
        );

        return $row !== null ? (int) $row['version'] : 0;
    }

    /**
     * Upsert this lib's schema version row after a successful migration.
     *
     * @api
     */
    public function setVersion(int $version): void
    {
        $exists = $this->connection->fetch(
            'SELECT 1 FROM ' . self::TABLE . ' WHERE lib_key = ?',
            [$this->libKey]
        );

        if ($exists !== null) {
            $this->connection->execute(
                'UPDATE ' . self::TABLE . ' SET version = ? WHERE lib_key = ?',
                [$version, $this->libKey]
            );
        } else {
            $this->connection->execute(
                'INSERT INTO ' . self::TABLE . ' (lib_key, version) VALUES (?, ?)',
                [$this->libKey, $version]
            );
        }
    }

    private function ensureTable(): void
    {
        $this->connection->execute(
            'CREATE TABLE IF NOT EXISTS ' . self::TABLE . ' (
                lib_key VARCHAR(191) NOT NULL,
                version INT NOT NULL DEFAULT 0,
                PRIMARY KEY (lib_key)
            )'
        );
    }
}
