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
 * Minimal contract for framework modules.
 *
 * Platform-specific module interfaces (the Moodle/WordPress adapters) and
 * MIDDAG-flavored module interfaces (in core) extend this. Per D2, adapters
 * know only this OSS contract — the richer extension surface lives downstream.
 *
 * @api
 */
interface ModuleInterface
{
    /** Stable machine identifier (slug) used for dependency wiring and storage. */
    public function getName(): string;

    /** Human-readable display name for UIs and logs. */
    public function getLabel(): string;

    public function getVersion(): string;

    /**
     * Names of modules that must boot before this one.
     *
     * @return array<string>
     */
    public function getDependencies(): array;

    /** Register services into the container. Runs for every module before any boot(). */
    public function register(ContainerInterface $container): void;

    /** Wire runtime behaviour (hooks, routes). Runs after every module has registered. */
    public function boot(): void;

    /** Whether the module is switched on (configuration / feature flag). */
    public function isEnabled(): bool;

    /** Whether the module's runtime prerequisites are met (e.g. dependencies present). */
    public function isAvailable(): bool;
}
