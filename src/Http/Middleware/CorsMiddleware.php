<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Http\Middleware;

use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Mirrors CORS headers onto the actual (non-preflight) response.
 *
 * The kernel answers the OPTIONS preflight, but without
 * `Access-Control-Allow-Origin` on the *real* response the browser still blocks a
 * cross-origin XHR from reading it. This middleware adds the header to the
 * downstream response when the request's `Origin` is allowed, so the preflight
 * and the actual response agree.
 *
 * Origins are an explicit allow-list; the default `['*']` echoes `*`
 * (credential-less, matching the kernel preflight). Pass explicit origins plus
 * `allowCredentials: true` for cookie / Authorization cross-origin flows, where
 * the spec forbids the `*` wildcard.
 *
 * @api
 */
final readonly class CorsMiddleware implements MiddlewareInterface
{
    /**
     * @param list<string> $allowedOrigins exact origins, or `['*']` for any
     */
    public function __construct(
        private array $allowedOrigins = ['*'],
        private string $allowedMethods = 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
        private string $allowedHeaders = 'Content-Type, Authorization, X-Requested-With, Accept, Origin, X-CSRF-Token, X-XSRF-TOKEN, X-Inertia, X-Inertia-Version',
        private bool $allowCredentials = false,
    ) {
        if ($allowCredentials && in_array('*', $allowedOrigins, true)) {
            // The CORS spec forbids `Access-Control-Allow-Credentials: true` with
            // a `*` origin; reflecting an arbitrary Origin with credentials is an
            // account-takeover footgun. Fail fast — require an explicit allow-list.
            throw new InvalidArgumentException(
                'CorsMiddleware: the "*" origin wildcard cannot be combined with allowCredentials; '
                . 'pass an explicit origin allow-list for credentialed CORS.',
            );
        }
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        $origin = $request->getHeaderLine('Origin');

        if ($origin === '' || !$this->isAllowed($origin)) {
            return $response;
        }

        // The `*` wildcard is only valid without credentials; with credentials the
        // spec requires echoing the concrete origin.
        $wildcard = !$this->allowCredentials && in_array('*', $this->allowedOrigins, true);

        $response = $response
            ->withHeader('Access-Control-Allow-Origin', $wildcard ? '*' : $origin)
            ->withHeader('Access-Control-Allow-Methods', $this->allowedMethods)
            ->withHeader('Access-Control-Allow-Headers', $this->allowedHeaders);

        if (!$wildcard) {
            // Once we echo a specific origin the response varies by it, so caches
            // must key on Origin.
            $response = $response->withAddedHeader('Vary', 'Origin');
        }

        if ($this->allowCredentials) {
            return $response->withHeader('Access-Control-Allow-Credentials', 'true');
        }

        return $response;
    }

    private function isAllowed(string $origin): bool
    {
        return in_array('*', $this->allowedOrigins, true)
            || in_array($origin, $this->allowedOrigins, true);
    }
}
