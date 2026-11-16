<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Bus\Command;

use ArrayIterator;
use InvalidArgumentException;
use Iterator;
use IteratorAggregate;
use Middag\Framework\Bus\Contract\MessageBusInterface;
use Middag\Framework\Bus\Contract\TransportInterface;
use Middag\Framework\Bus\Retry\AttemptStamp;
use Middag\Framework\Bus\Retry\AttemptStoreInterface;
use Middag\Framework\Bus\Retry\RetryPolicyInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\StampInterface;
use Throwable;

/**
 * Drains queued messages from one or more transports ("transports") and
 * re-dispatches each through the {@see MessageBus} carrying a
 * {@see ReceivedStamp} — so the send middleware skips re-queueing and the
 * handle middleware runs the handler. Async execution therefore reuses the
 * exact same bus and handler resolution as sync, no duplication.
 *
 * Call drain() from a long-lived process (systemd/supervisor) or a periodic
 * OS/host cron invocation; symfony/console (suggested) wraps it as a
 * `messenger:consume`-style CLI worker. Each call makes exactly one pass over
 * every transport — draining it until empty, a stop condition trips, or a
 * decode failure forces that transport to stop early — then returns. A
 * long-lived caller simply calls drain() again in its own loop; that is also
 * what makes drain() safe to call from a one-shot cron tick.
 *
 * Retry bookkeeping (core#164 F4): when a received Envelope carries an
 * {@see AttemptStamp} — see that class for the transport-side contract — and
 * both a {@see RetryPolicyInterface} and an {@see AttemptStoreInterface} are
 * configured, a handler failure is classified via the policy and recorded on
 * the row (`recordFailure()`/`markDead()`), never on envelope stamps. Without
 * the stamp, or without both collaborators, there is no row to update: the
 * worker still never lets the failure escape the loop, it just logs and
 * rejects. This is a strict widening of the original behavior — the
 * mandatory two-argument and three-argument constructor shapes keep
 * constructing a plain ack/reject/log worker with no retry bookkeeping.
 *
 * A {@see MessageDecodingFailedException} — an unreadable payload — is
 * handled wherever it actually surfaces, and is never retried: from the
 * handler (via {@see MessageBusInterface::dispatch()}); as the message of a
 * received Envelope, the form a symfony/messenger 8.1 receiver yields for an
 * unreadable row (never dispatched); in both cases sent straight to
 * dead-letter when a store+id are available, then rejected. It also surfaces
 * from the transport's own `get()`: from the call itself, when a receiver
 * decodes eagerly and rethrows (symfony/messenger 7.4 Doctrine and Redis),
 * and from its iterator, which can throw *while advancing to the next item*
 * rather than only from the handler. A failure from the get() call means the
 * receiver already rejected that row: the worker counts it in drain()'s
 * result and calls get() once more on that transport within the same pass,
 * so one poison row neither hides the readable rows behind it nor makes a
 * busy transport read as empty.
 * Iterating a `foreach` gives no chance to catch that: the exception surfaces
 * between loop bodies, not inside one. `drain()` therefore walks each transport's
 * iterator by hand (`valid()`/`current()`/`next()`) so a poison message can
 * be caught, logged, counted in drain()'s result, and the transport cleanly
 * stopped for this pass — without an envelope to reject (decoding failed before one could be produced), that is
 * the best this worker can do; the row itself staying claimed-but-stuck is a
 * transport/store concern (typically a claim timeout), not the worker's.
 *
 * @api
 */
final class CommandWorker
{
    /**
     * get() calls per transport and pass: the first one, plus one retry after
     * a fetch-time decode failure.
     */
    private const int FETCH_ATTEMPTS_PER_PASS = 2;

    /** @var array<string, TransportInterface> transport name => transport, insertion order */
    private readonly array $transports;

    private readonly WorkerLimits $limits;

    private readonly string $name;

    private bool $stopRequested = false;

    private bool $signalHandlersInstalled = false;

