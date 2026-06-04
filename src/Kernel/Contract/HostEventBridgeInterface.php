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
 * The platform-agnostic seam an adapter MAY implement to bridge the host's
 * native eventing. Events are keyed by a dot-separated name (e.g.
 * 'organization.created') and carry a positional payload array passed straight
 * to listeners — no event object required. Execution is synchronous and
 * in-process.
 *
 * Distinct from the richer reaction layers:
 * - `Bus\Contract\SignalDispatcherInterface` — object-as-signal publish side
 *   (the 3-tier signal layer: SignalDispatcher/hooks/outbox lives in
 *   `middag-io/core`); takes any object and returns it, no string keys.
 * - {@see HookManager} — filter/action hooks
 *   that transform a value and can be chained by priority; this bridge only
 *   broadcasts named events to void listeners.
 *
 * No implementation ships yet, and the reaction path used in practice is the
 * signal layer above; this stays an experimental seam until a real adapter
 * validates its shape. Treat the signature as unstable.
 *
 * @internal
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
