<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Bus\Command;

use Closure;
use InvalidArgumentException;
use Middag\Framework\Bus\Command\CommandWorker;
use Middag\Framework\Bus\Command\WorkerLimits;
use Middag\Framework\Bus\Contract\MessageBusInterface;
use Middag\Framework\Bus\Retry\AttemptStamp;
use Middag\Framework\Bus\Transport\InMemoryTransport;
use Middag\Framework\Bus\Transport\TransportLocator;
use Middag\Framework\Tests\Bus\Command\Fixture\FixedRetryPolicy;
use Middag\Framework\Tests\Bus\Command\Fixture\RecordingHeartbeat;
use Middag\Framework\Tests\Bus\Command\Fixture\RecordingLogger;
use Middag\Framework\Tests\Bus\Command\Fixture\ThrowingIterationTransport;
use Middag\Framework\Tests\Bus\Fixture\RecordCommand;
use Middag\Framework\Tests\Bus\Retry\Fixture\FakeAttemptable;
use Middag\Framework\Tests\Bus\Retry\Fixture\InMemoryAttemptStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use RuntimeException;
use stdClass;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Throwable;

/**
 * core#164 F4: {@see CommandWorker} must never let a handler failure or a
 * decode failure escape drain() and kill the loop, and must apply retry
 * bookkeeping to the queue row (via {@see AttemptStamp}) rather than to
 * envelope stamps.
 *
 * @internal
 */
#[CoversClass(CommandWorker::class)]
final class CommandWorkerTest extends TestCase
{
    #[Test]
    public function twoArgConstructorDegradesToAPlainAckDrainWithNoRetryBookkeeping(): void
    {
        $transport = new InMemoryTransport();
        $transport->send(new Envelope(new RecordCommand('ok')));

        $handled = [];

        $worker = new CommandWorker($transport, $this->recordingBus($handled));

        self::assertSame(1, $worker->drain());
        self::assertSame(['ok'], $handled);
        self::assertSame([], iterator_to_array($transport->get()), 'acked, removed from the queue');
    }

    #[Test]
    public function threeArgConstructorStillNamesTheTransportOnTheReceivedStamp(): void
    {
        $transport = new InMemoryTransport();
        $transport->send(new Envelope(new RecordCommand('ok')));

        $handled = [];

        $worker = new CommandWorker($transport, $this->recordingBus($handled), 'reports');

        self::assertSame(1, $worker->drain());
    }

    #[Test]
    public function handlerFailureWithoutRetryCollaboratorsLogsAndRejectsWithoutRethrowing(): void
    {
        $transport = new InMemoryTransport();
        $transport->send(new Envelope(new RecordCommand('doomed')));

        $logger = new RecordingLogger();

        $worker = new CommandWorker(
            $transport,
            $this->throwingBus(new RuntimeException('handler blew up')),
            logger: $logger,
        );

        $handled = $worker->drain();

        self::assertSame(1, $handled, 'still counted as processed');
        self::assertSame([], iterator_to_array($transport->get()), 'rejected, not left on the queue');
        self::assertNotEmpty($logger->messagesAtLevel('error'));
    }

    #[Test]
    public function retryableFailureRecordsFailureOnTheRowAndRejectsTheEnvelope(): void
    {
        $transport = new InMemoryTransport();
        $item = new FakeAttemptable(attempts: 1, maxAttempts: 3);
        $transport->send((new Envelope(new RecordCommand('retry-me')))->with(new AttemptStamp(7, $item)));
        $store = new InMemoryAttemptStore();
        $exception = new RuntimeException('transient failure');

        $before = time();
        $worker = new CommandWorker(
            $transport,
            $this->throwingBus($exception),
            retryPolicy: new FixedRetryPolicy(retryable: true, waitingTimeMilliseconds: 2500),
            attemptStore: $store,
        );
        $handled = $worker->drain();
        $after = time();

        self::assertSame(1, $handled);
        self::assertSame([], $store->dead);
        self::assertArrayHasKey(7, $store->failures);
        self::assertSame($exception, $store->failures[7]['exception']);
        self::assertGreaterThanOrEqual($before + 2, $store->failures[7]['availableAt']);
        self::assertLessThanOrEqual($after + 2, $store->failures[7]['availableAt']);
        self::assertSame([], iterator_to_array($transport->get()), 'rejected: the row, not the transport, owns re-delivery');
    }

    #[Test]
    public function nonRetryableFailureMarksTheRowDeadAndRejectsTheEnvelope(): void
    {
        $transport = new InMemoryTransport();
        $item = new FakeAttemptable(attempts: 3, maxAttempts: 3);
        $transport->send((new Envelope(new RecordCommand('exhausted')))->with(new AttemptStamp(9, $item)));
        $store = new InMemoryAttemptStore();

        $worker = new CommandWorker(
            $transport,
            $this->throwingBus(new RuntimeException('final failure')),
            retryPolicy: new FixedRetryPolicy(retryable: false),
            attemptStore: $store,
        );

        self::assertSame(1, $worker->drain());
        self::assertSame([9], $store->dead);
        self::assertSame([], $store->failures);
        self::assertSame([], iterator_to_array($transport->get()));
    }

