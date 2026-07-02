<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Observability\Contract;

use Middag\Framework\Observability\NullErrorReporter;
use Middag\Framework\Observability\SentryErrorReporter;
use Throwable;

/**
 * Infrastructure port for shipping errors to an external tracker, so kernel
 * and domain code report through a thin contract instead of a vendor SDK.
 *
 * Platform-agnostic by design: the SDKs are never framework dependencies.
 * Bind an implementation for your tracker of choice — Sentry, Bugsnag,
 * Rollbar, a host-native logger — in the consumer/adapter.
 *
 * Implementations MUST never throw: error reporting is best-effort telemetry
 * and can never take the request down with it.
 *
 * Default OSS impl: {@see NullErrorReporter} (discards). With `sentry/sentry`
 * installed (see the composer `suggest`), {@see SentryErrorReporter} is ready
 * to bind.
 *
 * @api
 */
interface ErrorReporterInterface
{
    /**
     * Report a throwable with optional structured context.
     *
     * @param array<string, mixed> $context extra key/value context attached to the event
     */
    public function report(Throwable $throwable, array $context = []): void;
}
