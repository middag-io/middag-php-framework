<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Kernel\Module;

use Middag\Framework\Kernel\Contract\HookRegisterInterface;

/**
 * Base class for module hook registration.
 *
 * Concrete hook classes in any module's hook/ directory should extend this
 * class and override registerActions() and/or registerFilters() as needed.
 *
 * The register() entry point is called automatically by
 * AbstractModule::registerHooks() during the boot phase.
 *
 * Usage:
 *
 *   class RegisterHookManager extends AbstractHookRegister
 *   {
 *       protected static function registerActions(): void
 *       {
 *           HookFacade::addAction('my_event', static function (array $args): void { ... });
 *       }
 *   }
 *
 * @internal
 */
abstract class AbstractHookRegister implements HookRegisterInterface
{
    /**
     * Calls registerActions() and registerFilters() in sequence.
     * Override only if the registration order must differ.
     */
    public static function register(): void
    {
        static::registerActions();
        static::registerFilters();
    }

    /**
     * Register action listeners for this module.
     * Override to add HookFacade::addAction() calls.
     */
    protected static function registerActions(): void {}

    /**
     * Register filter listeners for this module.
     * Override to add HookFacade::addFilter() calls.
     */
    protected static function registerFilters(): void {}
}
