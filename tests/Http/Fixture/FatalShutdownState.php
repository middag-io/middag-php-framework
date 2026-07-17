<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Http\Fixture;

use Middag\Framework\Http\FatalErrorHandler;

/**
 * Mutable state consulted by the `Middag\Framework\Http` global-function
 * stubs in `fatal_error_handler_functions.php`. Lets a test drive
 * {@see FatalErrorHandler::handleShutdown()} through
 * its full body in-process — including the `headers_sent()` guard and the
 * response emission — without a real fatal error or a subprocess.
 *
 * @internal
 */
final class FatalShutdownState
{
    /** @var null|array{type: int, message: string, file: string, line: int} */
    public static ?array $errorGetLast = null;

    public static bool $headersSent = false;

    /** @var list<int> */
    public static array $responseCodes = [];

    /** @var list<string> */
    public static array $headers = [];

    /**
     * While false, every stub transparently delegates to the real global
     * function — so this fixture only affects tests that opt in.
     */
    public static bool $active = false;

    public static function reset(): void
    {
        self::$errorGetLast = null;
        self::$headersSent = false;
        self::$responseCodes = [];
        self::$headers = [];
        self::$active = false;
    }
}
