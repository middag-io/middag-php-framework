<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Kernel\Facade;

use Middag\Framework\Kernel\Contract\HookManagerInterface;

/**
 * Static proxy to the {@see HookManagerInterface} service resolved from the
 * container.
 *
 * Restores WordPress-style `Hook::addFilter()` / `Hook::doAction()`
 * call-site ergonomics on top of the instance-based hook manager — the state
 * lives on the resolved service (swappable, mockable), not in static class
 * properties. Host adapters can go a step further and declare native global
 * helpers (e.g. WordPress `add_action()` in lib.php) that forward here.
 *
 * @method static void    addFilter(string $tag, callable $function, int $priority = 10, int $args = 1)
 * @method static bool    removeFilter(string $tag, callable $function, int $priority = 10)
 * @method static mixed   applyFilters(string $tag, mixed $value, mixed ...$args)
 * @method static void    addAction(string $tag, callable $function, int $priority = 10, int $args = 1)
 * @method static bool    removeAction(string $tag, callable $function, int $priority = 10)
 * @method static void    doAction(string $tag, mixed ...$args)
 * @method static bool    hasFilter(string $tag)
 * @method static bool    hasAction(string $tag)
 * @method static ?string currentFilter()
 * @method static bool    doingAction(?string $tag = null)
 * @method static int     didAction(string $tag)
 * @method static void    reset()
 *
 * @api
 */
final class HookFacade extends AbstractFacade
{
    public static function getFacadeAccessor(): string
    {
        return HookManagerInterface::class;
    }
}
