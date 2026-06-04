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

/**
 * Platform-agnostic version storage for schema runners.
 *
 * Each platform (Moodle, WordPress) provides its own implementation.
 * The key identifies which lib's schema version is being tracked.
 *
 * @api
 */
interface VersionTrackerInterface
{
    /**
     * Return the currently installed schema version, or 0 when not yet installed.
     */
    public function getVersion(): int;

    /**
     * Persist the schema version after a successful migration.
     */
    public function setVersion(int $version): void;
}
