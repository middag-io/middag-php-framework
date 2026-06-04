<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Http;

use Throwable;

/**
 * Shared developer exception page renderer.
 *
 * One place builds the debug HTML for an uncaught throwable (class, message,
 * file:line, stack trace) so the {@see HttpKernel} and the {@see StandaloneKernel}
 * render identical pages instead of the StandaloneKernel swallowing the trace
 * behind a bare "Internal Server Error". Output is fully escaped.
 *
 * @internal
 */
final class DebugExceptionRenderer
{
    /**
     * Render the debug HTML page for an uncaught throwable.
     */
    public static function html(Throwable $throwable): string
    {
        return sprintf(
            '<!doctype html><meta charset="UTF-8"><title>500 — %s</title>'
            . '<h1>%s</h1><pre>%s</pre><h2>Stack</h2><pre>%s</pre>',
            htmlspecialchars($throwable::class, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($throwable::class . ': ' . $throwable->getMessage(), ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($throwable->getFile() . ':' . $throwable->getLine(), ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($throwable->getTraceAsString(), ENT_QUOTES, 'UTF-8'),
        );
    }
}
