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

use Middag\Framework\Kernel\Manager\HookManager;

/**
 * Generic synchronous host event bridge.
 *
 * The public, stable OSS seam for synchronous in-process broadcast of named
 * host events. Events are keyed by a dot-separated name (e.g.
 * 'organization.created') and carry a positional payload array passed straight
 * to listeners — no event object required. Execution is synchronous and
 * in-process; listeners return nothing.
 *
 * Adapters implement this contract by exposing the host's native eventing; the
 * reference implementation is `MoodleHostEventBridge` in `middag-io/moodle`,
 * which delegates to {@see HookManager}. Governed listeners (CORE and
 * products) register against this contract, so the adapter never has to import
 * CORE to wire them up.
 *
 * Distinct from the richer reaction layers:
 * - `Bus\Contract\SignalDispatcherInterface` — the object-as-signal publish
 *   side (the premium 3-tier signal layer — SignalDispatcher/hooks/outbox —
 *   lives in `middag-io/core`); takes any object and returns it, no string
 *   keys.
 * - {@see HookManager} — filter/action hooks that transform a value and can be
 *   chained by priority, returning the filtered value; this bridge only
 *   broadcasts named events to void listeners.
 *
 * @api
 */
interface HostEventBridgeInterface
{
    /**
     * Dispatch a domain event.
     *
     * @param string  $eventName Dot-separated event name, e.g. 'organization.created'
     * @param mixed[] $payload   Positional arguments passed to listeners
     */
    public function dispatch(string $eventName, array $payload = []): void;

    /**
     * Register a listener for a domain event.
     *
     * @param string   $eventName Dot-separated event name
     * @param callable $listener  Callback to invoke when event is dispatched
     * @param int      $priority  Listener priority (lower = earlier)
     */
    public function listen(string $eventName, callable $listener, int $priority = 10): void;
}
