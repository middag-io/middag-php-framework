<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Shared\Util;

use Closure;
use Middag\Framework\Shared\Enum\DebugMode;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

/**
 * Tracing helper gated by a configurable debug level.
 *
 * Emits messages and exception traces through a PSR-3 logger when the
 * configured debug mode meets or exceeds the call-site requirement.
 * Static API by design — call sites do not need to wire any dependency.
 *
 * Boot wiring (one-shot at adapter bootstrap):
 *
 *   Debug::setRuntime(
 *       logger: $container->get(LoggerInterface::class),
 *       configReader: static fn(): int => (int) $config->get('debugmode'),
 *   );
 *
 * Adapters can subclass to swap the output channel (e.g. a host's
 * native trace/log routine) by overriding `emit()` and the
 * exception detail extraction by overriding `formatExceptionLines()`.
 *
 * @api
 */
class Debug
{
    private static ?LoggerInterface $logger = null;

    /** @var null|Closure(): int */
    private static ?Closure $configReader = null;

    /**
     * Reentrancy guard — avoids infinite recursion when tracing an
     * exception triggers another error that would also be traced.
     */
    private static bool $intrace = false;

    /**
     * Wire the static runtime once at adapter bootstrap.
     *
     * @param LoggerInterface $logger       PSR-3 sink
     * @param Closure(): int  $configReader returns the current debug mode integer value
     */
    public static function setRuntime(LoggerInterface $logger, Closure $configReader): void
    {
        self::$logger = $logger;
        self::$configReader = $configReader;
    }

    /**
     * Reset runtime wiring — primarily for tests.
     */
    public static function resetRuntime(): void
    {
        self::$logger = null;
        self::$configReader = null;
        self::$intrace = false;
    }

    /**
     * Emit a trace message when the current debug mode is at least `$level`.
     */
    public static function trace(string $message = '', DebugMode $level = DebugMode::NORMAL): void
    {
        if (!$level->isEnabledBy(self::readDebugMode())) {
            return;
        }

        static::emit($message);
    }

    /**
     * Emit a detailed exception trace when the current debug mode is at least `$level`.
     *
     * Subclasses override `formatExceptionLines()` to add platform-specific
     * exception properties (e.g. host-specific debug/SQL fields).
     */
    public static function traceException(Throwable $exception, DebugMode $level = DebugMode::NORMAL): void
    {
        if (self::$intrace) {
            return;
        }

        self::$intrace = true;

        try {
            $enabledValue = DebugMode::DISABLED->value;

            try {
                $enabledValue = self::readDebugMode();
            } catch (Throwable) {
                // Config read may fail during early boot; default to disabled.
            }

            if (!$level->isEnabledBy($enabledValue)) {
                return;
            }

            foreach (static::formatExceptionLines($exception) as $line) {
                static::emit($line);
            }
        } finally {
            self::$intrace = false;
        }
    }

    /**
     * Output channel — PSR-3 logger by default. Subclasses override to redirect
     * (e.g. a host's native trace routine for cron/task visibility).
     */
    protected static function emit(string $message): void
    {
        self::ensureLogger()->debug($message);
    }

    /**
     * Lines produced for an exception trace. Platform-agnostic baseline:
     * code, message, and a string-formatted backtrace. Subclasses extend
     * to surface host-flavored fields.
     *
     * @return list<string>
     */
    protected static function formatExceptionLines(Throwable $exception): array
    {
        $lines = [
            '@@@@@@ EXCEPTION @@@@@@',
            'Code: ' . $exception->getCode(),
            'Message: ' . $exception->getMessage(),
            'Trace: ',
            $exception->getTraceAsString(),
        ];

        $previous = $exception->getPrevious();
        if ($previous instanceof Throwable) {
            $lines[] = 'Previous: ' . $previous->getMessage();
        }

        return $lines;
    }

    private static function readDebugMode(): int
    {
        if (!self::$configReader instanceof Closure) {
            return DebugMode::DISABLED->value;
        }

        return (self::$configReader)();
    }

    private static function ensureLogger(): LoggerInterface
    {
        return self::$logger ?? new NullLogger();
    }
}
