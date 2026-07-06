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

use Middag\Framework\Http\FatalErrorHandler;
use Middag\Framework\Observability\ProfileCollector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Stringable;

/**
 * @internal
 */
#[CoversClass(FatalErrorHandler::class)]
final class FatalErrorHandlerTest extends TestCase
{
    /** @var array{type: int, message: string, file: string, line: int} */
    private const ERROR = [
        'type' => E_ERROR,
        'message' => 'Allowed memory size exhausted',
        'file' => '/app/Foo.php',
        'line' => 42,
    ];

    public function testBuildsJsonBodyWithCodeAndNoDetailInProduction(): void
    {
        $handler = new FatalErrorHandler(debug: false);

        [$status, $type, $body] = $handler->buildResponse(self::ERROR, 'ABCD1234', wantsJson: true);

        self::assertSame(500, $status);
        self::assertSame('application/json', $type);

        $decoded = json_decode($body, true);
        self::assertSame('ABCD1234', $decoded['error']['code']);
        self::assertSame('Internal Server Error', $decoded['error']['message']);
        self::assertArrayNotHasKey('detail', $decoded['error'], 'no technical detail leaks in production');
    }

    public function testJsonBodyIncludesDetailInDebug(): void
    {
        $handler = new FatalErrorHandler(debug: true);

        [, , $body] = $handler->buildResponse(self::ERROR, 'ABCD1234', wantsJson: true);

        $decoded = json_decode($body, true);
        self::assertStringContainsString('Allowed memory size exhausted', $decoded['error']['detail']);
        self::assertStringContainsString('/app/Foo.php:42', $decoded['error']['detail']);
    }

    public function testHtmlBodyShowsCodeButHidesDetailInProduction(): void
    {
        $handler = new FatalErrorHandler(debug: false);

        [$status, $type, $body] = $handler->buildResponse(self::ERROR, 'ABCD1234', wantsJson: false);

        self::assertSame(500, $status);
        self::assertStringContainsString('text/html', $type);
        self::assertStringContainsString('ABCD1234', $body);
        self::assertStringNotContainsString('Allowed memory size exhausted', $body);
    }

    public function testContentNegotiation(): void
    {
        $handler = new FatalErrorHandler();

        self::assertTrue($handler->serverWantsJson(['HTTP_ACCEPT' => 'application/json']));
        self::assertTrue($handler->serverWantsJson(['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']));
        self::assertTrue($handler->serverWantsJson(['CONTENT_TYPE' => 'application/json; charset=utf-8']));
        self::assertFalse($handler->serverWantsJson(['HTTP_ACCEPT' => 'text/html']));
        self::assertFalse($handler->serverWantsJson([]));
        // Inertia visits get an HTML reload, never raw JSON.
        self::assertFalse($handler->serverWantsJson(['HTTP_X_INERTIA' => 'true', 'HTTP_ACCEPT' => 'application/json']));
    }

    public function testHtmlBodyIncludesDetailInDebug(): void
    {
        $handler = new FatalErrorHandler(debug: true);

        [, , $body] = $handler->buildResponse(self::ERROR, 'ABCD1234', wantsJson: false);

        self::assertStringContainsString('ABCD1234', $body);
        self::assertStringContainsString('Allowed memory size exhausted', $body);
        self::assertStringContainsString('/app/Foo.php:42', $body);
        self::assertStringContainsString('<pre>', $body, 'debug detail is rendered in a preformatted block');
    }

    public function testRegisterInstallsShutdownHandlerWithoutError(): void
    {
        $handler = new FatalErrorHandler();

        // register_shutdown_function is a global side effect; the guarded
        // shutdown callback is a no-op at a clean process end (no fatal pending).
        $handler->register();

        $this->addToAssertionCount(1);
    }

    public function testHandleShutdownIsNoOpWhenLastErrorIsNotFatal(): void
    {
        // Make error_get_last() report a non-fatal type so the fatal-mask guard
        // returns early — nothing is logged or rendered.
        @trigger_error('a plain user notice', E_USER_NOTICE);

        $logger = new class extends AbstractLogger {
            public int $calls = 0;

            public function log($level, string|Stringable $message, array $context = []): void
            {
                ++$this->calls;
            }
        };

        (new FatalErrorHandler($logger))->handleShutdown();

        self::assertSame(0, $logger->calls, 'a non-fatal last error must not be reported');
    }

    public function testReportLogsCriticalAndRecordsOnProfiler(): void
    {
        $logger = new class extends AbstractLogger {
            /** @var list<array{level: mixed, message: string, context: array<string, mixed>}> */
            public array $records = [];

            public function log($level, string|Stringable $message, array $context = []): void
            {
                $this->records[] = ['level' => $level, 'message' => (string) $message, 'context' => $context];
            }
        };
        $profile = new ProfileCollector();

        $handler = new FatalErrorHandler($logger, $profile);
        $handler->report(self::ERROR, 'ABCD1234');

        self::assertCount(1, $logger->records);
        self::assertSame('critical', $logger->records[0]['level']);
        self::assertSame('ABCD1234', $logger->records[0]['context']['code']);

        $events = $profile->events();
        self::assertCount(1, $events);
        self::assertSame('fatal', $events[0]['category']);
        self::assertSame('ABCD1234', $events[0]['label']);
    }
}
