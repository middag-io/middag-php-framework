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
use Generator;
use InvalidArgumentException;
use Middag\Framework\Bus\Command\CommandSerializer;
use Middag\Framework\Bus\Command\CommandWorker;
use Middag\Framework\Bus\Command\WorkerLimits;
use Middag\Framework\Bus\Contract\MessageBusInterface;
use Middag\Framework\Bus\Contract\TransportInterface;
use Middag\Framework\Bus\Retry\AttemptStamp;
use Middag\Framework\Bus\Transport\InMemoryTransport;
use Middag\Framework\Bus\Transport\TransportLocator;
use Middag\Framework\Tests\Bus\Command\Fixture\EagerDecodeFailureTransport;
use Middag\Framework\Tests\Bus\Command\Fixture\EncodedRowTransport;
use Middag\Framework\Tests\Bus\Command\Fixture\FixedRetryPolicy;
use Middag\Framework\Tests\Bus\Command\Fixture\PayloadCarryingDecodingFailure;
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

        self::assertSame(2, $processed, 'the good message and the poison one both count: the queue was not empty');
        self::assertSame(['first'], $handled);
        self::assertCount(1, $transport->acked);
        self::assertNotEmpty($logger->messagesAtLevel('error'));
    }

    #[Test]
    public function aQueueHoldingOnlyAnUnreadableMessageIsNeverReportedEmpty(): void
    {
        $transport = new class implements TransportInterface {
            /** @var list<string> */
            public array $rows = ['poison'];

            public function send(Envelope $envelope): Envelope
            {
                return $envelope;
            }

            public function get(): iterable
            {
                return $this->decode();
            }

            public function ack(Envelope $envelope): void {}

            public function reject(Envelope $envelope): void {}

            /**
             * @return Generator<int, Envelope>
             */
            private function decode(): Generator
            {
                foreach ($this->rows as $row) {
                    if ('poison' === $row) {
                        throw new MessageDecodingFailedException('poison payload');
                    }

                    yield new Envelope(new RecordCommand($row));
                }
            }
        };
        $logger = new RecordingLogger();
        $handled = [];

        $worker = new CommandWorker($transport, $this->recordingBus($handled), logger: $logger);

        self::assertSame(1, $worker->drain(), 'the unreadable message counts: the queue was not empty');
        self::assertSame([], $handled);
        self::assertNotEmpty($logger->messagesAtLevel('error'));
    }

    /**
     * framework#108: the DB lane claims a row, then decodes it. Before the
     * serializer wrapped its failures, an undecodable row left the worker loop
     * as a MiddagInfrastructureException and killed the process, which met the
     * same row again after the orphan timeout and died again, forever.
     */
    #[Test]
    public function aPoisonRowOnAnEncodedTransportDoesNotKillTheWorker(): void
    {
        $good = (new CommandSerializer())->encode(new Envelope(new RecordCommand('good')));
        $transport = new EncodedRowTransport([
            $good,
            ['headers' => ['type' => 'Vendor\Gone\RenamedCommand'], 'body' => '{"value":"poison"}'],
            $good,
        ]);
        $logger = new RecordingLogger();
        $handled = [];

        $worker = new CommandWorker($transport, $this->recordingBus($handled), logger: $logger);

        $processed = $worker->drain();

        self::assertSame(2, $processed, 'the good row and the claimed poison row both count');
        self::assertSame(['good'], $handled);
        self::assertNotEmpty($logger->messagesAtLevel('error'));
    }

    /**
     * framework#108: the symfony/messenger 7.4 Doctrine and Redis receivers
     * decode eagerly inside get() and rethrow after rejecting the row, so the
     * failure surfaces from the get() call itself, before any iterator exists.
     */
    #[Test]
    public function aDecodingFailureThrownByTheGetCallItselfIsCaughtAndTheNextTransportStillDrains(): void
    {
        $poisoned = new EagerDecodeFailureTransport();
        $healthy = new InMemoryTransport();
        $healthy->send(new Envelope(new RecordCommand('after')));

        $logger = new RecordingLogger();
        $handled = [];

        $worker = new CommandWorker(
            $poisoned,
            $this->recordingBus($handled),
            logger: $logger,
            transportLocator: new TransportLocator(['reports' => $healthy]),
            additionalTransportNames: ['reports'],
        );

        $processed = $worker->drain();

        self::assertSame(3, $processed, 'both rejected rows count, then the healthy transport drains');
        self::assertSame(2, $poisoned->getCalls, 'one retry within the pass, then the transport stops');
        self::assertSame(['after'], $handled);
        self::assertCount(2, $logger->messagesAtLevel('error'));
    }

    /**
     * A row rejected at fetch time was consumed by the receiver: reporting 0
     * would let a supervising loop read a busy transport as empty, and
     * stopping the transport would leave the readable rows behind it for the
     * next pass.
     */
    #[Test]
    public function aDecodingFailureFromGetCountsTheRejectedRowAndRetriesGetOnce(): void
    {
        $transport = new EagerDecodeFailureTransport(failures: 1, queued: [
            new Envelope(new RecordCommand('first')),
            new Envelope(new RecordCommand('second')),
        ]);
        $logger = new RecordingLogger();
        $handled = [];

        $worker = new CommandWorker($transport, $this->recordingBus($handled), logger: $logger);

        $processed = $worker->drain();

        self::assertSame(3, $processed, 'the rejected row plus the two read on the retry');
        self::assertSame(2, $transport->getCalls);
        self::assertSame(['first', 'second'], $handled);
        self::assertCount(2, $transport->acked);
        self::assertCount(1, $logger->messagesAtLevel('error'));
    }

    #[Test]
    public function theRejectedRowCountsTowardTheMessageLimitBeforeAnyRetry(): void
    {
        $transport = new EagerDecodeFailureTransport(failures: 1, queued: [new Envelope(new RecordCommand('first'))]);
        $handled = [];

        $worker = new CommandWorker($transport, $this->recordingBus($handled), limits: new WorkerLimits(messageLimit: 1));

        self::assertSame(1, $worker->drain());
        self::assertSame(1, $transport->getCalls, 'the limit tripped, so get() is not called again');
        self::assertSame([], $handled);
    }

    /**
     * framework#108: from symfony/messenger 8.1 the same receivers no longer
     * throw; they yield an Envelope whose message is the
     * MessageDecodingFailedException itself. Dispatching it would run it
     * through the handler resolution as an ordinary message and retry it.
     */
    #[Test]
    public function aWrappedDecodingFailureIsNeverDispatchedAndItsRowIsMarkedDead(): void
    {
        $transport = new InMemoryTransport();
        $item = new FakeAttemptable(attempts: 1, maxAttempts: 3);
        $transport->send((new Envelope(new MessageDecodingFailedException('poison row')))->with(new AttemptStamp(11, $item)));
        $transport->send(new Envelope(new RecordCommand('after')));

        $store = new InMemoryAttemptStore();
        $logger = new RecordingLogger();
        $handled = [];

        $worker = new CommandWorker(
            $transport,
            $this->recordingBus($handled),
            retryPolicy: new FixedRetryPolicy(retryable: true),
            attemptStore: $store,
            logger: $logger,
        );

        $processed = $worker->drain();

        self::assertSame(2, $processed);
        self::assertSame(['after'], $handled, 'the wrapped failure never reaches the bus');
        self::assertSame([11], $store->dead);
        self::assertSame([], $store->failures, 'never goes through the retry policy');
        self::assertSame([], iterator_to_array($transport->get()), 'rejected, not left in the queue');
        self::assertNotEmpty($logger->messagesAtLevel('error'));
    }

    #[Test]
    public function aWrappedDecodingFailureIsLoggedWithoutItsPayload(): void
    {
        $failure = $this->payloadCarryingFailure();
        $transport = new InMemoryTransport();
        $transport->send(new Envelope($failure));

        $logger = new RecordingLogger();
        $handled = [];

        $worker = new CommandWorker($transport, $this->recordingBus($handled), logger: $logger);

        self::assertSame(1, $worker->drain());
        $this->assertDecodingFailureLoggedWithoutPayload($logger, $failure);
    }

    #[Test]
    public function aDecodingFailureFromGetIsLoggedWithoutItsPayload(): void
    {
        $failure = $this->payloadCarryingFailure();
        $transport = new EagerDecodeFailureTransport(failures: 1, failure: $failure);
        $logger = new RecordingLogger();
        $handled = [];

        $worker = new CommandWorker($transport, $this->recordingBus($handled), logger: $logger);

        self::assertSame(1, $worker->drain());
        $this->assertDecodingFailureLoggedWithoutPayload($logger, $failure);
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
        self::assertSame(['async'], $heartbeat->beats[0]['transports']);
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
            transportLocator: $locator,
            additionalTransportNames: ['reports'],
        );

        self::assertSame(2, $worker->drain());
        self::assertSame(['async-one', 'reports-one'], $handled);

        foreach ($heartbeat->beats as $beat) {
            self::assertSame(['async', 'reports'], $beat['transports']);
        }
    }

    #[Test]
    public function additionalTransportNameWithoutATransportLocatorThrowsAtConstruction(): void
    {
        $handled = [];
        $this->expectException(InvalidArgumentException::class);

        new CommandWorker(
            new InMemoryTransport(),
            $this->recordingBus($handled),
            additionalTransportNames: ['reports'],
        );
    }

    #[Test]
    public function additionalTransportNameResolvingToSomethingOtherThanATransportThrowsAtConstruction(): void
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
            transportLocator: $locator,
            additionalTransportNames: ['reports'],
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
    private function payloadCarryingFailure(): PayloadCarryingDecodingFailure
    {
        return new PayloadCarryingDecodingFailure(
            'poison row',
            ['body' => 'outer-secret-payload'],
            new PayloadCarryingDecodingFailure('inner decode failure', ['body' => 'inner-secret-payload']),
        );
    }

    /**
     * Walks every Throwable in every logged context, previous chain included:
     * none is a payload-carrying instance or holds a non-empty encodedEnvelope
     * (the symfony/messenger 8.1 property), and no plain context value holds
     * the payload. The message, the original class and the previous message
     * stay in the log.
     */
    private function assertDecodingFailureLoggedWithoutPayload(RecordingLogger $logger, PayloadCarryingDecodingFailure $failure): void
    {
        $errors = array_values(array_filter($logger->records, static fn (array $record): bool => $record['level'] === 'error'));
        self::assertCount(1, $errors);

        $context = $errors[0]['context'];
        self::assertSame(PayloadCarryingDecodingFailure::class, $context['exception_class'] ?? null);

        $logged = $context['exception'] ?? null;
        self::assertInstanceOf(MessageDecodingFailedException::class, $logged);
        self::assertSame($failure->getMessage(), $logged->getMessage());
        self::assertSame('inner decode failure', $logged->getPrevious()?->getMessage());

        foreach ($context as $value) {
            if (!$value instanceof Throwable) {
                self::assertStringNotContainsString('secret-payload', var_export($value, true));

                continue;
            }

            for ($throwable = $value; $throwable instanceof Throwable; $throwable = $throwable->getPrevious()) {
                self::assertNotInstanceOf(PayloadCarryingDecodingFailure::class, $throwable);
                self::assertSame([], get_object_vars($throwable)['encodedEnvelope'] ?? []);
            }
        }
    }

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
        return new readonly class($onDispatch) implements MessageBusInterface {
            public function __construct(private Closure $onDispatch) {}

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
