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
 * Role: the auth gate the kernel derives from a route's #[Auth] — require a
 * logged-in user and/or a set of capabilities. The adapter interprets the
 * capability model ($context / $instanceId) for its host.
 *
 * Segregated from {@see ControllerInterface} so an adapter can adopt the auth
 * policy independently of the request lifecycle
 * ({@see RequestHandlingInterface}). Note the interface fixes only the
 * signature, not the enforcement: a page controller may redirect / halt on
 * failure while a REST controller reports the denial in its own protocol.
 *
 * @api
 */
interface AuthorizationAwareInterface
{
    public function setRequireLogin(): void;

    /**
     * @param array<int, string> $capabilities
     * @param string             $context      Platform-specific context type the adapter interprets
     */
    public function setRequireCapabilities(array $capabilities, string $context = 'system', int $instanceId = 0): void;
}
