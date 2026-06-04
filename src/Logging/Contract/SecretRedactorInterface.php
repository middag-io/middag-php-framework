<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Logging\Contract;

use Middag\Framework\Logging\LoggerFactory;
use Middag\Framework\Logging\Redactor\DefaultSecretRedactor;

/**
 * Strategy that scrubs sensitive values out of a log context before it is
 * written to disk.
 *
 * Override seam: the framework ships a default ({@see DefaultSecretRedactor})
 * wired into {@see LoggerFactory} by default, but a
 * consumer can bind its own implementation (extra keys, value patterns,
 * tokenisation) without touching the logging pipeline.
 *
 * @api
 */
interface SecretRedactorInterface
{
    /**
     * Return a copy of $context with sensitive values replaced.
     *
     * Implementations MUST be pure (no mutation of the input) and SHOULD recurse
     * into nested arrays so secrets nested in request payloads are caught.
     *
     * @param array<array-key, mixed> $context
     *
     * @return array<array-key, mixed>
     */
    public function redact(array $context): array;
}
