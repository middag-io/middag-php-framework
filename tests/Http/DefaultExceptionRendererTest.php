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

use Middag\Framework\Http\DefaultExceptionRenderer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(DefaultExceptionRenderer::class)]
final class DefaultExceptionRendererTest extends TestCase
{
    #[Test]
    public function jsonEnvelopeForApiClientOmitsDebugWhenOff(): void
    {
        $response = (new DefaultExceptionRenderer(false))->render(
            new RuntimeException('boom'),
            Request::create('/'),
            404,
            'not_found',
            'Route not found',
            true,
        );

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(404, $response->getStatusCode());

        $body = json_decode((string) $response->getContent(), true);
        self::assertIsArray($body);
        self::assertSame(['success' => false, 'error' => 'not_found', 'message' => 'Route not found'], $body);
        self::assertArrayNotHasKey('debug', $body);
    }

    #[Test]
    public function htmlPageForBrowserClientIsPlainMessage(): void
    {
        $response = (new DefaultExceptionRenderer(false))->render(
            new RuntimeException('boom'),
            Request::create('/'),
            404,
            'not_found',
            'Route not found',
            false,
        );

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('Route not found', $response->getContent());
    }

    #[Test]
    public function serverErrorHtmlStaysOpaqueWithoutDebug(): void
    {
        $response = (new DefaultExceptionRenderer(false))->render(
            new RuntimeException('secret detail'),
            Request::create('/'),
            500,
            'server_error',
            'Server Error',
            false,
        );

        self::assertSame(500, $response->getStatusCode());
        self::assertSame('Server Error', $response->getContent());
        self::assertStringNotContainsString('secret detail', (string) $response->getContent());
    }

    #[Test]
    public function debugRendersFullPageForServerError(): void
    {
        $response = (new DefaultExceptionRenderer(true))->render(
            new RuntimeException('boom detail'),
            Request::create('/'),
            500,
            'server_error',
            'Server Error',
            false,
        );

        self::assertSame(500, $response->getStatusCode());
        self::assertStringContainsString('RuntimeException', (string) $response->getContent());
        self::assertStringContainsString('boom detail', (string) $response->getContent());
        self::assertStringContainsString('Stack', (string) $response->getContent());
    }

    #[Test]
    public function debugEnrichesJsonWithExceptionDetail(): void
    {
        $response = (new DefaultExceptionRenderer(true))->render(
            new RuntimeException('boom detail'),
            Request::create('/'),
            500,
            'server_error',
            'Server Error',
            true,
        );

        $body = json_decode((string) $response->getContent(), true);
        self::assertIsArray($body);
        self::assertArrayHasKey('debug', $body);

        $debug = $body['debug'];
        self::assertIsArray($debug);
        self::assertSame('boom detail', $debug['message']);
        self::assertSame(RuntimeException::class, $debug['exception']);
    }
}
