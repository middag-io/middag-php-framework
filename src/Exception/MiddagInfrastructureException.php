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
 * Infrastructure failure — I/O, external service, or storage error.
 *
 * Thrown when a technical dependency fails (database, file system,
 * HTTP client, queue). The `previous` exception should preserve the
 * original error for debugging.
 *
 * @api
 */
class MiddagInfrastructureException extends MiddagException {}
