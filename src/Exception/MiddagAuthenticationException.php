<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Exception;

/**
 * Authentication failure — user is not identified.
 *
 * Thrown when a request reaches a protected endpoint without valid
 * credentials (missing/expired token, no active session, anonymous/guest principal).
 *
 * Distinct from MiddagAuthorizationException (403): here the user is
 * unknown, not merely unauthorized.
 *
 * Maps to HTTP 401 in API responses.
 *
 * @api
 */
class MiddagAuthenticationException extends MiddagException
{
    public function getStatusCode(): int
    {
        return 401;
    }
}