    /**
     * @param string                  $transportName            transport name for $transport (matches the alias
     *                                                          routed messages are sent under, e.g. via
     *                                                          {@see TransportLocator})
     * @param LoggerInterface         $logger                   PSR-3 sink for failures and stop reasons; optional
     * @param null|WorkerLimits       $limits                   stop conditions; null means unlimited
     * @param null|ContainerInterface $transportLocator         resolves $additionalTransportNames to their
     *                                                          {@see TransportInterface} (e.g. a
     *                                                          {@see TransportLocator}); required only when
     *                                                          $additionalTransportNames is non-empty
     * @param list<string>            $additionalTransportNames extra transports to drain alongside $transportName,
     *                                                          each resolved through $transportLocator; drain() cycles all
     *                                                          transports, $transportName first
     * @param null|string             $name                     this worker's identity for {@see WorkerHeartbeatInterface};
     *                                                          defaults to a host+pid derived value
     */
    public function __construct(
        TransportInterface $transport,
        private readonly MessageBusInterface $bus,
        string $transportName = 'async',
        private readonly ?RetryPolicyInterface $retryPolicy = null,
        private readonly ?AttemptStoreInterface $attemptStore = null,
        private readonly ?WorkerHeartbeatInterface $heartbeat = null,
        private readonly LoggerInterface $logger = new NullLogger(),
        ?WorkerLimits $limits = null,
        ?ContainerInterface $transportLocator = null,
        array $additionalTransportNames = [],
        ?string $name = null,
    ) {
        $this->limits = $limits ?? WorkerLimits::unlimited();
        $this->name = $name ?? $this->defaultWorkerName();
        $this->transports = $this->resolveTransports($transportName, $transport, $transportLocator, $additionalTransportNames);
    }

    /**
     * Drain every configured transport once: process each transport's currently
     * available messages until it is exhausted, a stop condition trips, or a
     * decode failure forces that transport to stop early for this pass. When
     * get() itself throws a decode failure, the transport has already
     * rejected that row: it counts as processed, and get() is called once more
     * on that transport (unless a stop condition trips first); a second
     * failure stops that transport for this pass.
     *
     * Once a SIGTERM/SIGINT has been observed, {@see self::isStopRequested()}
     * stays true for the rest of this worker's lifetime — drain() does NOT
     * reset it on the next call, so a supervising loop that keeps calling
     * drain() after a stop request reliably gets 0 back instead of resuming
     * work a shutdown signal already asked it to stop.
     *
     * @return int number of messages this call processed (handled, failed, or
     *             discarded on a decode failure), across every transport —
     *             not just the ones successfully acked. A decode failure
     *             counts wherever it surfaces (from get(), while iterating or
     *             while advancing an iterator): a queue that held only an
     *             unreadable message is never reported as 0, which a
     *             supervising loop would read as an empty queue. A decode
     *             failure met while advancing past the last message a limit
     *             allows still counts, so the result can exceed the message
     *             limit by one discarded message; no extra message is handled.
     */
    public function drain(): int
    {
        $this->installSignalHandlers();

        $startedAt = microtime(true);
        $processed = 0;

        foreach ($this->transports as $transportName => $transport) {
            if ($this->shouldStop($processed, $startedAt)) {
                break;
            }

            $processed += $this->drainTransport($transportName, $transport, $processed, $startedAt);
        }

        return $processed;
    }

    /**
     * True once a SIGTERM/SIGINT has been observed (see the pcntl caveat on
     * {@see self::drain()}). A host loop wrapping repeated drain() calls
     * should check this and stop calling drain() again once it flips true.
     */
    public function isStopRequested(): bool
    {
        return $this->stopRequested;
    }

    /**
     * @return int messages processed on this transport during this pass,
     *             counting each row the transport rejected while fetching
     */
    private function drainTransport(string $transportName, TransportInterface $transport, int $processedSoFar, float $startedAt): int
    {
        $rejected = 0;

        for ($attempt = 1; $attempt <= self::FETCH_ATTEMPTS_PER_PASS; ++$attempt) {
            if ($attempt > 1 && $this->shouldStop($processedSoFar + $rejected, $startedAt)) {
                break;
            }

            // A receiver that decodes eagerly (symfony/messenger 7.4 Doctrine
            // and Redis) rejects the unreadable row and rethrows from get()
            // itself, before any iterator exists. That row was consumed, so
            // it counts, and the next get() can still find readable rows.
            try {
                $iterator = self::toIterator($transport->get());
            } catch (MessageDecodingFailedException $messageDecodingFailedException) {
                $this->logger->error(
                    'Discarding a queued message: the transport could not decode it while fetching.',
                    $this->decodingFailureContext($transportName, $messageDecodingFailedException),
                );
                ++$rejected;

                continue;
            }

            return $rejected + $this->drainIterator($transportName, $transport, $iterator, $processedSoFar + $rejected, $startedAt);
        }

        return $rejected;
    }

