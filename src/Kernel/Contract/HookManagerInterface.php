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

use Middag\Framework\Kernel\Facade\HookFacade;
use Middag\Framework\Kernel\Manager\HookManager;

/**
 * Contract for the Hook Manager.
 *
 * Provides a hook system of "filters" and "actions".
 *
 * Filters: Modify a value and return it.
 * Actions: Execute side effects, no return value.
 *
 * WordPress-parity surface: add/remove/has for filters and actions, plus the
 * dispatch-introspection trio ({@see self::currentFilter()}, {@see self::doingAction()},
 * {@see self::didAction()}). Event loops are broken the WP way — detach with
 * {@see self::removeAction()} before re-dispatching, or guard re-entry with
 * {@see self::doingAction()}. The contract does NOT auto-block re-entrancy.
 *
 * Instance-based seam: the bootstrap binds an implementation as a service, so
 * adapters can swap it (e.g. a host bridge that delegates to the platform's
 * native hooks) and tests can isolate via a fresh instance or a mock. For
 * ergonomic `Hook::addFilter()` call sites, see {@see HookFacade}.
 *
 * @api
 *
 * @see HookManager The default in-memory implementation shipped with the framework.
 * @see HookFacade  Static proxy that resolves this contract from the container.
 */
interface HookManagerInterface
{
    /**
     * Register a new filter callback.
     *
     * @param string   $tag      Filter identifier
     * @param callable $function Callback to execute
     * @param int      $priority Lower = run earlier
     * @param int      $args     Number of accepted arguments
     */
    public function addFilter(string $tag, callable $function, int $priority = 10, int $args = 1): void;

    /**
     * Detach a filter callback previously registered at $priority.
     *
     * Matching is by strict identity: pass back the same callable reference used
     * in {@see self::addFilter()} (same closure instance / `[object, method]`).
     *
     * @return bool true if at least one callback was removed
     */
    public function removeFilter(string $tag, callable $function, int $priority = 10): bool;

    /**
     * Apply all registered filters to a value.
     *
     * @param string $tag     Filter identifier
     * @param mixed  $value   Initial value
     * @param mixed  ...$args Additional parameters passed to callbacks
     *
     * @return mixed Filtered value
     */
    public function applyFilters(string $tag, mixed $value, mixed ...$args): mixed;

    /**
     * Register a new action callback.
     *
     * @param string   $tag      Action identifier
     * @param callable $function Callback to execute
     * @param int      $priority Lower = run earlier
     * @param int      $args     Number of accepted arguments
     */
    public function addAction(string $tag, callable $function, int $priority = 10, int $args = 1): void;

    /**
     * Detach an action callback previously registered at $priority.
     *
     * Matching is by strict identity: pass back the same callable reference used
     * in {@see self::addAction()}. This is the WordPress-style loop break — detach
     * before re-dispatching, then re-attach.
     *
     * @return bool true if at least one callback was removed
     */
    public function removeAction(string $tag, callable $function, int $priority = 10): bool;

    /**
     * Execute all callbacks registered for a given action.
     *
     * @param string $tag     Action identifier
     * @param mixed  ...$args Parameters passed to callbacks
     */
    public function doAction(string $tag, mixed ...$args): void;

    /**
     * Check whether any callback is registered for the given filter.
     */
    public function hasFilter(string $tag): bool;

    /**
     * Check whether any callback is registered for the given action.
     */
    public function hasAction(string $tag): bool;

    /**
     * The hook tag currently being dispatched (innermost), or null if none.
     *
     * Mirrors WordPress `current_filter()`; reports the top of the dispatch
     * stack for both filters and actions.
     */
    public function currentFilter(): ?string;

    /**
     * Whether a dispatch is in progress — for $tag specifically, or any when null.
     *
     * Use to refuse re-entrant dispatch and break event loops, e.g.
     * `if ($hooks->doingAction('save')) { return; }`.
     */
    public function doingAction(?string $tag = null): bool;

    /**
     * How many times {@see self::doAction()} has been invoked for $tag this request.
     */
    public function didAction(string $tag): int;

    /**
     * Clear all registered hooks. Essential for unit testing isolation.
     */
    public function reset(): void;
}
