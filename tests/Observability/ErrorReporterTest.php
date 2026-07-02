<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Observability;

use Middag\Framework\Observability\Contract\ErrorReporterInterface;
use Middag\Framework\Observability\NullErrorReporter;
use Middag\Framework\Observability\SentryErrorReporter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @internal
 */
#[CoversClass(NullErrorReporter::class)]
#[CoversClass(SentryErrorReporter::class)]
final class ErrorReporterTest extends TestCase
{
    public function testNullReporterDiscardsWithoutError(): void
    {
        $reporter = new NullErrorReporter();

        self::assertInstanceOf(ErrorReporterInterface::class, $reporter);
        $reporter->report(new RuntimeException('boom'), ['request' => ['path' => '/x']]);

        // Reaching this line proves the no-op default returned without throwing.
        self::assertTrue(true);
    }

    public function testSentryReporterIsANoOpWithoutInitialisedSdk(): void
    {
        $reporter = new SentryErrorReporter();

        self::assertInstanceOf(ErrorReporterInterface::class, $reporter);

        // The SDK was never init()ed in this process: Sentry's capture
        // functions no-op, and the reporter's never-throw contract holds.
        $reporter->report(new RuntimeException('boom'), ['scalar' => 42, 'nested' => ['a' => 1]]);

        self::assertTrue(true);
    }
}
