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

use InvalidArgumentException;
use Middag\Framework\Http\Middleware\CorsMiddleware;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * @internal
 */
#[CoversClass(CorsMiddleware::class)]
final class CorsMiddlewareTest extends TestCase
{
    #[Test]
    public function wildcardEchoesStarOnTheRealResponse(): void
    {
        $request = (new ServerRequest('GET', '/api'))->withHeader('Origin', 'https://client.test');

        $response = (new CorsMiddleware())->process($request, $this->reached());

        self::assertSame('*', $response->getHeaderLine('Access-Control-Allow-Origin'));
        self::assertNotSame('', $response->getHeaderLine('Access-Control-Allow-Methods'));
    }

    #[Test]
    public function disallowedOriginGetsNoCorsHeader(): void
    {
        $request = (new ServerRequest('GET', '/api'))->withHeader('Origin', 'https://evil.test');

        $response = (new CorsMiddleware(['https://app.test']))->process($request, $this->reached());

        self::assertFalse($response->hasHeader('Access-Control-Allow-Origin'));
    }

    #[Test]
    public function requestWithoutOriginPassesThroughUntouched(): void
    {
        $response = (new CorsMiddleware())->process(new ServerRequest('GET', '/api'), $this->reached());

        self::assertFalse($response->hasHeader('Access-Control-Allow-Origin'));
    }

    #[Test]
    public function credentialedAllowListEchoesOriginWithVaryAndCredentials(): void
    {
        $request = (new ServerRequest('GET', '/api'))->withHeader('Origin', 'https://app.test');

        $response = (new CorsMiddleware(['https://app.test'], allowCredentials: true))
            ->process($request, $this->reached());

        self::assertSame('https://app.test', $response->getHeaderLine('Access-Control-Allow-Origin'));
        self::assertSame('true', $response->getHeaderLine('Access-Control-Allow-Credentials'));
        self::assertStringContainsString('Origin', $response->getHeaderLine('Vary'));
    }

    #[Test]
    public function rejectsWildcardOriginCombinedWithCredentials(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new CorsMiddleware(['*'], allowCredentials: true);
    }

    #[Test]
    public function correctsAPreflightStyleResponseFromTheInnerHandler(): void
    {
        // Mimics the kernel's hardcoded OPTIONS preflight (ACAO: *): the
        // middleware overwrites it so preflight and real response agree under a
        // credentialed allow-list.
        $request = (new ServerRequest('OPTIONS', '/api'))->withHeader('Origin', 'https://app.test');
        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return (new Psr17Factory())->createResponse(204)->withHeader('Access-Control-Allow-Origin', '*');
            }
        };

        $response = (new CorsMiddleware(['https://app.test'], allowCredentials: true))->process($request, $handler);

        self::assertSame('https://app.test', $response->getHeaderLine('Access-Control-Allow-Origin'));
        self::assertSame('true', $response->getHeaderLine('Access-Control-Allow-Credentials'));
    }

    private function reached(): RequestHandlerInterface
    {
        return new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return (new Psr17Factory())->createResponse(200);
            }
        };
    }
}
