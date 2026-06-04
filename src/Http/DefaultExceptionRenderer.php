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

use Middag\Framework\Http\Contract\ExceptionRendererInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Default OSS exception renderer.
 *
 * Emits a JSON envelope (`{success, error, message}`) for API/XHR clients and a
 * minimal HTML page for browsers; when debug is on it adds a debug payload to
 * the JSON and renders the full {@see DebugExceptionRenderer} page for 5xx
 * errors. The output buffer is cleaned first so stray host output never corrupts
 * the response body.
 *
 * Adapters either implement {@see ExceptionRendererInterface} directly or extend
 * this class and override {@see self::renderHtmlPage()} to wrap the host layout.
 *
 * @api
 */
readonly class DefaultExceptionRenderer implements ExceptionRendererInterface
{
    public function __construct(private bool $debug = false) {}

    public function render(
        Throwable $throwable,
        Request $request,
        int $statusCode,
        string $errorCode,
        string $message,
        bool $isJson,
    ): Response {
        if ($isJson) {
            return $this->renderJson($throwable, $statusCode, $errorCode, $message);
        }

        if ($this->debugEnabled() && $statusCode >= Response::HTTP_INTERNAL_SERVER_ERROR) {
            return new Response(
                DebugExceptionRenderer::html($throwable),
                $statusCode,
                ['Content-Type' => 'text/html; charset=UTF-8'],
            );
        }

        return $this->renderHtmlPage($throwable, $statusCode, $message);
    }

    /**
     * Render the minimal HTML error page for a browser client.
     *
     * Override point for adapters that extend this renderer to wrap the host
     * layout (themed error pages) while keeping the JSON and debug paths intact.
     */
    protected function renderHtmlPage(Throwable $throwable, int $statusCode, string $message): Response
    {
        return new Response($message, $statusCode);
    }

    /**
     * Build the JSON error envelope, enriched with debug detail when enabled.
     */
    private function renderJson(Throwable $throwable, int $statusCode, string $errorCode, string $message): JsonResponse
    {
        $body = [
            'success' => false,
            'error' => $errorCode,
            'message' => $message,
        ];

        if ($this->debugEnabled()) {
            $body['debug'] = [
                'message' => $throwable->getMessage(),
                'exception' => $throwable::class,
                'file' => $throwable->getFile() . ':' . $throwable->getLine(),
                'trace' => $throwable->getTraceAsString(),
            ];
        }

        return new JsonResponse($body, $statusCode);
    }

    /**
     * Whether debug output is enabled (constructor flag or environment override).
     */
    private function debugEnabled(): bool
    {
        return $this->debug
            || (isset($_ENV['APP_DEBUG']) && (string) $_ENV['APP_DEBUG'] === '1')
            || (defined('MIDDAG_DEBUG') && MIDDAG_DEBUG);
    }
}
