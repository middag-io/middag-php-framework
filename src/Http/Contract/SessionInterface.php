<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Http\Contract;

/**
 * Minimal server-side session store.
 *
 * Abstracts the session backend so the auth primitive and middleware never
 * touch the {@see $_SESSION} superglobal directly: standalone hosts use
 * {@see NativeSession} (native PHP sessions), tests and CLI workers use
 * {@see ArraySession} (in-memory). Platform adapters (Moodle, WordPress) may
 * provide their own implementation over the host session.
 *
 * @api
 */
interface SessionInterface
{
    /**
     * Start the session if it is not already active. Idempotent.
     */
    public function start(): void;

    public function has(string $key): bool;

    public function get(string $key, mixed $default = null): mixed;

    public function set(string $key, mixed $value): void;

    public function remove(string $key): void;

    /**
     * Drop every value in the session.
     */
    public function clear(): void;

    /**
     * Rotate the underlying session id, preserving the current data.
     *
     * Called on a privilege change (notably login) to defeat session fixation:
     * an id an attacker planted before authentication must not survive it.
     * No-op for backends without an id transport (in-memory, CLI).
     *
     * @param bool $deleteOld whether to delete the old session file/record
     *
     * @since 0.6.0 required method; external implementers must add it
     */
    public function regenerate(bool $deleteOld = true): void;
}
