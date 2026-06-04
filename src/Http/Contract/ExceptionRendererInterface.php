<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Http\Contract;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Renders a mapped exception into an HTTP response.
 *
 * The kernel resolves the HTTP status code and a stable machine error code by
 * exception type, then delegates body construction to this renderer. The
 * framework ships `DefaultExceptionRenderer` as the OSS default (JSON envelope
 * for API/XHR, a minimal HTML page for browsers, a full debug page when debug
 * is on). Adapters bind their own implementation to wrap the host layout
 * (themed 404/500 pages) without subclassing the kernel.
 *
 * Validation failures are handled by the kernel itself (field-level error map
 * and flash redirect) and never reach this renderer.
 *
 * @api
 */
interface ExceptionRendererInterface
{
    /**
     * Render an already-mapped error to a response.
     *
     * @param Throwable $throwable  the unwrapped error being rendered
     * @param Request   $request    the current request (for content or layout hints)
     * @param int       $statusCode the resolved HTTP status code
     * @param string    $errorCode  a stable machine code (e.g. 'not_found', 'server_error')
     * @param string    $message    a client-safe summary (already redacted for 5xx)
     * @param bool      $isJson     whether the client expects a JSON response
     */
    public function render(
        Throwable $throwable,
        Request $request,
        int $statusCode,
        string $errorCode,
        string $message,
        bool $isJson,
    ): Response;
}
