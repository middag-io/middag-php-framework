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

use Middag\Framework\Database\Contract\SchemaBuilderAdapterInterface;
use Middag\Framework\Database\Contract\VersionTrackerInterface;
use Middag\Framework\Exception\MiddagPersistenceException;

/**
 * Abstract cross-platform migration runner.
 *
 * Orchestrates install and upgrade operations for MIDDAG schema descriptors.
 * Platform adapters subclass this and provide the DDL adapter + version
 * tracking implementation.
 *
 * Usage pattern:
 *   1. Adapter subclass binds a SchemaBuilderAdapterInterface implementation.
 *   2. SchemaBuilder loads descriptors from db/schema/.
 *   3. install() creates all tables not yet present.
 *   4. upgrade(oldVersion) detects and applies schema changes since oldVersion.
 *
 * @api
 */
abstract class MigrationRunner
{
    public function __construct(
        protected readonly SchemaBuilder $builder,
        protected readonly SchemaBuilderAdapterInterface $adapter,
        protected readonly VersionTrackerInterface $tracker,
    ) {}

    /**
     * Install all tables described in the SchemaBuilder that do not yet exist.
     *
     * Idempotent — safe to call on an already-installed database.
     *
     * @throws MiddagPersistenceException on DDL failure
     */
    public function install(): void
    {
        foreach ($this->builder->all() as $descriptor) {
            $table = $descriptor['name'];

            if (!$this->adapter->tableExists($table)) {
                $this->adapter->createTable($descriptor);
            }
        }
    }

    /**
     * Upgrade schema from the given version to the current state.
     *
     * The base implementation adds columns that exist in descriptors but are
     * absent from the live schema. Subclasses may override for more complex
     * upgrade logic (e.g. index changes, renames).
     *
     * @param int $oldVersion the previously installed version number
     *
     * @throws MiddagPersistenceException on DDL failure
     */
    public function upgrade(int $oldVersion): void
    {
        foreach ($this->builder->all() as $descriptor) {
            $table = $descriptor['name'];

            if (!$this->adapter->tableExists($table)) {
                $this->adapter->createTable($descriptor);

                continue;
            }

            foreach ($descriptor['columns'] ?? [] as $column) {
                if (!$this->adapter->columnExists($table, $column['name'])) {
                    $this->adapter->addColumn($table, $column);
                }
            }
        }

        $this->onUpgrade($oldVersion);
    }

    /**
     * Return the current schema version managed by this runner.
     */
    public function getInstalledVersion(): int
    {
        return $this->tracker->getVersion();
    }

    /**
     * Persist the newly applied version so future runs know the current state.
     *
     * @param int $version the version that was just applied
     */
    public function setInstalledVersion(int $version): void
    {
        $this->tracker->setVersion($version);
    }

    /**
     * Override point for platform-specific upgrade steps at a given version.
     *
     * Called at the end of upgrade() after the base column-sync pass.
     *
     * @param int $oldVersion the version before this upgrade began
     */
    protected function onUpgrade(int $oldVersion): void {}
}
