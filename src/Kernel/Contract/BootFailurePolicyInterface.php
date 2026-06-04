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

use Throwable;

/**
 * Decides how to handle a module boot failure.
 *
 * The default implementation (BootRethrowFailurePolicy) re-throws unconditionally.
 * A distribution-tier policy can be bound by the consumer in its place.
 *
 * @api
 */
interface BootFailurePolicyInterface
{
    /**
     * Handle a boot failure for the given module.
     *
     * Implementations may rethrow, log-and-continue, or apply tier-based logic.
     *
     * @throws Throwable when the policy decides to propagate the failure
     */
    public function handle(ModuleInterface $module, Throwable $e): void;
}
