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
 * Drains queued messages from one or more transports ("lanes") and
 * re-dispatches each through the {@see MessageBus} carrying a
 * {@see ReceivedStamp} — so the send middleware skips re-queueing and the
 * handle middleware runs the handler. Async execution therefore reuses the
 * exact same bus and handler resolution as sync, no duplication.
 *
 * Call drain() from a long-lived process (systemd/supervisor) or a periodic
 * OS/host cron invocation; symfony/console (suggested) wraps it as a
 * `messenger:consume`-style CLI worker. Each call makes exactly one pass over
 * every lane's transport — draining it until empty, a stop condition trips,
 * or a decode failure forces that lane to stop early — then returns. A
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
 * handled wherever it actually surfaces: from the handler (via
 * {@see MessageBusInterface::dispatch()}, where it is always treated as
 * non-retryable and sent straight to dead-letter when a store+id are
 * available) and from the transport's own `get()` iterator, which can throw
 * *while advancing to the next item* rather than only from the handler.
 * Iterating a `foreach` gives no chance to catch that: the exception surfaces
 * between loop bodies, not inside one. `drain()` therefore walks each lane's
 * iterator by hand (`valid()`/`current()`/`next()`) so a poison message can
 * be caught, logged, and the lane cleanly stopped for this pass — without an
 * envelope to reject (decoding failed before one could be produced), that is
 * the best this worker can do; the row itself staying claimed-but-stuck is a
 * transport/store concern (typically a claim timeout), not the worker's.
 *
 * @api
 */
final class CommandWorker
{
    /** @var array<string, TransportInterface> lane name => transport, insertion order */
    private readonly array $laneTransports;

    private readonly WorkerLimits $limits;

    private readonly string $name;

    private bool $stopRequested = false;

    private bool $signalHandlersInstalled = false;

