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

use Middag\Framework\Http\Contract\SessionInterface;
use Middag\Framework\Http\Middleware\StartSessionMiddleware;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * The first default middleware starts the session, then delegates.
 *
 * @internal
 */
#[CoversClass(StartSessionMiddleware::class)]
final class StartSessionMiddlewareTest extends TestCase
{
    #[Test]
    public function startsSessionBeforeDelegatingToTheHandler(): void
    {
        $session = new class implements SessionInterface {
            public bool $started = false;

            public function start(): void
            {
                $this->started = true;
            }

            public function has(string $key): bool
            {
                return false;
            }

            public function get(string $key, mixed $default = null): mixed
            {
                return $default;
            }

            public function set(string $key, mixed $value): void {}

            public function remove(string $key): void {}

            public function clear(): void {}

            public function regenerate(bool $deleteOld = true): void {}
        };

        $handler = new class implements RequestHandlerInterface {
            public bool $reached = false;

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->reached = true;

                return (new Psr17Factory())->createResponse(204);
            }
        };

        $response = (new StartSessionMiddleware($session))->process(new ServerRequest('GET', '/'), $handler);

        $this->assertTrue($session->started, 'session must be started');
        $this->assertTrue($handler->reached, 'inner handler must run');
        $this->assertSame(204, $response->getStatusCode());
    }
}
