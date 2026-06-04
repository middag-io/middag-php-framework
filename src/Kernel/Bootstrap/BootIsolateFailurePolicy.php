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

use Middag\Framework\Http\FatalErrorHandler;
use Middag\Framework\Kernel\Contract\BootFailurePolicyInterface;
use Middag\Framework\Kernel\Contract\ModuleInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

/**
 * Isolation failure policy: log the boot failure at `critical` and continue,
 * so one broken module does not abort the whole platform boot (and does not
 * surface a fatal to the host CMS).
 *
 * Production-leaning counterpart to {@see BootRethrowFailurePolicy} (the
 * dev/test default that rethrows so failures are loud). A consumer binds this
 * in DI for resilient production boot; pair it with
 * {@see FatalErrorHandler} to also catch fatals raised
 * outside the module-boot try/catch.
 *
 * @api
 */
final readonly class BootIsolateFailurePolicy implements BootFailurePolicyInterface
{
    public function __construct(
        private LoggerInterface $logger = new NullLogger(),
    ) {}

    public function handle(ModuleInterface $module, Throwable $e): void
    {
        // Swallow on purpose: log and let the remaining modules boot.
        $this->logger->critical(
            '[boot] Module "{module}" failed to boot and was isolated: {message}',
            [
                'module' => $module->getName(),
                'message' => $e->getMessage(),
                'exception' => $e,
            ],
        );
    }
}
