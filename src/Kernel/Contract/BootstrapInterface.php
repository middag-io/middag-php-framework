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

use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Contract for platform-specific container bootstrap.
 *
 * Each host platform (Moodle, WordPress, standalone) implements this interface
 * to wire its services, parameters, and platform bindings into the framework
 * container.
 *
 * @api
 */
interface BootstrapInterface
{
    /**
     * Configure the container with platform-specific services and parameters.
     *
     * Called once during kernel initialization. Implementations register:
     * - Platform service bindings (database, cache, auth, etc.)
     * - Configuration parameters ($CFG equivalents)
     * - Platform-specific compiler passes
     */
    public function configure(ContainerBuilder $builder): void;

    /**
     * The human-readable platform identifier (e.g. 'moodle', 'wordpress', 'standalone').
     */
    public function platform(): string;

    /**
     * Absolute path to the project root (used for service discovery and paths).
     *
     * Adapters that do not use service discovery may return an empty string.
     */
    public function getProjectRoot(): string;

    /**
     * Additional bootstrap options (e.g. cache path, debug flag).
     *
     * @return array<string, mixed>
     */
    public function getOptions(): array;
}
