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
 * Loader-level failure policy for class-loading artifact discovery.
 *
 * Used by host service loaders to short-circuit module classes
 * that already failed and to apply distribution-tier isolation logic.
 *
 * A distribution-tier implementation is bound by the host/consumer in DI.
 *
 * @api
 */
interface LoaderFailurePolicyInterface
{
    /**
     * Returns true when the class belongs to a module already marked failed.
     */
    public function shouldSkipClass(string $class): bool;

    /**
     * Apply failure policy for a loader failure.
     *
     * @return bool True when the failure was isolated (caller should continue).
     *              False/throw to propagate.
     *
     * @throws Throwable when the policy decides to rethrow
     */
    public function isolateOrThrow(string $artifact, string $class, Throwable $throwable): bool;
}
