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

/**
 * Contract for hookfile loaders.
 *
 * Hookfiles are .php scripts that register signal/hook subscriptions at boot
 * via top-level statements (one of the three integration modes documented in
 * the developer guide). Concrete implementations live in platform adapters
 * (Moodle, WordPress) because path discovery is platform-specific.
 *
 * @api
 */
interface HookfileLoaderInterface
{
    /**
     * Discover candidate hookfile paths to be loaded.
     *
     * @return string[] absolute file paths
     */
    public function discover(): array;

    /**
     * Load a single hookfile.
     *
     * Implementations should isolate failures: if the file throws during load,
     * the loader is expected to suspend it (log + skip) rather than propagate
     * the exception, so that one broken hookfile does not break the boot cycle.
     *
     * @return bool true if loaded successfully, false if suspended due to a failure
     */
    public function load(string $path): bool;
}
