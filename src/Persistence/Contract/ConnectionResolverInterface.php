<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Persistence\Contract;

use Middag\Framework\Database\Contract\ConnectionAdapterInterface;

/**
 * Resolves a named {@see ConnectionAdapterInterface} for the Active Record layer.
 *
 * A Model never holds a connection directly; it asks the resolver for one by
 * (optional) name. Standalone apps wire a single-connection resolver; hosts
 * with several databases can return different adapters per name. This keeps
 * the Model host-agnostic and lets the same model class run on PDO, Moodle
 * $DB, or WordPress $wpdb depending on the resolver in play.
 *
 * @api
 */
interface ConnectionResolverInterface
{
    /**
     * Resolve the connection adapter for the given logical name.
     *
     * @param null|string $name connection name; null selects the default
     */
    public function connection(?string $name = null): ConnectionAdapterInterface;
}
