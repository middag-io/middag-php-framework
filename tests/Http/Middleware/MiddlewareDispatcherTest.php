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

use Middag\Framework\Http\Middleware\MiddlewareDispatcher;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * The PSR-15 pipeline runs middlewares in order around the inner kernel,
 * each able to short-circuit, and stays re-entrant across calls.
 *
 * @internal
 */
#[CoversNothing]
final class MiddlewareDispatcherTest extends TestCase
{
    #[Test]
    public function withoutMiddlewareDelegatesStraightToKernel(): void
    {
        $dispatcher = new MiddlewareDispatcher($this->kernel('KERNEL'));

        $this->assertSame('KERNEL', (string) $dispatcher->handle($this->request())->getBody());
    }

    #[Test]
    public function runsMiddlewaresOutsideInThenReachesKernel(): void
    {
        $dispatcher = new MiddlewareDispatcher(
            $this->kernel('[kernel]'),
            $this->tag('A'),
            $this->tag('B'),
        );

        // A wraps B wraps kernel: A sees the innermost result last.
        $this->assertSame('A(B([kernel]))', (string) $dispatcher->handle($this->request())->getBody());
    }

    #[Test]
    public function aMiddlewareCanShortCircuitBeforeTheKernel(): void
    {
        $shortCircuit = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                $psr17 = new Psr17Factory();

                return $psr17->createResponse(403)->withBody($psr17->createStream('denied'));
            }
        };

        $dispatcher = new MiddlewareDispatcher($this->kernel('NEVER'), $shortCircuit);
        $response = $dispatcher->handle($this->request());

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('denied', (string) $response->getBody());
    }

    #[Test]
    public function isReentrantAcrossRepeatedCalls(): void
    {
        $dispatcher = new MiddlewareDispatcher($this->kernel('[k]'), $this->tag('X'));

        $this->assertSame('X([k])', (string) $dispatcher->handle($this->request())->getBody());
        $this->assertSame('X([k])', (string) $dispatcher->handle($this->request())->getBody());
    }

    private function tag(string $label): MiddlewareInterface
    {
        return new class($label) implements MiddlewareInterface {
            public function __construct(private readonly string $label) {}

            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                $inner = (string) $handler->handle($request)->getBody();
                $psr17 = new Psr17Factory();

                return $psr17->createResponse()->withBody($psr17->createStream($this->label . '(' . $inner . ')'));
            }
        };
    }

    private function kernel(string $body): RequestHandlerInterface
    {
        return new class($body) implements RequestHandlerInterface {
            public function __construct(private readonly string $body) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $psr17 = new Psr17Factory();

                return $psr17->createResponse()->withBody($psr17->createStream($this->body));
            }
        };
    }

    private function request(): ServerRequestInterface
    {
        return new ServerRequest('GET', '/');
    }
}