    #[Test]
    public function successRecordsSuccessOnTheRowThenAcks(): void
    {
        $transport = new InMemoryTransport();
        $item = new FakeAttemptable();
        $transport->send((new Envelope(new RecordCommand('fine')))->with(new AttemptStamp(3, $item)));
        $store = new InMemoryAttemptStore();
        $handled = [];

        $worker = new CommandWorker(
            $transport,
            $this->recordingBus($handled),
            retryPolicy: new FixedRetryPolicy(retryable: true),
            attemptStore: $store,
        );

        self::assertSame(1, $worker->drain());
        self::assertSame([3], $store->succeeded);
        self::assertSame(['fine'], $handled);
        self::assertSame([], iterator_to_array($transport->get()));
    }

    #[Test]
    public function envelopesWithoutAnAttemptStampSkipBookkeepingEvenWhenCollaboratorsAreConfigured(): void
    {
        $transport = new InMemoryTransport();
        $transport->send(new Envelope(new RecordCommand('no-stamp-fail')));
        $transport->send(new Envelope(new RecordCommand('no-stamp-ok')));

        $store = new InMemoryAttemptStore();
        $logger = new RecordingLogger();
        $succeeded = [];

        $worker = new CommandWorker(
            $transport,
            $this->bus(function (object $message) use (&$succeeded): void {
                if ($message->value === 'no-stamp-fail') {
                    throw new RuntimeException('no row to blame');
                }

                $succeeded[] = $message->value;
            }),
            retryPolicy: new FixedRetryPolicy(retryable: true),
            attemptStore: $store,
            logger: $logger,
        );

        self::assertSame(2, $worker->drain());
        self::assertSame([], $store->succeeded, 'no id to record success against');
        self::assertSame([], $store->dead, 'no id to record death against');
        self::assertSame([], $store->failures, 'no id to record failure against');
        self::assertSame(['no-stamp-ok'], $succeeded);
        self::assertNotEmpty($logger->messagesAtLevel('error'));
        self::assertSame([], iterator_to_array($transport->get()));
    }

    #[Test]
    public function decodingFailureFromTheHandlerIsAlwaysDeadLetteredNeverRetried(): void
    {
        $transport = new InMemoryTransport();
        $item = new FakeAttemptable(attempts: 0, maxAttempts: 5);
        $transport->send((new Envelope(new RecordCommand('corrupt')))->with(new AttemptStamp(11, $item)));
        $store = new InMemoryAttemptStore();

        $worker = new CommandWorker(
            $transport,
            $this->throwingBus(new MessageDecodingFailedException('cannot decode')),
            // Retryable would say "yes" — a decode failure must ignore that.
            retryPolicy: new FixedRetryPolicy(retryable: true),
            attemptStore: $store,
        );

        self::assertSame(1, $worker->drain());
        self::assertSame([11], $store->dead);
        self::assertSame([], $store->failures, 'never goes through the retry policy');
        self::assertSame([], iterator_to_array($transport->get()));
    }

    #[Test]
    public function decodingFailureThrownWhileAdvancingTheIteratorIsCaughtAndTheWorkerSurvives(): void
    {
        $transport = new ThrowingIterationTransport();
        $logger = new RecordingLogger();
        $handled = [];

        $worker = new CommandWorker($transport, $this->recordingBus($handled), logger: $logger);

        $processed = $worker->drain();

        self::assertSame(1, $processed, 'the good message before the poison one still counts');
        self::assertSame(['first'], $handled);
        self::assertCount(1, $transport->acked);
        self::assertNotEmpty($logger->messagesAtLevel('error'));
    }

    #[Test]
    public function messageLimitStopsDrainingAfterTheConfiguredCount(): void
    {
        $transport = new InMemoryTransport();
        $transport->send(new Envelope(new RecordCommand('a')));
        $transport->send(new Envelope(new RecordCommand('b')));
        $transport->send(new Envelope(new RecordCommand('c')));

        $handled = [];

        $worker = new CommandWorker(
            $transport,
            $this->recordingBus($handled),
            limits: new WorkerLimits(messageLimit: 2),
        );

        self::assertSame(2, $worker->drain());
        self::assertSame(['a', 'b'], $handled);
        self::assertCount(1, iterator_to_array($transport->get()), 'the third message is left untouched');
    }

    #[Test]
    public function timeLimitOfZeroStopsBeforeProcessingAnyMessage(): void
    {
        $transport = new InMemoryTransport();
        $transport->send(new Envelope(new RecordCommand('never')));

        $handled = [];

        $worker = new CommandWorker(
            $transport,
            $this->recordingBus($handled),
            limits: new WorkerLimits(timeLimitSeconds: 0),
        );

        self::assertSame(0, $worker->drain());
        self::assertSame([], $handled);
        self::assertCount(1, iterator_to_array($transport->get()));
    }

