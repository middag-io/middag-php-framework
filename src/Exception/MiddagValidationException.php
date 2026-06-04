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

use Throwable;

/**
 * Input validation failed.
 *
 * Carries a structured map of field-level errors for use in forms
 * and API responses (HTTP 422).
 *
 * @api
 */
class MiddagValidationException extends MiddagDomainException
{
    /**
     * @param string                         $message  human-readable summary
     * @param array<string, string|string[]> $errors   field => error(s) map
     * @param null|Throwable                 $previous chained exception
     */
    public function __construct(
        string $message = 'Validation failed',
        private readonly array $errors = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * @return array<string, string|string[]>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    public function getStatusCode(): int
    {
        return 422;
    }
}
