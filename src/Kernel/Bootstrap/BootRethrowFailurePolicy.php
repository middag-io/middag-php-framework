<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Kernel\Bootstrap;

use Middag\Framework\Kernel\Contract\BootFailurePolicyInterface;
use Middag\Framework\Kernel\Contract\ModuleInterface;
use Throwable;

/**
 * Default failure policy: always rethrow.
 *
 * Apps that want isolation (log-and-continue) or distribution-tier handling
 * bind a custom policy in DI.
 *
 * @internal
 */
final class BootRethrowFailurePolicy implements BootFailurePolicyInterface
{
    public function handle(ModuleInterface $module, Throwable $e): void
    {
        throw $e;
    }
}
