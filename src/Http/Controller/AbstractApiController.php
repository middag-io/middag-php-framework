<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Http\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Abstract base API controller — forces JSON responses and standardizes the response envelope.
 *
 * {@see isJson()} is hardwired to true, so every response is JSON regardless of the request
 * Accept header. {@see self::jsonResponse()} wraps the payload in a `success` envelope: it derives
 * `success` from the 2xx status (preserving a caller-supplied `success`) and boxes non-array
 * data under a `data` key. Errors flow through {@see errorResponse()}, emitting
 * `{ success: false, message, error_code, debug? }`. The status helpers — {@see created()} (201),
 * {@see noContent()} (204), {@see notFound()} (404), {@see forbidden()} (403),
 * {@see unauthorized()} (401) — return the matching response (204 is an empty body, the rest
 * carry the envelope). Public for subclassing: subclasses (e.g. the demo `TaskApiController`) and
 * platform adapters add platform-specific auth (token, sesskey, etc.).
 *
 * @api
 */
abstract class AbstractApiController extends AbstractController
{
    /**
     * Always true for API controllers — every response is JSON.
     */
    protected function isJson(): bool
    {
        return true;
    }

    /**
     * Return a JSON response wrapped in the standard success envelope.
     *
     * Non-array `$data` is boxed under a `data` key. A `success` flag is derived
     * from the 2xx range of `$status` unless the payload already supplies one.
     */
    protected function jsonResponse(mixed $data, int $status = Response::HTTP_OK): JsonResponse
    {
        $payload = is_array($data) ? $data : ['data' => $data];

        $payload['success'] ??= $status >= Response::HTTP_OK && $status < Response::HTTP_MULTIPLE_CHOICES;

        return parent::jsonResponse($payload, $status);
    }

    /**
     * Send a standardized JSON error envelope: `{ success: false, message, error_code }`.
     *
     * `error_code` mirrors the HTTP `$status`; a non-null `$debug` is attached under `debug`.
     */
    protected function errorResponse(string $message, int $status = Response::HTTP_BAD_REQUEST, mixed $debug = null): JsonResponse
    {
        $data = [
            'success' => false,
            'message' => $message,
            'error_code' => $status,
        ];

        if ($debug !== null) {
            $data['debug'] = $debug;
        }

        return new JsonResponse($data, $status);
    }

    /**
     * 201 Created — envelopes `$data`.
     */
    protected function created(mixed $data = []): JsonResponse
    {
        return $this->jsonResponse($data, Response::HTTP_CREATED);
    }

    /**
     * 204 No Content.
     */
    protected function noContent(): Response
    {
        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * 404 Not Found.
     */
    protected function notFound(string $message = 'Resource not found'): JsonResponse
    {
        return $this->errorResponse($message, Response::HTTP_NOT_FOUND);
    }

    /**
     * 403 Forbidden.
     */
    protected function forbidden(string $message = 'Access denied'): JsonResponse
    {
        return $this->errorResponse($message, Response::HTTP_FORBIDDEN);
    }

    /**
     * 401 Unauthorized.
     */
    protected function unauthorized(string $message = 'Unauthorized'): JsonResponse
    {
        return $this->errorResponse($message, Response::HTTP_UNAUTHORIZED);
    }
}
