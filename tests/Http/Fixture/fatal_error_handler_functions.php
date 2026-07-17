<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Http;

use Middag\Framework\Tests\Http\Fixture\FatalShutdownState as State;

/**
 * Namespace-scoped overrides of the global functions
 * {@see FatalErrorHandler::handleShutdown()} calls unqualified.
 *
 * PHP resolves an unqualified function call made from inside a namespace
 * against that namespace first, falling back to the global function only
 * when no such function is declared there. Declaring these here — in
 * `Middag\Framework\Http`, the same namespace as {@see FatalErrorHandler} —
 * lets a test drive `handleShutdown()`'s full body (the `headers_sent()`
 * guard, `http_response_code()`, `header()`) deterministically and
 * in-process, which a real fatal error cannot do without ending the process.
 *
 * Each stub is a transparent pass-through to the real global function unless
 * {@see State::$active} is true, so every caller other than the opted-in
 * test keeps ordinary behaviour.
 */
function error_get_last(): ?array
{
    return State::$active ? State::$errorGetLast : \error_get_last();
}

function headers_sent(): bool
{
    return State::$active ? State::$headersSent : \headers_sent();
}

function http_response_code(int $response_code = 0): bool|int
{
    if (State::$active) {
        State::$responseCodes[] = $response_code;

        return true;
    }

    return \http_response_code($response_code);
}

function header(string $header, bool $replace = true, int $response_code = 0): void
{
    if (State::$active) {
        State::$headers[] = $header;

        return;
    }

    \header($header, $replace, $response_code);
}
