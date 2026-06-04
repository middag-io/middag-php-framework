<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Http\Middleware;

use Middag\Framework\Http\Inertia\InertiaManager;
use Middag\Framework\Http\Middleware\VerifyCsrfMiddleware;
use Middag\Framework\Http\Security\CsrfTokenManager;
use Middag\Framework\Http\Session\ArraySession;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * CSRF verification on unsafe methods, token sharing, safe-method pass.
 *
 * @internal
 */
#[CoversNothing]
final class VerifyCsrfMiddlewareTest extends TestCase
{
    #[Test]
    public function safeMethodPassesAndSharesTheToken(): void
    {
        $csrf = new CsrfTokenManager(new ArraySession());

        $response = $this->middleware($csrf)->process(new ServerRequest('GET', '/'), $this->reached());

        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame($csrf->token(), InertiaManager::getShared()['csrf_token']);
    }

    #[Test]
    public function unsafeMethodWithoutTokenIsRejectedWith419(): void
    {
        $response = $this->middleware(new CsrfTokenManager(new ArraySession()))
            ->process(new ServerRequest('POST', '/tasks'), $this->reached());

        $this->assertSame(419, $response->getStatusCode());
    }

    #[Test]
    public function unsafeMethodWithValidHeaderTokenPasses(): void
    {
        $csrf = new CsrfTokenManager(new ArraySession());
        $token = $csrf->token();

        $request = (new ServerRequest('POST', '/tasks'))->withHeader('X-CSRF-Token', $token);
        $response = $this->middleware($csrf)->process($request, $this->reached());

        $this->assertSame(204, $response->getStatusCode());
    }

    #[Test]
    public function unsafeMethodWithValidBodyTokenPasses(): void
    {
        $csrf = new CsrfTokenManager(new ArraySession());
        $token = $csrf->token();

        $request = (new ServerRequest('POST', '/tasks'))->withParsedBody(['_token' => $token]);
        $response = $this->middleware($csrf)->process($request, $this->reached());

        $this->assertSame(204, $response->getStatusCode());
    }

    #[Test]
    public function setsReadableXsrfCookieOnThePassThroughResponse(): void
    {
        $csrf = new CsrfTokenManager(new ArraySession());

        $response = $this->middleware($csrf)->process(new ServerRequest('GET', '/'), $this->reached());

        $cookies = $response->getHeader('Set-Cookie');
        $this->assertNotEmpty($cookies);
        $this->assertStringContainsString('XSRF-TOKEN=' . rawurlencode($csrf->token()), $cookies[0]);
        // Must be JS-readable for axios/Inertia to echo it back.
        $this->assertStringNotContainsStringIgnoringCase('httponly', $cookies[0]);
    }

    private function middleware(CsrfTokenManager $csrf): VerifyCsrfMiddleware
    {
        return new VerifyCsrfMiddleware($csrf, new Psr17Factory());
    }

    private function reached(): RequestHandlerInterface
    {
        return new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return (new Psr17Factory())->createResponse(204);
            }
        };
    }
}
