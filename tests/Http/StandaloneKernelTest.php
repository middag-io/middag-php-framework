<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Http;

use Middag\Framework\Http\Contract\HttpKernelInterface;
use Middag\Framework\Http\StandaloneKernel;
use Nyholm\Psr7\Response as PsrResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request as SfRequest;

/**
 * The StandaloneKernel honours the debug flag instead of always masking the
 * stack trace behind a bare 500.
 *
 * @internal
 */
#[CoversClass(StandaloneKernel::class)]
final class StandaloneKernelTest extends TestCase
{
    #[Test]
    public function convertsTheInnerPsrResponseToAnHttpFoundationResponse(): void
    {
        $kernel = new StandaloneKernel($this->succeedingInner(201, 'created ok'));

        $response = $kernel->handle(SfRequest::create('/'));

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('created ok', (string) $response->getContent());
    }

    #[Test]
    public function debugModeRendersTheStackTrace(): void
    {
        $kernel = new StandaloneKernel($this->throwingInner('boom in handler'), debug: true);

        $response = $kernel->handle(SfRequest::create('/'));

        $this->assertSame(500, $response->getStatusCode());
        $this->assertStringContainsString('boom in handler', (string) $response->getContent());
        $this->assertStringContainsString('Stack', (string) $response->getContent());
    }

    #[Test]
    public function productionModeHidesTheTrace(): void
    {
        $kernel = new StandaloneKernel($this->throwingInner('boom in handler'), debug: false);

        $response = $kernel->handle(SfRequest::create('/'));

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame('Internal Server Error', (string) $response->getContent());
        $this->assertStringNotContainsString('boom in handler', (string) $response->getContent());
    }

    #[Test]
    public function withoutCatchTheThrowablePropagates(): void
    {
        $kernel = new StandaloneKernel($this->throwingInner('boom'), debug: false);

        $this->expectException(RuntimeException::class);
        $kernel->handle(SfRequest::create('/'), catch: false);
    }

    private function succeedingInner(int $status, string $body): HttpKernelInterface
    {
        return new class($status, $body) implements HttpKernelInterface {
            public function __construct(
                private readonly int $status,
                private readonly string $body,
            ) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new PsrResponse($this->status, [], $this->body);
            }
        };
    }

    private function throwingInner(string $message): HttpKernelInterface
    {
        return new class($message) implements HttpKernelInterface {
            public function __construct(private readonly string $message) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw new RuntimeException($this->message);
            }
        };
    }
}
