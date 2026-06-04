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

use Psr\Container\ContainerInterface;

/**
 * Lifecycle contract for the application kernel singleton.
 *
 * Defines the four static entry points an adapter kernel exposes: {@see init()}
 * (boot services + load definitions), {@see handle()} (run the current request),
 * {@see container()} (the shared PSR-11 container for consumers that cannot rely
 * on constructor injection), and {@see dispatch()} (push an event through the
 * framework dispatcher).
 *
 * The framework ships no OSS implementation of this interface; adapters (Moodle,
 * WordPress) and the standalone runtime provide the concrete kernel. The spec is
 * intentional, not aspirational — it pins the lifecycle every host must satisfy.
 *
 * @internal
 */
interface KernelInterface
{
    /**
     * Initialize kernel services and load definitions.
     */
    public static function init(): void;

    /**
     * Execute the current request using the configured HTTP kernel/dispatcher.
     */
    public static function handle(): void;

    /**
     * Get the shared PSR-11 container instance.
     */
    public static function container(): ContainerInterface;

    /**
     * Dispatch an event through the framework's event dispatcher.
     *
     * @return object the same event, after listeners have mutated it
     */
    public static function dispatch(object $event): object;
}
