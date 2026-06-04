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

use RuntimeException;

/**
 * Root exception for the MIDDAG framework.
 *
 * All framework exceptions extend this class, enabling typed catch
 * for any MIDDAG error: `catch (MiddagException $e)`.
 *
 * Subclasses override `getStatusCode()` to provide the appropriate
 * HTTP status code for API responses.
 *
 * @api
 */
abstract class MiddagException extends RuntimeException
{
    /**
     * HTTP status code for API/controller error handling.
     */
    public function getStatusCode(): int
    {
        return 500;
    }
}
