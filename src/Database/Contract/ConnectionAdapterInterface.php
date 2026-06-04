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

use Middag\Framework\Database\Enum\Capability;

/**
 * Host database adapter — the single seam every platform implements.
 *
 * A ConnectionAdapter is the only object in the stack that knows a concrete
 * database: Moodle ($DB), WordPress ($wpdb), a standalone PDO connection, or an
 * optional Eloquent/Capsule connection. Everything above it — query builder,
 * repositories, domain — depends on this contract, never on a host API, so the
 * same domain code runs unchanged on every host.
 *
 * It composes three focused contracts and adds capability reporting:
 *  - raw SQL          ({@see ConnectionInterface})
 *  - record CRUD + streaming ({@see RecordConnectionInterface})
 *  - dialect rendering ({@see SqlDialectInterface}, via dialect())
 *
 * Adapters MUST keep this layer free of business/domain types: it speaks
 * tables, assoc-array records, SQL and bind params only. Mapping rows to
 * domain entities happens above, in repositories.
 *
 * @api
 */
interface ConnectionAdapterInterface extends RecordConnectionInterface
{
    /**
     * Report whether the host database supports an optional capability.
     *
     * Lets callers gate behaviour (e.g. fall back when RETURNING or
     * JSON_WHERE is unavailable) instead of emitting SQL the host rejects.
     */
    public function supports(Capability $feature): bool;

    /**
     * The SQL dialect helpers for this host — table bracing, IN clauses and
     * TEXT comparison — used when callers must emit raw SQL.
     */
    public function dialect(): SqlDialectInterface;
}
