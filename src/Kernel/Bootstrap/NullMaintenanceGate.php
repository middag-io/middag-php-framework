<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Kernel\Bootstrap;

use Middag\Framework\Kernel\Contract\MaintenanceGateInterface;

/**
 * Default OSS maintenance gate: the host is never under maintenance.
 *
 * Standalone (no host) has no upgrade/install/maintenance state to honour, so
 * the kernel always boots. Host adapters bind their own
 * {@see MaintenanceGateInterface} (Moodle/WordPress checks) in its place.
 *
 * @api
 */
final readonly class NullMaintenanceGate implements MaintenanceGateInterface
{
    public function isUnderMaintenance(): bool
    {
        return false;
    }
}
