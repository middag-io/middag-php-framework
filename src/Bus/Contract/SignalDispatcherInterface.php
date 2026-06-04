<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Bus\Contract;

/**
 * Signal dispatcher contract.
 *
 * The canonical publish-side entry point for dispatching signals
 * (domain events, integration events, reactive notifications).
 *
 * Any PHP object is a signal — no base class required.
 * Concrete implementations delegate to the host's native dispatch
 * (Symfony EventDispatcher, Moodle events, WordPress hooks) or to the
 * governed core Signal/outbox.
 *
 * No OSS implementation ships in this framework: the publish-side seam is
 * fulfilled by `middag-io/core` or a host adapter. Standalone consumers
 * should model side-effects via hooks + async commands (see the demo)
 * rather than relying on a bundled dispatcher.
 *
 * @api
 */
interface SignalDispatcherInterface
{
    /**
     * Dispatch a signal to all registered listeners.
     *
     * @template T of object
     *
     * @param T $signal any object — the signal to dispatch
     *
     * @return T the (potentially modified) signal after all listeners
     */
    public function dispatch(object $signal): object;
}
