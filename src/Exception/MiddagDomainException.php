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
 * Domain rule violated or business invariant broken.
 *
 * Thrown when an operation conflicts with domain logic — e.g. invalid
 * state transition, constraint violation, or business rule failure.
 *
 * @api
 */
class MiddagDomainException extends MiddagException
{
    public function getStatusCode(): int
    {
        return 400;
    }
}
