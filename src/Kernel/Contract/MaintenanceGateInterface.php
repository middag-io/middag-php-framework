<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Kernel\Contract;

use Middag\Framework\Kernel\Bootstrap\NullMaintenanceGate;

/**
 * Bridge contract: reports whether the host platform is in a state where MIDDAG
 * must stand down (core upgrade running, install in progress, maintenance mode).
 *
 * The framework cannot probe the host, so this is an adapter-implemented seam.
 * The bootstrap consults it to decide whether to bring the kernel up; when the
 * host is under maintenance the kernel should not boot its modules / routes, so
 * a half-upgraded core is never driven by MIDDAG code.
 *
 * Reference implementations:
 *   - Moodle:    `isset($CFG->upgraderunning) || during_initial_install()`
 *   - WordPress: `wp_is_maintenance_mode()` / `.maintenance` file presence
 *
 * Default OSS impl: {@see NullMaintenanceGate}
 * (standalone is never under host maintenance).
 *
 * @api
 */
interface MaintenanceGateInterface
{
    /**
     * True when the host is mid-upgrade / installing / in maintenance and MIDDAG
     * should not bring its kernel up.
     */
    public function isUnderMaintenance(): bool;
}
