<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Persistence;

use Middag\Framework\Database\Contract\ConnectionAdapterInterface;
use Middag\Framework\Persistence\Contract\ConnectionResolverInterface;

/**
 * Trivial single-connection resolver.
 *
 * The default for standalone apps and tests: one adapter, returned for every
 * name. Wire it once via Model::setConnection() and every model resolves to
 * the same database.
 *
 * @api
 */
final readonly class SingleConnectionResolver implements ConnectionResolverInterface
{
    public function __construct(private ConnectionAdapterInterface $connection) {}

    public function connection(?string $name = null): ConnectionAdapterInterface
    {
        return $this->connection;
    }
}
