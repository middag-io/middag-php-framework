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
 * Requested resource or record not found.
 *
 * Thrown when a lookup by ID, identifier, or criteria yields no result.
 * Maps to HTTP 404 in API responses.
 *
 * @api
 */
class MiddagNotFoundException extends MiddagDomainException
{
    public function getStatusCode(): int
    {
        return 404;
    }
}