    /**
     * @param string                  $transportName       lane name for $transport (matches the alias
     *                                                     routed messages are sent under, e.g. via
     *                                                     {@see TransportLocator})
     * @param LoggerInterface         $logger              PSR-3 sink for failures and stop reasons; optional
     * @param null|WorkerLimits       $limits              stop conditions; null means unlimited
     * @param null|ContainerInterface $lanes               resolves $additionalLaneNames to their
     *                                                     {@see TransportInterface} (e.g. a
     *                                                     {@see TransportLocator}); required only when
     *                                                     $additionalLaneNames is non-empty
     * @param list<string>            $additionalLaneNames extra lanes to drain alongside $transportName,
     *                                                     each resolved through $lanes; drain() cycles all
     *                                                     lanes, $transportName first
     * @param null|string             $name                this worker's identity for {@see WorkerHeartbeatInterface};
     *                                                     defaults to a host+pid derived value
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
        ?ContainerInterface $lanes = null,
        array $additionalLaneNames = [],
        ?string $name = null,
    ) {
        $this->limits = $limits ?? WorkerLimits::unlimited();
        $this->name = $name ?? $this->defaultWorkerName();
        $this->laneTransports = $this->resolveLanes($transportName, $transport, $lanes, $additionalLaneNames);
    }

    /**
     * Drain every configured lane once: process each lane's currently
     * available messages until it is exhausted, a stop condition trips, or a
     * decode failure forces that lane to stop early for this pass.
     *
     * Once a SIGTERM/SIGINT has been observed, {@see self::isStopRequested()}
     * stays true for the rest of this worker's lifetime — drain() does NOT
     * reset it on the next call, so a supervising loop that keeps calling
     * drain() after a stop request reliably gets 0 back instead of resuming
     * work a shutdown signal already asked it to stop.
     *
     * @return int number of messages this call processed (handled or failed),
     *             across every lane — not just the ones successfully acked
     */
    public function drain(): int
    {
        $this->installSignalHandlers();

        $startedAt = microtime(true);
        $processed = 0;

        foreach ($this->laneTransports as $laneName => $transport) {
            if ($this->shouldStop($processed, $startedAt)) {
                break;
            }

            $processed += $this->drainLane($laneName, $transport, $processed, $startedAt);
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
     * @return int messages processed on this lane during this pass
     */
    private function drainLane(string $laneName, TransportInterface $transport, int $processedSoFar, float $startedAt): int
    {
        $iterator = self::toIterator($transport->get());
        $processed = 0;

        while (true) {
            $this->heartbeat?->beat($this->name, array_keys($this->laneTransports));
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
                $this->logger->error('Discarding a queued message: the transport could not decode it while iterating.', [
                    'lane' => $laneName,
                    'exception' => $e,
                ]);

                break;
            }

            $this->processEnvelope($laneName, $transport, $envelope);
            ++$processed;

            try {
                $iterator->next();
            } catch (MessageDecodingFailedException $e) {
                $this->logger->error('Discarding a queued message: the transport could not decode the next item while advancing.', [
                    'lane' => $laneName,
                    'exception' => $e,
                ]);

                break;
            }
        }

        return $processed;
    }

    private function processEnvelope(string $laneName, TransportInterface $transport, Envelope $envelope): void
    {
        $stamp = $envelope->last(AttemptStamp::class);

        try {
            $this->bus->dispatch($envelope->with(new ReceivedStamp($laneName)));
        } catch (MessageDecodingFailedException $e) {
            $this->logger->error('Discarding a message that failed to decode.', ['lane' => $laneName, 'exception' => $e]);

            if ($this->attemptStore instanceof AttemptStoreInterface && $stamp instanceof StampInterface) {
                $this->attemptStore->markDead($stamp->getId(), $e);
            }

            $transport->reject($envelope);

            return;
        } catch (Throwable $e) {
            $this->handleFailure($laneName, $transport, $envelope, $stamp, $e);

            return;
        }

        if ($this->attemptStore instanceof AttemptStoreInterface && $stamp instanceof StampInterface) {
            $this->attemptStore->recordSuccess($stamp->getId());
        }

        $transport->ack($envelope);
    }

    private function handleFailure(string $laneName, TransportInterface $transport, Envelope $envelope, ?AttemptStamp $stamp, Throwable $e): void
    {
        $hasRetryBookkeeping = $this->retryPolicy instanceof RetryPolicyInterface && $this->attemptStore instanceof AttemptStoreInterface && $stamp instanceof AttemptStamp;

        if (!$hasRetryBookkeeping) {
            $this->logger->error('Command handler failed; no retry bookkeeping is configured for this message, rejecting.', [
                'lane' => $laneName,
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
                'lane' => $laneName,
                'exception' => $e,
                'availableAt' => $availableAt,
            ]);
        } else {
            $attemptStore->markDead($stamp->getId(), $e);
            $this->logger->error('Command handler failed; retries exhausted, marking dead.', [
                'lane' => $laneName,
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
     * @param list<string> $additionalLaneNames
     *
     * @return array<string, TransportInterface>
     */
    private function resolveLanes(
        string $primaryName,
        TransportInterface $primaryTransport,
        ?ContainerInterface $lanes,
        array $additionalLaneNames,
    ): array {
        $laneTransports = [$primaryName => $primaryTransport];

        foreach ($additionalLaneNames as $laneName) {
            if ($laneName === $primaryName) {
                continue;
            }

            if (!$lanes instanceof ContainerInterface) {
                throw new InvalidArgumentException(sprintf(
                    'CommandWorker cannot resolve lane "%s": no transport locator was given.',
                    $laneName,
                ));
            }

            $resolved = $lanes->get($laneName);

            if (!$resolved instanceof TransportInterface) {
                throw new InvalidArgumentException(sprintf(
                    'CommandWorker cannot resolve lane "%s": expected a %s, got %s.',
                    $laneName,
                    TransportInterface::class,
                    get_debug_type($resolved),
                ));
            }

            $laneTransports[$laneName] = $resolved;
        }

        return $laneTransports;
    }

    private function defaultWorkerName(): string
    {
        $host = gethostname();

        return sprintf('%s:%d', $host !== false ? $host : 'worker', getmypid() ?: 0);
    }
}
