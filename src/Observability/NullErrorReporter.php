<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Observability;

use Middag\Framework\Observability\Contract\ErrorReporterInterface;
use Throwable;

/**
 * Default OSS error reporter: discards the event.
 *
 * Standalone (no tracker configured) has nowhere to ship errors, so the safe
 * default is a no-op named to make that obvious. Bind a platform implementation
 * (Sentry, Bugsnag, host-native) in production to actually report.
 *
 * @api
 */
final readonly class NullErrorReporter implements ErrorReporterInterface
{
    public function report(Throwable $throwable, array $context = []): void
    {
        // Intentional no-op: no tracker bound. Swap for a real reporter.
    }
}
