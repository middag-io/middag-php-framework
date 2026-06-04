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

use Middag\Framework\Bus\Contract\UserContextResolverInterface;
use Middag\Framework\Bus\NullUserContextResolver;
use Middag\Framework\Http\Contract\AuthenticatorInterface;

/**
 * Bridges the standalone {@see AuthenticatorInterface} to the bus-side
 * {@see UserContextResolverInterface}.
 *
 * The command bus and domain layer ask "who is the current user?" through
 * {@see UserContextResolverInterface}; standalone apps answer it from the
 * authenticated session instead of falling back to the always-null
 * {@see NullUserContextResolver}. Bind this in place of
 * the null resolver once an authenticator is wired.
 *
 * @api
 */
final readonly class SessionUserContextResolver implements UserContextResolverInterface
{
    public function __construct(
        private AuthenticatorInterface $authenticator,
    ) {}

    public function getCurrentUserId(): ?int
    {
        return $this->authenticator->id();
    }
}