    /**
     * @param Iterator<int, Envelope> $iterator
     *
     * @return int messages processed from this iterator, counting a decode
     *             failure that ends the iteration
     */
    private function drainIterator(string $transportName, TransportInterface $transport, Iterator $iterator, int $processedSoFar, float $startedAt): int
    {
        $processed = 0;

        while (true) {
            $this->heartbeat?->beat($this->name, array_keys($this->transports));
            $this->dispatchSignals();

            if ($this->shouldStop($processedSoFar + $processed, $startedAt)) {
                break;
            }

            try {
                if (!$iterator->valid()) {
                    break;
                }

                $envelope = $iterator->current();
            } catch (MessageDecodingFailedException $e) {
                $this->logger->error(
                    'Discarding a queued message: the transport could not decode it while iterating.',
                    $this->decodingFailureContext($transportName, $e),
                );
                ++$processed;

                break;
            }

            $this->processEnvelope($transportName, $transport, $envelope);
            ++$processed;

            try {
                $iterator->next();
            } catch (MessageDecodingFailedException $e) {
                $this->logger->error(
                    'Discarding a queued message: the transport could not decode the next item while advancing.',
                    $this->decodingFailureContext($transportName, $e),
                );
                ++$processed;

                break;
            }
        }

        return $processed;
    }

    private function processEnvelope(string $transportName, TransportInterface $transport, Envelope $envelope): void
    {
        $stamp = $envelope->last(AttemptStamp::class);
        $message = $envelope->getMessage();

        // From symfony/messenger 8.1 a receiver yields an unreadable row as an
        // Envelope whose message is the decoding failure itself. It is never a
        // command: dispatching it would treat it as an ordinary handler failure.
        if ($message instanceof MessageDecodingFailedException) {
            $this->discardUndecodable($transportName, $transport, $envelope, $stamp, $message);

            return;
        }

        try {
            $this->bus->dispatch($envelope->with(new ReceivedStamp($transportName)));
        } catch (MessageDecodingFailedException $e) {
            $this->discardUndecodable($transportName, $transport, $envelope, $stamp, $e);

            return;
        } catch (Throwable $e) {
            $this->handleFailure($transportName, $transport, $envelope, $stamp, $e);

            return;
        }

        if ($this->attemptStore instanceof AttemptStoreInterface && $stamp instanceof StampInterface) {
            $this->attemptStore->recordSuccess($stamp->getId());
        }

        $transport->ack($envelope);
    }

    /**
     * An unreadable payload is never retried: the row is marked dead when there
     * is one to update, and the envelope is rejected.
     */
    private function discardUndecodable(
        string $transportName,
        TransportInterface $transport,
        Envelope $envelope,
        ?AttemptStamp $stamp,
        MessageDecodingFailedException $e,
    ): void {
        $this->logger->error('Discarding a message that failed to decode.', $this->decodingFailureContext($transportName, $e));

        if ($this->attemptStore instanceof AttemptStoreInterface && $stamp instanceof StampInterface) {
            $this->attemptStore->markDead($stamp->getId(), $e);
        }

        $transport->reject($envelope);
    }

    /**
     * Log context for a decode failure. From symfony/messenger 8.1 the
     * exception carries the raw queued payload in its public encodedEnvelope,
     * and a log sink that dumps the exception would write that payload to the
     * log. The context holds a copy with the message, the code and the
     * previous exception, under the original class name, never the payload.
     *
     * @return array{transport: string, exception: MessageDecodingFailedException, exception_class: class-string}
     */
    private function decodingFailureContext(string $transportName, MessageDecodingFailedException $e): array
    {
        return [
            'transport' => $transportName,
            'exception' => self::withoutPayload($e),
            'exception_class' => $e::class,
        ];
    }

    /**
     * A previous exception that is itself a decode failure can carry a
     * payload too, so the chain is copied the same way.
     */
    private static function withoutPayload(MessageDecodingFailedException $e): MessageDecodingFailedException
    {
        $previous = $e->getPrevious();

        if ($previous instanceof MessageDecodingFailedException) {
            $previous = self::withoutPayload($previous);
        }

        return new MessageDecodingFailedException($e->getMessage(), (int) $e->getCode(), $previous);
    }

