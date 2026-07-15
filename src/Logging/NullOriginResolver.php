<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Logging;

use Middag\Framework\Logging\Contract\OriginResolverInterface;

/**
 * Null origin resolver — reports `cli` when running under CLI SAPI,
 * `ip:<remote>` when REMOTE_ADDR is available, otherwise `system`.
 *
 * @api
 */
final class NullOriginResolver implements OriginResolverInterface
{
    public function resolve(): string
    {
        if (PHP_SAPI === 'cli') {
            return 'cli';
        }

        /**
         * @codeCoverageIgnoreStart — PHPUnit always runs under the cli SAPI,
         * so the web-request tail is unreachable in any test run (R-05).
         */
        $remote = $_SERVER['REMOTE_ADDR'] ?? '';
        if (is_string($remote) && $remote !== '') {
            return 'ip:' . $remote;
        }

        return 'system';
        // @codeCoverageIgnoreEnd
    }
}
