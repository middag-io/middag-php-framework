<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Kernel\Manager;

use Middag\Framework\Kernel\Contract\HookManagerInterface;
use Middag\Framework\Kernel\Facade\HookFacade;
use Middag\Framework\Observability\Contract\ProfileCollectorInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Lightweight Hook Manager (filter/action hooks).
 *
 * Default {@see HookManagerInterface} implementation: an in-memory, per-instance
 * registry. Handles synchronous execution of filters and actions; registration
 * is handled by individual Modules during boot.
 *
 * Per-instance state (no global statics) so adapters can bind their own impl and
 * tests can isolate via a fresh instance. For `Hook::addFilter()` call-site
 * ergonomics, resolve it through {@see HookFacade}.
 *
 * WordPress-parity surface: add/remove/has for both filters and actions, plus the
 * dispatch-introspection trio ({@see self::currentFilter()}, {@see self::doingAction()},
 * {@see self::didAction()}). Loops are broken the WP way — a listener detaches
 * itself with {@see self::removeAction()} before re-dispatching and re-attaches
 * after, or guards with {@see self::doingAction()} to refuse re-entry. The manager
 * does NOT auto-block re-entrancy (same as WP); it gives the primitives to do so.
 *
 * Slow hook monitoring: callbacks exceeding the threshold trigger a debug
 * warning. Configure via {@see self::setSlowThreshold()} or disable by setting 0.
 * Default: 100ms.
 *
 * @internal
 *
 * @see HookManagerInterface The contract this class implements (the override seam).
 * @see HookFacade           Static proxy that resolves this service from the container.
 */
final class HookManager implements HookManagerInterface
{
    /** @var array<string, array<int, list<array{function: callable, accepted_args: int}>>> */
    private array $filters = [];

    /** @var array<string, array<int, list<array{function: callable, accepted_args: int}>>> */
    private array $actions = [];

    /**
     * LIFO stack of hook tags currently being dispatched (filter or action).
     * Powers {@see self::currentFilter()} / {@see self::doingAction()} so a
     * listener can detect and refuse re-entrant dispatch (loop break).
     *
     * @var list<string>
     */
    private array $currentStack = [];

    /**
     * Completed-dispatch counters per action tag, for {@see self::didAction()}.
     *
     * @var array<string, int>
     */
    private array $didActions = [];

    /** Optional profiler sink for fired-hook introspection. Null = no recording. */
    private ?ProfileCollectorInterface $profile = null;

    /**
     * @param int $slowThresholdMs Slow-hook warning threshold in ms. 0 = disabled.
     */
    public function __construct(
        private ?LoggerInterface $logger = null,
        private int $slowThresholdMs = 100,
    ) {
        $this->slowThresholdMs = max(0, $slowThresholdMs);
    }

    /**
     * Attach a profile collector to record each fired hook.
     *
     * Optional and off by default — when unset, hook dispatch is unchanged.
     */
    public function setProfileCollector(ProfileCollectorInterface $profile): void
    {
        $this->profile = $profile;
    }

    /**
     * Configure the slow-hook warning threshold. Set to 0 to disable monitoring.
     *
     * @param int $ms threshold in milliseconds
     */
    public function setSlowThreshold(int $ms): void
    {
        $this->slowThresholdMs = max(0, $ms);
    }

    /**
     * Inject the PSR-3 logger used for slow-hook warnings.
     *
     * Called after container compilation so the boundary-compliant logger
     * adapter is used instead of a kernel-layer debugging() call.
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * Register a filter callback under $tag, ordered by $priority (lower first).
     *
     * {@inheritDoc}
     *
     * @see self::applyFilters() Runs the callbacks registered here.
     */
    public function addFilter(string $tag, callable $function, int $priority = 10, int $args = 1): void
    {
        $this->filters[$tag][$priority][] = [
            'function' => $function,
            'accepted_args' => $args,
        ];
    }

    /**
     * Detach a previously-registered filter callback at the given priority.
     *
     * {@inheritDoc}
     *
     * @see self::removeAction() Action-side twin.
     */
    public function removeFilter(string $tag, callable $function, int $priority = 10): bool
    {
        if (!isset($this->filters[$tag][$priority])) {
            return false;
        }

        [$removed, $kept] = $this->rejectCallback($this->filters[$tag][$priority], $function);

        if (!$removed) {
            return false;
        }

        if ($kept === []) {
            unset($this->filters[$tag][$priority]);

            if ($this->filters[$tag] === []) {
                unset($this->filters[$tag]);
            }
        } else {
            $this->filters[$tag][$priority] = $kept;
        }

        return true;
    }

    /**
     * Run every filter registered for $tag in priority order, threading $value
     * through each callback's return; warns on slow callbacks.
     *
     * {@inheritDoc}
     *
     * @see self::addFilter() Registers the callbacks this method runs.
     */
    public function applyFilters(string $tag, mixed $value, mixed ...$args): mixed
    {
        if (!isset($this->filters[$tag])) {
            return $value;
        }

        ksort($this->filters[$tag]);

        $tagStart = hrtime(true);
        $this->currentStack[] = $tag;

        try {
            foreach ($this->filters[$tag] as $callbacks) {
                foreach ($callbacks as $callback) {
                    // First argument is always the value being filtered.
                    $callArgs = array_merge([$value], array_slice($args, 0, $callback['accepted_args'] - 1));

                    $start = hrtime(true);
                    $value = call_user_func_array($callback['function'], $callArgs);
                    $this->warnIfSlow('filter', $tag, (hrtime(true) - $start) / 1_000_000);
                }
            }
        } finally {
            array_pop($this->currentStack);
        }

        $this->profile?->record('hook', $tag, ['kind' => 'filter'], (hrtime(true) - $tagStart) / 1_000_000);

        return $value;
    }

