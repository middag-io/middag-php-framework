<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Observability;

use Middag\Framework\Observability\Contract\ErrorReporterInterface;
use Middag\Framework\Observability\NullErrorReporter;
use Middag\Framework\Observability\SentryErrorReporter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sentry\ClientBuilder;
use Sentry\Event;
use Sentry\SentrySdk;
use Sentry\State\Hub;
use Sentry\Transport\Result;
use Sentry\Transport\ResultStatus;
use Sentry\Transport\TransportInterface;

/**
 * @internal
 */
#[CoversClass(NullErrorReporter::class)]
#[CoversClass(SentryErrorReporter::class)]
final class ErrorReporterTest extends TestCase
{
    public function testNullReporterDiscardsWithoutError(): void
    {
        $reporter = new NullErrorReporter();

        self::assertInstanceOf(ErrorReporterInterface::class, $reporter);
        $reporter->report(new RuntimeException('boom'), ['request' => ['path' => '/x']]);

        // Reaching this line proves the no-op default returned without throwing.
        self::assertTrue(true);
    }

    public function testSentryReporterIsANoOpWithoutInitialisedSdk(): void
    {
        $reporter = new SentryErrorReporter();

        self::assertInstanceOf(ErrorReporterInterface::class, $reporter);

        // The SDK was never init()ed in this process: Sentry's capture
        // functions no-op, and the reporter's never-throw contract holds.
        $reporter->report(new RuntimeException('boom'), ['scalar' => 42, 'nested' => ['a' => 1]]);

        self::assertTrue(true);
    }

    public function testSentryReporterForwardsExceptionAndContextToLiveSdk(): void
    {
        $transport = new class implements TransportInterface {
            /** @var list<Event> */
            public array $events = [];

            public function send(Event $event): Result
            {
                $this->events[] = $event;

                return new Result(ResultStatus::success(), $event);
            }

            public function close(?int $timeout = null): Result
            {
                return new Result(ResultStatus::success());
            }
        };

        $client = ClientBuilder::create([
            'dsn' => 'https://public@sentry.invalid/1',
            'default_integrations' => false,
        ])->setTransport($transport)->getClient();

        $previousHub = SentrySdk::getCurrentHub();
        SentrySdk::setCurrentHub(new Hub($client));

        try {
            (new SentryErrorReporter())->report(
                new RuntimeException('live boom'),
                ['request' => ['path' => '/x'], 'scalar' => 42],
            );
        } finally {
            SentrySdk::setCurrentHub($previousHub);
        }

        self::assertCount(1, $transport->events);
        $event = $transport->events[0];

        $exceptions = $event->getExceptions();
        self::assertNotEmpty($exceptions);
        self::assertSame('live boom', $exceptions[0]->getValue());

        // Array context passes through; scalars are wrapped per the contract.
        $contexts = $event->getContexts();
        self::assertSame(['path' => '/x'], $contexts['request']);
        self::assertSame(['value' => 42], $contexts['scalar']);
    }

    public function testSentryReporterNeverThrowsWhenTheSdkExplodes(): void
    {
        // The Client swallows transport failures itself, so the reporter's own
        // guard is only reachable when the hub blows up before capture.
        $explodingHub = new class extends Hub {
            public function withScope(callable $callback): void
            {
                throw new RuntimeException('sdk exploded');
            }
        };

        $previousHub = SentrySdk::getCurrentHub();
        SentrySdk::setCurrentHub($explodingHub);

        try {
            // Telemetry failure must never take the request down.
            (new SentryErrorReporter())->report(new RuntimeException('boom'));
        } finally {
            SentrySdk::setCurrentHub($previousHub);
        }

        self::assertTrue(true);
    }
}
