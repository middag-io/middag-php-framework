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

use Middag\Framework\Http\Inertia\InertiaManager;
use Middag\Framework\Http\Security\CsrfTokenManager;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Verifies the CSRF token on state-changing requests.
 *
 * Shares the current token as the Inertia `csrf_token` shared prop (so forms and
 * the JS client can echo it back), then — for unsafe methods (POST/PUT/PATCH/
 * DELETE) — requires a matching token in the `X-CSRF-Token` / `X-XSRF-TOKEN`
 * header or a `_token` body field, rejecting mismatches with 419. Safe methods
 * pass straight through.
 *
 * @api
 */
final readonly class VerifyCsrfMiddleware implements MiddlewareInterface
{
    /** HTTP status for a missing/invalid CSRF token (Laravel's de-facto "page expired"). */
    private const TOKEN_MISMATCH = 419;

    /** @var list<string> */
    private const UNSAFE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function __construct(
        private CsrfTokenManager $csrf,
        private ResponseFactoryInterface $responses,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        InertiaManager::share('csrf_token', $this->csrf->token());

        if (in_array(strtoupper($request->getMethod()), self::UNSAFE_METHODS, true)
            && !$this->csrf->isValid($this->tokenFrom($request))) {
            $response = $this->responses->createResponse(self::TOKEN_MISMATCH, 'CSRF Token Mismatch');
            $response->getBody()->write('CSRF token mismatch.');

            return $response;
        }

        return $this->withXsrfCookie($handler->handle($request));
    }

    /**
     * Expose the token to the JS client as a readable cookie so axios / the
     * Inertia client echo it back via the `X-XSRF-TOKEN` header (the branch
     * {@see self::tokenFrom()} already accepts). `httpOnly` is intentionally
     * off — the cookie must be JS-readable; `SameSite=Lax` curbs cross-site
     * leakage. Added (not set) so it never clobbers other Set-Cookie headers.
     */
    private function withXsrfCookie(ResponseInterface $response): ResponseInterface
    {
        $cookie = sprintf('XSRF-TOKEN=%s; Path=/; SameSite=Lax', rawurlencode($this->csrf->token()));

        return $response->withAddedHeader('Set-Cookie', $cookie);
    }

    private function tokenFrom(ServerRequestInterface $request): ?string
    {
        foreach (['X-CSRF-Token', 'X-XSRF-TOKEN'] as $header) {
            if ($request->hasHeader($header)) {
                return $request->getHeaderLine($header);
            }
        }

        $body = $request->getParsedBody();

        if (is_array($body) && isset($body['_token']) && is_string($body['_token'])) {
            return $body['_token'];
        }

        return null;
    }
}
