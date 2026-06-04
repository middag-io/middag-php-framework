<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Http\Session;

use Middag\Framework\Http\Contract\SessionInterface;

/**
 * Native PHP session store over {@see $_SESSION}.
 *
 * The standalone default. {@see self::start()} opens the session lazily and is
 * safe to call repeatedly (and on the CLI SAPI, where it is a no-op so workers
 * and tests never trip a "headers already sent" warning). Wire it behind
 * {@see SessionInterface} and start it once via {@see StartSessionMiddleware}.
 *
 * @api
 */
final class NativeSession implements SessionInterface
{
    public function start(): void
    {
        if (\PHP_SAPI === 'cli') {
            return;
        }

        if (session_status() === \PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public function clear(): void
    {
        $_SESSION = [];
    }

    public function regenerate(bool $deleteOld = true): void
    {
        if (\PHP_SAPI === 'cli') {
            return;
        }

        if (session_status() === \PHP_SESSION_ACTIVE) {
            session_regenerate_id($deleteOld);
        }
    }
}
