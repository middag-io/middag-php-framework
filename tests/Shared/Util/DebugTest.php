<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Shared\Util;

use LogicException;
use Middag\Framework\Shared\Enum\DebugMode;
use Middag\Framework\Shared\Util\Debug;
use Middag\Framework\Tests\Shared\Util\Fixture\ReentrantDebug;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use RuntimeException;
use Stringable;

/**
 * @internal
 */
#[CoversClass(Debug::class)]
final class DebugTest extends TestCase
{
    protected function setUp(): void
    {
        Debug::resetRuntime();
    }

    protected function tearDown(): void
    {
        Debug::resetRuntime();
    }

    #[Test]
    public function traceEmitsWhenModeMeetsLevel(): void
    {
        $logger = $this->spyLogger();
        Debug::setRuntime($logger, static fn (): int => DebugMode::FULL->value);

        Debug::trace('hello', DebugMode::NORMAL);

        self::assertContains('hello', $logger->messages);
    }

    #[Test]
    public function traceIsSilentWhenModeBelowLevel(): void
    {
        $logger = $this->spyLogger();
        Debug::setRuntime($logger, static fn (): int => DebugMode::DISABLED->value);

        Debug::trace('hidden', DebugMode::NORMAL);

        self::assertSame([], $logger->messages);
    }

    #[Test]
    public function traceExceptionEmitsFormattedLines(): void
    {
        $logger = $this->spyLogger();
        Debug::setRuntime($logger, static fn (): int => DebugMode::NORMAL->value);

        Debug::traceException(new RuntimeException('boom', 7), DebugMode::NORMAL);

        self::assertContains('@@@@@@ EXCEPTION @@@@@@', $logger->messages);
        self::assertContains('Code: 7', $logger->messages);
        self::assertContains('Message: boom', $logger->messages);
        self::assertContains('Trace: ', $logger->messages);
    }

    #[Test]
    public function traceExceptionAppendsPreviousMessage(): void
    {
        $logger = $this->spyLogger();
        Debug::setRuntime($logger, static fn (): int => DebugMode::NORMAL->value);

        Debug::traceException(
            new RuntimeException('outer', 0, new LogicException('inner')),
            DebugMode::NORMAL,
        );

        self::assertContains('Previous: inner', $logger->messages);
    }

    #[Test]
    public function traceExceptionIsSilentWhenModeBelowLevel(): void
    {
        $logger = $this->spyLogger();
        Debug::setRuntime($logger, static fn (): int => DebugMode::DISABLED->value);

        Debug::traceException(new RuntimeException('boom'), DebugMode::NORMAL);

        self::assertSame([], $logger->messages);
    }

    #[Test]
    public function traceExceptionDefaultsToDisabledWhenConfigReadThrows(): void
    {
        $logger = $this->spyLogger();
        Debug::setRuntime($logger, static function (): int {
            throw new RuntimeException('config unavailable');
        });

        Debug::traceException(new RuntimeException('boom'), DebugMode::NORMAL);

        self::assertSame([], $logger->messages);
    }

    #[Test]
    public function traceExceptionGuardsAgainstReentrancy(): void
    {
        ReentrantDebug::$formatCalls = 0;
        Debug::setRuntime($this->spyLogger(), static fn (): int => DebugMode::FULL->value);

        ReentrantDebug::traceException(new RuntimeException('boom'), DebugMode::NORMAL);

        self::assertSame(1, ReentrantDebug::$formatCalls);
    }

    #[Test]
    public function resetRuntimeClearsWiringSoTraceBecomesDisabled(): void
    {
        $logger = $this->spyLogger();
        Debug::setRuntime($logger, static fn (): int => DebugMode::FULL->value);

        Debug::resetRuntime();
        Debug::trace('after-reset', DebugMode::NORMAL);

        self::assertSame([], $logger->messages);
    }

    #[Test]
    public function emitFallsBackToNullLoggerWhenNoLoggerWired(): void
    {
        $this->expectNotToPerformAssertions();

        // No runtime wired: readDebugMode() returns DISABLED (0), which still
        // satisfies a DISABLED-level trace, so emit() runs against the
        // NullLogger fallback without throwing.
        Debug::trace('to-null-logger', DebugMode::DISABLED);
    }

    /**
     * @return AbstractLogger&object{messages: list<string>}
     */
    private function spyLogger(): AbstractLogger
    {
        return new class extends AbstractLogger {
            /** @var list<string> */
            public array $messages = [];

            public function log($level, string|Stringable $message, array $context = []): void
            {
                $this->messages[] = (string) $message;
            }
        };
    }
}
