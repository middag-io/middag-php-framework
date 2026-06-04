<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Http\Security;

use Middag\Framework\Http\Contract\SessionInterface;
use Middag\Framework\Http\Middleware\VerifyCsrfMiddleware;

/**
 * Session-backed CSRF token manager.
 *
 * Generates one per-session token (lazily, on first read) and validates
 * candidates against it in constant time. No new dependency: it leans on the
 * framework {@see SessionInterface} rather than pulling in symfony/security-csrf.
 * The companion {@see VerifyCsrfMiddleware}
 * shares the token for clients/forms and verifies it on unsafe methods.
 *
 * @api
 */
final readonly class CsrfTokenManager
{
    private const SESSION_KEY = '_middag_csrf';

    public function __construct(
        private SessionInterface $session,
    ) {}

    /**
     * The current CSRF token, generating and persisting one on first use.
     */
    public function token(): string
    {
        $token = $this->session->get(self::SESSION_KEY);

        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $this->session->set(self::SESSION_KEY, $token);
        }

        return $token;
    }

    /**
     * Whether the candidate matches the session token (constant-time compare).
     */
    public function isValid(?string $candidate): bool
    {
        if (!is_string($candidate) || $candidate === '') {
            return false;
        }

        $token = $this->session->get(self::SESSION_KEY);

        return is_string($token) && $token !== '' && hash_equals($token, $candidate);
    }
}
