<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Shared\Util\Fixture;

use Middag\Framework\Shared\Util\Debug;
use RuntimeException;
use Throwable;

/**
 * Debug subclass used to exercise the re-entrancy guard: formatting an
 * exception re-enters {@see Debug::traceException()}, which must be a no-op
 * while the outer trace is still in flight.
 *
 * @internal
 */
final class ReentrantDebug extends Debug
{
    public static int $formatCalls = 0;

    /**
     * @return list<string>
     */
    protected static function formatExceptionLines(Throwable $exception): array
    {
        ++self::$formatCalls;

        // Re-entrant call: the guard in the parent must short-circuit this,
        // otherwise formatExceptionLines would be entered a second time.
        self::traceException(new RuntimeException('nested'));

        return [];
    }
}
