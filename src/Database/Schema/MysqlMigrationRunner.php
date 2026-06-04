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

/**
 * Concrete MigrationRunner for MySQL/MariaDB deployments.
 *
 * Wires together MysqlSchemaBuilderAdapter + MysqlVersionTracker so that apps
 * without a platform-specific runner (Moodle, WordPress) can install and
 * upgrade schemas using a plain PDO connection on MySQL or MariaDB.
 *
 * Usage:
 *   $runner = MysqlMigrationRunner::make($builder, $connection, 'my_lib');
 *   $runner->install();
 *   $runner->upgrade($runner->getInstalledVersion());
 *
 * @api
 */
class MysqlMigrationRunner extends MigrationRunner
{
    /**
     * Build a ready-to-use runner for a given SchemaBuilder and connection.
     *
     * @param string $libKey unique key identifying the library whose version is tracked
     */
    public static function make(
        SchemaBuilder $builder,
        ConnectionInterface $connection,
        string $libKey = 'default',
    ): self {
        $adapter = new MysqlSchemaBuilderAdapter($connection);
        $tracker = new MysqlVersionTracker($connection, $libKey);

        return new self($builder, $adapter, $tracker);
    }
}
