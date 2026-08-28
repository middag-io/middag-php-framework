<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Runtime;

use Middag\Framework\Bus\Command\CommandWorker;
use Middag\Framework\Bus\Transport\InMemoryTransport;
use Middag\Framework\Database\Contract\ConnectionAdapterInterface;
use Middag\Framework\Runtime\ServiceMap;
use Middag\Framework\Runtime\StandaloneKernel;
use Middag\Framework\Tests\Bus\Fixture\RecordCommand;
use Middag\Framework\Tests\Bus\Fixture\RecordCommandHandler;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The host-free runtime of core#164 F6 6.0 (level 1).
 *
 * A real PDO (SQLite in memory), a real bus and a real worker — nothing here is
 * a double, because the claim under test is "a MIDDAG product boots and drains
 * work on a plain PHP host", and a mock cannot be evidence of that.
 *
 * @internal
 */
#[CoversClass(StandaloneKernel::class)]
final class StandaloneKernelTest extends TestCase
{
    protected function setUp(): void
    {
        RecordCommandHandler::reset();
    }

    /**
     * Decision G1h: with no transports every message runs inline. A queue
     * nobody drains is worse than a slow request, so this is the only safe
     * default for a host that may have no worker process at all.
     */
    #[Test]
    public function withNoTransportsEveryMessageIsHandledInline(): void
    {
        $kernel = $this->kernel();

        $kernel->bus()->dispatch(new RecordCommand('inline'));

        self::assertSame(['inline'], RecordCommandHandler::$handled);
    }

    #[Test]
    public function aRoutedCommandWaitsForTheWorker(): void
    {
        $transport = new InMemoryTransport();
        $kernel = $this->kernel(
            transports: ['async' => $transport],
            routes: [RecordCommand::class => ['async']],
        );

        $kernel->bus()->dispatch(new RecordCommand('queued'));

        self::assertSame([], RecordCommandHandler::$handled, 'A routed command must not run at dispatch time.');

        $processed = $kernel->worker('async')->drain();

        self::assertSame(1, $processed);
        self::assertSame(['queued'], RecordCommandHandler::$handled);
        self::assertSame([], $transport->get(), 'The worker must ack what it handled.');
    }

    /**
     * A command absent from the routes runs inline even while transports exist —
     * routing is per message, not per kernel.
     */
    #[Test]
    public function anUnroutedCommandStillRunsInlineWhenTransportsExist(): void
    {
        $kernel = $this->kernel(
            transports: ['async' => new InMemoryTransport()],
            routes: ['Some\Other\Command' => ['async']],
        );

        $kernel->bus()->dispatch(new RecordCommand('still-inline'));

        self::assertSame(['still-inline'], RecordCommandHandler::$handled);
    }

    /**
     * One bus per process. A second instance would carry a second senders map,
     * and a command dispatched from inside a handler would then be routed by
     * different rules than the one that reached it.
     */
    #[Test]
    public function theBusIsBuiltOnceAndReused(): void
    {
        $kernel = $this->kernel();

        self::assertSame($kernel->bus(), $kernel->bus());
    }

    #[Test]
    public function theConnectionIsTheOnePdoThatWasHandedIn(): void
    {
        $pdo = $this->pdo();
        $kernel = new StandaloneKernel($pdo);

        self::assertSame($pdo, $kernel->pdo());
        self::assertInstanceOf(ConnectionAdapterInterface::class, $kernel->connection());
        self::assertSame($kernel->connection(), $kernel->connection(), 'The adapter must be memoised, not rebuilt.');

        // Proof it is really the same session: a temp table created through the
        // adapter is visible on the PDO the caller still holds.
        $kernel->connection()->execute('CREATE TABLE probe (id INTEGER PRIMARY KEY)');

        self::assertNotFalse($pdo->query('SELECT COUNT(*) FROM probe'));
    }

    #[Test]
    public function theWorkerDrainsEveryNamedTransportInOnePass(): void
    {
        $kernel = $this->kernel(
            transports: ['async' => new InMemoryTransport(), 'low' => new InMemoryTransport()],
            routes: [RecordCommand::class => ['async', 'low']],
        );

        $kernel->bus()->dispatch(new RecordCommand('both'));

        $worker = $kernel->worker('async', ['low']);

        self::assertInstanceOf(CommandWorker::class, $worker);
        self::assertSame(2, $worker->drain(), 'A command routed to two transports is one message on each.');
        self::assertSame(['both', 'both'], RecordCommandHandler::$handled);
    }

    /**
     * @param array<string, InMemoryTransport>  $transports
     * @param array<class-string, list<string>> $routes
     */
    private function kernel(array $transports = [], array $routes = []): StandaloneKernel
    {
        return new StandaloneKernel(
            pdo: $this->pdo(),
            // Convention resolution: {Command}Handler.
            services: new ServiceMap([
                RecordCommand::class . 'Handler' => static fn (): RecordCommandHandler => new RecordCommandHandler(),
            ]),
            transports: $transports,
            routes: $routes,
        );
    }

    private function pdo(): PDO
    {
        return new PDO('sqlite::memory:');
    }
}
