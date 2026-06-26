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

/**
 * Contract for hook registration classes discovered by AbstractModule::registerHooks().
 *
 * Any class placed in a module's hook/ directory must implement this interface
 * so that the framework's auto-discovery mechanism can call register() at boot time.
 *
 * @api
 */
interface HookRegisterInterface
{
    /**
     * Entry point called by AbstractModule::registerHooks() during the boot phase.
     * Must register all actions and filters for the module.
     */
    public static function register(): void;
}