    #[Test]
    public function memoryLimitOfZeroStopsBeforeProcessingAnyMessage(): void
    {
        $transport = new InMemoryTransport();
        $transport->send(new Envelope(new RecordCommand('never')));

        $handled = [];

        $worker = new CommandWorker(
            $transport,
            $this->recordingBus($handled),
            limits: new WorkerLimits(memoryLimitBytes: 0),
        );

        self::assertSame(0, $worker->drain());
        self::assertSame([], $handled);
    }

    #[Test]
    public function heartbeatBeatsOnceForEachLoopCycle(): void
    {
        $transport = new InMemoryTransport();
        $transport->send(new Envelope(new RecordCommand('a')));
        $transport->send(new Envelope(new RecordCommand('b')));

        $handled = [];
        $heartbeat = new RecordingHeartbeat();

        $worker = new CommandWorker($transport, $this->recordingBus($handled), heartbeat: $heartbeat);
        $worker->drain();

        // 2 messages + the final "queue is now empty" check.
        self::assertCount(3, $heartbeat->beats);
        self::assertSame(['async'], $heartbeat->beats[0]['lanes']);
        self::assertNotSame('', $heartbeat->beats[0]['workerName']);
    }

    #[Test]
    public function drainsMultipleTransportsAndReportsAllOfThemOnEveryBeat(): void
    {
        $async = new InMemoryTransport();
        $async->send(new Envelope(new RecordCommand('async-one')));

        $reports = new InMemoryTransport();
        $reports->send(new Envelope(new RecordCommand('reports-one')));

        $locator = new TransportLocator(['reports' => $reports]);
        $handled = [];
        $heartbeat = new RecordingHeartbeat();

        $worker = new CommandWorker(
            $async,
            $this->recordingBus($handled),
            heartbeat: $heartbeat,
            lanes: $locator,
            additionalLaneNames: ['reports'],
        );

        self::assertSame(2, $worker->drain());
        self::assertSame(['async-one', 'reports-one'], $handled);

        foreach ($heartbeat->beats as $beat) {
            self::assertSame(['async', 'reports'], $beat['lanes']);
        }
    }

    #[Test]
    public function additionalLaneNameWithoutATransportLocatorThrowsAtConstruction(): void
    {
        $handled = [];
        $this->expectException(InvalidArgumentException::class);

        new CommandWorker(
            new InMemoryTransport(),
            $this->recordingBus($handled),
            additionalLaneNames: ['reports'],
        );
    }

    #[Test]
    public function additionalLaneNameResolvingToSomethingOtherThanATransportThrowsAtConstruction(): void
    {
        $locator = new class implements ContainerInterface {
            public function get(string $id): stdClass
            {
                return new stdClass();
            }

            public function has(string $id): bool
            {
                return true;
            }
        };
        $handled = [];

        $this->expectException(InvalidArgumentException::class);

        new CommandWorker(
            new InMemoryTransport(),
            $this->recordingBus($handled),
            lanes: $locator,
            additionalLaneNames: ['reports'],
        );
    }

    #[Test]
    public function sigtermStopsAfterTheInFlightMessageAndStaysStoppedOnTheNextDrainCall(): void
    {
        if (!extension_loaded('pcntl') || !extension_loaded('posix')) {
            self::markTestSkipped('Requires the pcntl and posix extensions.');
        }

        $transport = new InMemoryTransport();
        $transport->send(new Envelope(new RecordCommand('first')));
        $transport->send(new Envelope(new RecordCommand('second')));

        $handled = [];

        $worker = new CommandWorker($transport, $this->bus(function (object $message) use (&$handled): void {
            $handled[] = $message->value;

            if ($message->value === 'first') {
                posix_kill(posix_getpid(), SIGTERM);
            }
        }));

        $firstDrain = $worker->drain();

        self::assertSame(1, $firstDrain);
        self::assertSame(['first'], $handled, 'stopped before starting the second message');
        self::assertTrue($worker->isStopRequested());
        self::assertCount(1, iterator_to_array($transport->get()), 'the second message was never touched');

        $secondDrain = $worker->drain();

        self::assertSame(0, $secondDrain, 'stays stopped: does not resume on the next call');
        self::assertSame(['first'], $handled);
    }

    /**
     * @param list<string> $handled
     */
    private function recordingBus(array &$handled): MessageBusInterface
    {
        return $this->bus(function (object $message) use (&$handled): void {
            $handled[] = $message->value;
        });
    }

    private function throwingBus(Throwable $exception): MessageBusInterface
    {
        return $this->bus(function () use ($exception): never {
            throw $exception;
        });
    }

    private function bus(Closure $onDispatch): MessageBusInterface
    {
        return new class($onDispatch) implements MessageBusInterface {
            public function __construct(private readonly Closure $onDispatch) {}

            public function dispatch(object $message, array $stamps = []): Envelope
            {
                // Mirror Envelope::wrap()'s semantics (what the real MessageBus
                // relies on): CommandWorker::processEnvelope() dispatches an
                // Envelope, not a bare message.
                $envelope = $message instanceof Envelope ? $message : new Envelope($message);

                ($this->onDispatch)($envelope->getMessage());

                return $envelope;
            }
        };
    }
}