    /**
     * Register a side-effect action callback under $tag, ordered by $priority.
     *
     * {@inheritDoc}
     *
     * @see self::doAction() Runs the callbacks registered here.
     */
    public function addAction(string $tag, callable $function, int $priority = 10, int $args = 1): void
    {
        $this->actions[$tag][$priority][] = [
            'function' => $function,
            'accepted_args' => $args,
        ];
    }

    /**
     * Detach a previously-registered action callback at the given priority.
     *
     * {@inheritDoc}
     *
     * @see self::removeFilter() Filter-side twin.
     */
    public function removeAction(string $tag, callable $function, int $priority = 10): bool
    {
        if (!isset($this->actions[$tag][$priority])) {
            return false;
        }

        [$removed, $kept] = $this->rejectCallback($this->actions[$tag][$priority], $function);

        if (!$removed) {
            return false;
        }

        if ($kept === []) {
            unset($this->actions[$tag][$priority]);

            if ($this->actions[$tag] === []) {
                unset($this->actions[$tag]);
            }
        } else {
            $this->actions[$tag][$priority] = $kept;
        }

        return true;
    }

    /**
     * Run every action registered for $tag in priority order (no return value);
     * warns on slow callbacks.
     *
     * {@inheritDoc}
     *
     * @see self::addAction() Registers the callbacks this method runs.
     */
    public function doAction(string $tag, mixed ...$args): void
    {
        $this->didActions[$tag] = ($this->didActions[$tag] ?? 0) + 1;

        if (!isset($this->actions[$tag])) {
            return;
        }

        ksort($this->actions[$tag]);

        $tagStart = hrtime(true);
        $this->currentStack[] = $tag;

        try {
            foreach ($this->actions[$tag] as $callbacks) {
                foreach ($callbacks as $callback) {
                    $cbArgs = array_slice($args, 0, $callback['accepted_args']);

                    $start = hrtime(true);
                    call_user_func_array($callback['function'], $cbArgs);
                    $this->warnIfSlow('action', $tag, (hrtime(true) - $start) / 1_000_000);
                }
            }
        } finally {
            array_pop($this->currentStack);
        }

        $this->profile?->record('hook', $tag, ['kind' => 'action'], (hrtime(true) - $tagStart) / 1_000_000);
    }

    /**
     * Whether at least one filter callback is registered for $tag.
     *
     * {@inheritDoc}
     */
    public function hasFilter(string $tag): bool
    {
        return isset($this->filters[$tag]) && $this->filters[$tag] !== [];
    }

    /**
     * Whether at least one action callback is registered for $tag.
     *
     * {@inheritDoc}
     */
    public function hasAction(string $tag): bool
    {
        return isset($this->actions[$tag]) && $this->actions[$tag] !== [];
    }

    /**
     * The hook tag currently being dispatched (top of stack), or null if none.
     *
     * {@inheritDoc}
     */
    public function currentFilter(): ?string
    {
        return $this->currentStack === [] ? null : $this->currentStack[array_key_last($this->currentStack)];
    }

    /**
     * Whether a dispatch is in progress — for $tag specifically, or any when null.
     *
     * {@inheritDoc}
     */
    public function doingAction(?string $tag = null): bool
    {
        if ($tag === null) {
            return $this->currentStack !== [];
        }

        return in_array($tag, $this->currentStack, true);
    }

    /**
     * How many times {@see self::doAction()} has been invoked for $tag.
     *
     * {@inheritDoc}
     */
    public function didAction(string $tag): int
    {
        return $this->didActions[$tag] ?? 0;
    }

    /**
     * Drop all registered filters and actions on this instance.
     *
     * {@inheritDoc}
     */
    public function reset(): void
    {
        $this->filters = [];
        $this->actions = [];
        $this->currentStack = [];
        $this->didActions = [];
    }

    /**
     * Split a priority bucket into [removed?, kept-callbacks], dropping every
     * entry whose callback is identical (`===`) to $function — the WordPress
     * contract (pass back the same closure instance / `[object|class, method]`
     * reference you registered). Pure: the input list is not mutated.
     *
     * @param list<array{function: callable, accepted_args: int}> $callbacks
     *
     * @return array{0: bool, 1: list<array{function: callable, accepted_args: int}>}
     */
    private function rejectCallback(array $callbacks, callable $function): array
    {
        $kept = [];
        $removed = false;

        foreach ($callbacks as $callback) {
            if ($callback['function'] === $function) {
                $removed = true;

                continue;
            }

            $kept[] = $callback;
        }

        return [$removed, $kept];
    }

    /**
     * Emit a PSR-3 warning when a single callback exceeds the slow threshold.
     *
     * @param string $kind      'filter' or 'action', for the log message
     * @param string $tag       the hook tag whose callback was slow
     * @param float  $elapsedMs measured callback duration in milliseconds
     *
     * @see self::setSlowThreshold() Configures the threshold checked here.
     */
    private function warnIfSlow(string $kind, string $tag, float $elapsedMs): void
    {
        if ($this->slowThresholdMs <= 0 || $elapsedMs <= $this->slowThresholdMs) {
            return;
        }

        $this->logger()->warning(
            '[hook_manager] Slow {kind} on "{tag}": {elapsed_ms}ms (threshold: {threshold_ms}ms)',
            [
                'kind' => $kind,
                'tag' => $tag,
                'elapsed_ms' => round($elapsedMs, 2),
                'threshold_ms' => $this->slowThresholdMs,
            ],
        );
    }

    /**
     * Resolve the slow-hook logger, lazily falling back to a {@see NullLogger}
     * when {@see self::setLogger()} was never called.
     */
    private function logger(): LoggerInterface
    {
        return $this->logger ??= new NullLogger();
    }
}
