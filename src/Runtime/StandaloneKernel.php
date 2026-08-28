<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Runtime;

use Middag\Framework\Bus\Command\CommandWorker;
use Middag\Framework\Bus\Command\WorkerHeartbeatInterface;
use Middag\Framework\Bus\Command\WorkerLimits;
use Middag\Framework\Bus\Contract\MessageBusInterface;
use Middag\Framework\Bus\Contract\TransportInterface;
use Middag\Framework\Bus\MessageBusFactory;
use Middag\Framework\Bus\Retry\AttemptStoreInterface;
use Middag\Framework\Bus\Retry\RetryPolicyInterface;
use Middag\Framework\Bus\Transport\DoctrineTransportFactory;
use Middag\Framework\Bus\Transport\InMemoryTransport;
use Middag\Framework\Bus\Transport\TransportLocator;
use Middag\Framework\Database\Contract\ConnectionAdapterInterface;
use Middag\Framework\Database\PdoConnectionAdapter;
use Middag\Framework\Database\Schema\SchemaBuilder;
use PDO;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Transport\Sender\SendersLocator;

/**
 * The minimum runtime a MIDDAG product needs on a plain PHP host — no Moodle,
 * no WordPress (core#164 F6 6.0, decision G-standalone level 1).
 *
 * Three things, which is the whole list a worker actually needs: a database
 * connection, a {@see MessageBusInterface}, and a {@see CommandWorker} that
 * drains it. Everything else a real product wants — repositories, an outbox,
 * lane naming, a strategy resolver — is deliberately NOT here: lane names and
 * the signal outbox are MIDDAG product knowledge and live in
 * `middag-io/core`'s `Adapter/Standalone/` (level 2), which consumes this class
 * rather than reassembling a second bus.
 *
 * This is also why the framework has, and must keep having, **no `Adapter/`
 * directory**: it is host-agnostic by definition, and that is exactly what
 * makes a host-free kernel belong here.
 *
 * ## Why the caller brings the PDO
 *
 * A kernel that opened its own connection would have to know a DSN, a
 * credential source, and a pooling policy — three host decisions. It takes an
 * open {@see PDO} instead, which also means the transport, the schema builder
 * and the application all provably share ONE connection. With the `doctrine`
 * transport the recommended order is the reverse of the obvious one: build the
 * DBAL connection first, then pass `$dbal->getNativeConnection()` here, so the
 * queue table and the domain tables are in the same database and the same
 * transaction scope.
 *
 * ## Sync is the default, and that is decision G1h
 *
 * With no transports the bus handles every message inline — Symfony's own
 * behaviour, and the only safe default for a host that may have no worker
 * process at all. A queue nobody drains is worse than a slow request. Pass
 * transports (e.g. {@see InMemoryTransport} for a test, or
 * {@see DoctrineTransportFactory} for a durable one) plus the routes that use
 * them to go async.
 *
 * @api
 */
final class StandaloneKernel
{
    private ?MessageBusInterface $bus = null;

    private ?PdoConnectionAdapter $connection = null;

    private readonly TransportLocator $transports;

    /**
     * @param PDO                               $pdo        the ONE open connection this process uses
     * @param ContainerInterface                $services   resolves command handlers and signal consumers by class
     *                                                      name; {@see ServiceMap} is enough for a small host
     * @param array<string, TransportInterface> $transports transport name => transport. Empty (the default) means
     *                                                      every message is handled synchronously.
     * @param array<class-string, list<string>> $routes     command FQCN => the transport names it is sent to.
     *                                                      A command absent from this map is handled inline even
     *                                                      when transports exist.
     * @param iterable<MiddlewareInterface>     $middleware prepended ahead of the send/handle stack
     */
    public function __construct(
        private readonly PDO $pdo,
        private readonly ContainerInterface $services = new ServiceMap(),
        array $transports = [],
        private readonly array $routes = [],
        private readonly LoggerInterface $logger = new NullLogger(),
        private readonly iterable $middleware = [],
    ) {
        $this->transports = new TransportLocator($transports);
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * The framework's connection abstraction over the same PDO — what
     * {@see SchemaBuilder} and repositories take.
     */
    public function connection(): ConnectionAdapterInterface
    {
        return $this->connection ??= new PdoConnectionAdapter($this->pdo);
    }

    public function services(): ContainerInterface
    {
        return $this->services;
    }

    public function transports(): TransportLocator
    {
        return $this->transports;
    }

    /**
     * The one bus of this process, built on first use and reused after that.
     *
     * Memoised on purpose: a second instance would mean a second senders map,
     * and a message dispatched by a handler would then be routed by different
     * rules than the one that reached the handler.
     */
    public function bus(): MessageBusInterface
    {
        return $this->bus ??= (new MessageBusFactory())->create(
            $this->services,
            $this->routes === [] ? null : new SendersLocator($this->routes, $this->transports),
            null,
            $this->middleware,
        );
    }

    /**
     * A worker draining $transportName (plus $also), through this kernel's bus.
     *
     * @param list<string> $also extra transport names drained in the same pass
     */
    public function worker(
        string $transportName,
        array $also = [],
        ?WorkerLimits $limits = null,
        ?RetryPolicyInterface $retryPolicy = null,
        ?AttemptStoreInterface $attemptStore = null,
        ?WorkerHeartbeatInterface $heartbeat = null,
    ): CommandWorker {
        return new CommandWorker(
            transport: $this->transports->get($transportName),
            bus: $this->bus(),
            transportName: $transportName,
            retryPolicy: $retryPolicy,
            attemptStore: $attemptStore,
            heartbeat: $heartbeat,
            logger: $this->logger,
            limits: $limits,
            transportLocator: $this->transports,
            additionalTransportNames: $also,
        );
    }
}