    private function handleFailure(string $transportName, TransportInterface $transport, Envelope $envelope, ?AttemptStamp $stamp, Throwable $e): void
    {
        $hasRetryBookkeeping = $this->retryPolicy instanceof RetryPolicyInterface && $this->attemptStore instanceof AttemptStoreInterface && $stamp instanceof AttemptStamp;

        if (!$hasRetryBookkeeping) {
            $this->logger->error('Command handler failed; no retry bookkeeping is configured for this message, rejecting.', [
                'transport' => $transportName,
                'exception' => $e,
            ]);

            $transport->reject($envelope);

            return;
        }

        /** @var RetryPolicyInterface $retryPolicy */
        $retryPolicy = $this->retryPolicy;

        /** @var AttemptStoreInterface $attemptStore */
        $attemptStore = $this->attemptStore;
        $item = $stamp->getItem();

        if ($retryPolicy->isRetryable($item, $e)) {
            $availableAt = time() + intdiv($retryPolicy->getWaitingTime($item, $e), 1000);
            $attemptStore->recordFailure($stamp->getId(), $e, $availableAt);
            $this->logger->warning('Command handler failed; scheduled for retry.', [
                'transport' => $transportName,
                'exception' => $e,
                'availableAt' => $availableAt,
            ]);
        } else {
            $attemptStore->markDead($stamp->getId(), $e);
            $this->logger->error('Command handler failed; retries exhausted, marking dead.', [
                'transport' => $transportName,
                'exception' => $e,
            ]);
        }

        $transport->reject($envelope);
    }

    private function shouldStop(int $processed, float $startedAt): bool
    {
        if ($this->stopRequested) {
            return true;
        }

        if ($this->limits->messageLimitReached($processed)) {
            $this->logger->info('Worker stopping: message limit reached.', ['processed' => $processed]);

            return true;
        }

        if ($this->limits->timeLimitReached(microtime(true) - $startedAt)) {
            $this->logger->info('Worker stopping: time limit reached.');

            return true;
        }

        if ($this->limits->memoryLimitReached(memory_get_usage(true))) {
            $this->logger->info('Worker stopping: memory limit reached.');

            return true;
        }

        return false;
    }

    /**
     * pcntl is optional: this package must keep working with the extension
     * absent (host constraint), so every touchpoint is function_exists()
     * guarded. SIGTERM/SIGINT only ever set a flag checked between messages —
     * never abort mid-message.
     */
    private function installSignalHandlers(): void
    {
        if ($this->signalHandlersInstalled || !function_exists('pcntl_signal')) {
            return;
        }

        $stop = function (): void {
            $this->stopRequested = true;
        };

        pcntl_signal(SIGTERM, $stop);
        pcntl_signal(SIGINT, $stop);

        $this->signalHandlersInstalled = true;
    }

    private function dispatchSignals(): void
    {
        if (function_exists('pcntl_signal_dispatch')) {
            pcntl_signal_dispatch();
        }
    }

    /**
     * @param iterable<Envelope> $iterable
     *
     * @return Iterator<int, Envelope>
     */
    private static function toIterator(iterable $iterable): Iterator
    {
        if (is_array($iterable)) {
            return new ArrayIterator($iterable);
        }

        if ($iterable instanceof IteratorAggregate) {
            return self::toIterator($iterable->getIterator());
        }

        // @var Iterator<int, Envelope> $iterable
        return $iterable;
    }

    /**
     * @param list<string> $additionalTransportNames
     *
     * @return array<string, TransportInterface>
     */
    private function resolveTransports(
        string $primaryName,
        TransportInterface $primaryTransport,
        ?ContainerInterface $transportLocator,
        array $additionalTransportNames,
    ): array {
        $transports = [$primaryName => $primaryTransport];

        foreach ($additionalTransportNames as $transportName) {
            if ($transportName === $primaryName) {
                continue;
            }

            if (!$transportLocator instanceof ContainerInterface) {
                throw new InvalidArgumentException(sprintf(
                    'CommandWorker cannot resolve transport "%s": no transport locator was given.',
                    $transportName,
                ));
            }

            $resolved = $transportLocator->get($transportName);

            if (!$resolved instanceof TransportInterface) {
                throw new InvalidArgumentException(sprintf(
                    'CommandWorker cannot resolve transport "%s": expected a %s, got %s.',
                    $transportName,
                    TransportInterface::class,
                    get_debug_type($resolved),
                ));
            }

            $transports[$transportName] = $resolved;
        }

        return $transports;
    }

    private function defaultWorkerName(): string
    {
        $host = gethostname();

        return sprintf('%s:%d', $host !== false ? $host : 'worker', getmypid() ?: 0);
    }
}
