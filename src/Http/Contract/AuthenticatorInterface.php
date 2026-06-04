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
 * The standalone authentication primitive.
 *
 * Owns the *session state* of who is logged in — not credential verification.
 * The application verifies credentials against its own user store, then calls
 * {@see self::login()} with the resolved user id; this contract persists and
 * exposes that identity for the rest of the request (and the HttpKernel's
 * `#[Auth]` gate). Bind an implementation to this id in the container to make
 * `#[Auth(login: true)]` enforce; leave it unbound and the gate stays inert
 * (host-delegated auth, e.g. Moodle/WordPress, is unaffected).
 *
 * @api
 */
interface AuthenticatorInterface
{
    /**
     * Establish an authenticated session for the given user id.
     *
     * @param array<string, mixed> $attributes optional profile data to keep alongside the id (name, email, capabilities, …)
     */
    public function login(int $userId, array $attributes = []): void;

    /**
     * Clear the authenticated session.
     */
    public function logout(): void;

    /**
     * Whether a user is currently authenticated.
     */
    public function check(): bool;

    /**
     * The authenticated user id, or null when no session is established.
     */
    public function id(): ?int;

    /**
     * The stored session record (`id` + `attributes`), or null when unauthenticated.
     *
     * @return null|array<string, mixed>
     */
    public function user(): ?array;

    /**
     * Path the kernel redirects unauthenticated browser visits to.
     */
    public function loginPath(): string;
}
