<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Http\Auth;

use Middag\Framework\Http\Contract\AuthenticatorInterface;
use Middag\Framework\Http\Contract\SessionInterface;

/**
 * Session-backed authenticator — the batteries-included {@see AuthenticatorInterface}.
 *
 * Persists the authenticated identity under a single reserved session key. Holds
 * no credential logic: the application verifies the password (or token, OIDC,
 * …) against its own user store, then calls {@see self::login()} with the
 * resolved id. Swap the {@see SessionInterface} backend (native vs in-memory)
 * without touching this class.
 *
 * @api
 */
final readonly class SessionAuthenticator implements AuthenticatorInterface
{
    /**
     * Reserved session key holding the `['id' => int, 'attributes' => array]` record.
     */
    private const SESSION_KEY = '_middag_auth';

    public function __construct(
        private SessionInterface $session,
        private string $loginPath = '/login',
    ) {}

    public function login(int $userId, array $attributes = []): void
    {
        // Rotate the session id before persisting the identity to defeat
        // session fixation: any id fixed by an attacker pre-login must not
        // carry over into the authenticated session.
        $this->session->regenerate();

        $this->session->set(self::SESSION_KEY, [
            'id' => $userId,
            'attributes' => $attributes,
        ]);
    }

    public function logout(): void
    {
        $this->session->remove(self::SESSION_KEY);
    }

    public function check(): bool
    {
        return $this->id() !== null;
    }

    public function id(): ?int
    {
        $record = $this->session->get(self::SESSION_KEY);

        if (is_array($record) && isset($record['id'])) {
            return (int) $record['id'];
        }

        return null;
    }

    public function user(): ?array
    {
        $record = $this->session->get(self::SESSION_KEY);

        return is_array($record) ? $record : null;
    }

    public function loginPath(): string
    {
        return $this->loginPath;
    }
}
