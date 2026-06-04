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
 * Base contract for kernel loaders that register definitions into the container.
 *
 * Implementations discover classes and push their definitions into the DI
 * container during the bootstrap phase. The target container is supplied to the
 * implementation at construction time, not passed to load().
 *
 * @internal
 */
interface LoaderInterface
{
    /**
     * Execute the loading process: scan directories, find files, and register
     * the discovered definitions into the implementation's container.
     */
    public function load(): void;
}
