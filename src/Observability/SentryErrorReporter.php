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
use Sentry\State\Scope;
use Throwable;

use function Sentry\captureException;
use function Sentry\withScope;

/**
 * Sentry-backed {@see ErrorReporterInterface} (requires `sentry/sentry`, see
 * the composer `suggest` — the SDK is NOT a framework dependency; this class
 * only loads when the consumer installs it and binds this reporter).
 *
 * Thin by design: the host initialises the SDK (`\Sentry\init()` with DSN,
 * environment, release) — this class only forwards the throwable and attaches
 * the structured context to the event scope. When the SDK was never
 * initialised, Sentry's capture functions are a no-op, so binding this
 * reporter without a DSN is harmless (mirrors the never-throw contract).
 *
 * @api
 */
final readonly class SentryErrorReporter implements ErrorReporterInterface
{
    public function report(Throwable $throwable, array $context = []): void
    {
        try {
            withScope(static function (Scope $scope) use ($throwable, $context): void {
                foreach ($context as $key => $value) {
                    $scope->setContext($key, \is_array($value) ? $value : ['value' => $value]);
                }
                captureException($throwable);
            });
        } catch (Throwable) {
            // Never let telemetry take the request down.
        }
    }
}
