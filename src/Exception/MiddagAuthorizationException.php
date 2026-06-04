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
 * Authorization failure — identified user lacks permission.
 *
 * Thrown when an authenticated user attempts an action they lack the
 * required capability for.
 *
 * Distinct from MiddagAuthenticationException (401): here the user is
 * known, just not permitted; there the user is unidentified.
 *
 * Maps to HTTP 403 in API responses.
 *
 * @api
 */
class MiddagAuthorizationException extends MiddagException
{
    public function getStatusCode(): int
    {
        return 403;
    }
}
