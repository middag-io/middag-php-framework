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
use Middag\Framework\Tests\Http\Fixture\FatalShutdownState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use ReflectionMethod;
use Stringable;

require_once __DIR__ . '/Fixture/fatal_error_handler_functions.php';

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

    protected function tearDown(): void
    {
        FatalShutdownState::reset();
    }

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

    #[DoesNotPerformAssertions]
    public function testRegisterInstallsShutdownHandlerWithoutError(): void
    {
        $handler = new FatalErrorHandler();

        // register_shutdown_function is a global side effect; the guarded
        // shutdown callback is a no-op at a clean process end (no fatal pending).
        $handler->register();
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

    public function testGenerateErrorCodeProducesAnEightCharUppercaseHexString(): void
    {
        $handler = new FatalErrorHandler();

        $method = new ReflectionMethod($handler, 'generateErrorCode');
        $code = $method->invoke($handler);

        self::assertMatchesRegularExpression('/^[0-9A-F]{8}$/', $code);
    }

    public function testHandleShutdownReportsAndEmitsTheNegotiatedJsonResponse(): void
    {
        FatalShutdownState::$active = true;
        FatalShutdownState::$errorGetLast = self::ERROR;
        FatalShutdownState::$headersSent = false;

        $logger = new class extends AbstractLogger {
            /** @var list<array{level: mixed, context: array<string, mixed>}> */
            public array $records = [];

            public function log($level, string|Stringable $message, array $context = []): void
            {
                $this->records[] = ['level' => $level, 'context' => $context];
            }
        };
        $profile = new ProfileCollector();
        $handler = new FatalErrorHandler($logger, $profile, debug: true);

        $server = $_SERVER;
        $_SERVER['HTTP_ACCEPT'] = 'application/json';

        try {
            ob_start();
            $handler->handleShutdown();
            $body = ob_get_clean();
        } finally {
            $_SERVER = $server;
        }

        self::assertCount(1, $logger->records, 'the fatal is logged before anything is rendered');
        self::assertSame('critical', $logger->records[0]['level']);
        self::assertCount(1, $profile->events(), 'the fatal is recorded on the profiler');

        self::assertSame([500], FatalShutdownState::$responseCodes);
        self::assertContains('Content-Type: application/json', FatalShutdownState::$headers);

        $decoded = json_decode((string) $body, true);
        self::assertIsArray($decoded);
        self::assertStringContainsString('Allowed memory size exhausted', $decoded['error']['detail']);
        self::assertSame($logger->records[0]['context']['code'], $decoded['error']['code']);
    }

    public function testHandleShutdownStopsAfterReportingWhenHeadersAlreadySent(): void
    {
        FatalShutdownState::$active = true;
        FatalShutdownState::$errorGetLast = self::ERROR;
        FatalShutdownState::$headersSent = true;

        $logger = new class extends AbstractLogger {
            public int $calls = 0;

            public function log($level, string|Stringable $message, array $context = []): void
            {
                ++$this->calls;
            }
        };
        $handler = new FatalErrorHandler($logger);

        ob_start();
        $handler->handleShutdown();
        $body = ob_get_clean();

        self::assertSame(1, $logger->calls, 'the fatal is still reported at critical');
        self::assertSame('', $body, 'nothing is rendered once output has already started');
        self::assertSame([], FatalShutdownState::$responseCodes, 'no status is sent once headers are already sent');
        self::assertSame([], FatalShutdownState::$headers, 'no header is sent once headers are already sent');
    }

    /**
     * End-to-end through a real fatal: a genuine, uncatchable-by-userland
     * E_USER_ERROR (in FATAL_MASK, and — unlike E_ERROR/E_PARSE/E_CORE_ERROR/
     * E_COMPILE_ERROR — the only fatal type PHP lets a script provoke without
     * killing the whole test runner) reaches error_get_last() and fires
     * register_shutdown_function() callbacks exactly like a real crash would.
     * Spawned in a subprocess because a real fatal still ends *that* process.
     */
    public function testHandleShutdownRendersTheNegotiated500ForARealFatal(): void
    {
        $autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';

        $script = <<<'PHP'
            require %s;
            $handler = new \Middag\Framework\Http\FatalErrorHandler(debug: true);
            $handler->register();
            trigger_error('boom-fatal-subprocess-test', E_USER_ERROR);
            PHP;
        $script = sprintf($script, var_export($autoload, true));

        $process = proc_open(
            [PHP_BINARY, '-d', 'display_errors=stderr', '-r', $script],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        self::assertIsResource($process);

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        self::assertNotFalse($stdout);
        self::assertStringContainsString('Internal Server Error', $stdout);
        self::assertStringContainsString('boom-fatal-subprocess-test', $stdout, 'debug:true includes the technical detail');
        self::assertMatchesRegularExpression('/support:\s*<strong>[0-9A-F]{8}<\/strong>/', $stdout);
    }
}
