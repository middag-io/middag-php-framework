<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Database\Enum;

use Middag\Framework\Database\Contract\ConnectionAdapterInterface;

/**
 * Optional persistence capabilities a host database may or may not support.
 *
 * A {@see ConnectionAdapterInterface} reports
 * its capabilities through supports(); callers gate optional behaviour on the
 * result and fall back instead of emitting SQL the host rejects. This keeps a
 * single contract usable across hosts with very different feature sets
 * (Moodle/$DB, WordPress/$wpdb, standalone PDO, Eloquent).
 *
 * @api
 */
enum Capability: string
{
    /**
     * Run a callable atomically inside a transaction.
     *
     * Standalone PDO and most engines support this. WordPress relies on raw
     * `START TRANSACTION` and is InnoDB-only, so a $wpdb adapter may report
     * false depending on the table engine.
     */
    case Transactions = 'transactions';

    /**
     * Stream large result sets lazily via cursor() instead of buffering.
     *
     * Backed by an unbuffered cursor (PDO) or a native recordset
     * (Moodle get_recordset_sql) so memory stays bounded on big reads.
     */
    case Streaming = 'streaming';

    /**
     * Use JSON-path predicates inside WHERE clauses.
     *
     * Available on modern MySQL/PostgreSQL; absent on legacy engines.
     */
    case JsonWhere = 'json_where';

    /**
     * Return affected/inserted row data from a write (SQL RETURNING).
     *
     * PostgreSQL supports it; MySQL and Moodle's DML do not.
     */
    case Returning = 'returning';

    /**
     * Insert-or-update in a single statement (INSERT ... ON CONFLICT / upsert).
     */
    case Upsert = 'upsert';

    /**
     * Introspect or diff the schema (DDL).
     *
     * False on hosts that own their own schema lifecycle, e.g. Moodle, where
     * install.xml / db/upgrade.php (XMLDB) are the only sanctioned path.
     */
    case SchemaDiff = 'schema_diff';

    /**
     * Acquire row-level locks within a transaction (SELECT ... FOR UPDATE /
     * FOR SHARE) to serialise concurrent readers and writers.
     *
     * Supported on MySQL/InnoDB and PostgreSQL; SQLite has no row-lock syntax
     * (it locks the whole database), so a SQLite-backed adapter reports false.
     */
    case RowLock = 'row_lock';
}
