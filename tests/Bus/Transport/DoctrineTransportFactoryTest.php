<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Bus\Transport;

use Doctrine\DBAL\Connection as DbalConnection;
use Doctrine\DBAL\DriverManager;
use InvalidArgumentException;
use Middag\Framework\Bus\Command\CommandSerializer;
use Middag\Framework\Bus\Transport\DoctrineTransportFactory;
use Middag\Framework\Bus\Transport\MiddagDoctrineTransport;
use Middag\Framework\Runtime\ServiceMap;
use Middag\Framework\Runtime\StandaloneKernel;
use Middag\Framework\Tests\Bus\Fixture\RecordCommand;
use Middag\Framework\Tests\Bus\Fixture\RecordCommandHandler;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use Symfony\Component\Messenger\Transport\SetupableTransportInterface;

/**
 * The `doctrine` transport of core#164 F6 6.1, over a REAL DBAL connection.
 *
 * SQLite in memory, the real `symfony/doctrine-messenger` bridge, the real
 * `messenger_messages` table it creates for itself. Level 1's acceptance
 * criterion is "a standalone host enqueues and consumes a message through the
 * `CommandWorker` with the `doctrine` transport over SQLite", and
 * {@see self::aStandaloneKernelEnqueuesAndDrainsThroughDoctrine()} is that
 * sentence executed — without `middag-io/core` anywhere in sight, which is the
 * other half of the criterion.
 *
 * @internal
 */
#[CoversClass(DoctrineTransportFactory::class)]
#[CoversClass(MiddagDoctrineTransport::class)]
final class DoctrineTransportFactoryTest extends TestCase
{
    private ?DbalConnection $dbal = null;

    protected function setUp(): void
    {
        RecordCommandHandler::reset();

        if (!DoctrineTransportFactory::isAvailable()) {
            self::markTestSkipped((string) DoctrineTransportFactory::missingReason());
        }
    }

    protected function tearDown(): void
    {
        $this->dbal?->close();
        $this->dbal = null;
    }

    #[Test]
    public function itSupportsOnlyItsOwnAlias(): void
    {
        $factory = new DoctrineTransportFactory();

        self::assertTrue($factory->supports(DoctrineTransportFactory::ALIAS));
        self::assertFalse($factory->supports('middag-db'));
        self::assertFalse($factory->supports('redis'));
    }

    /**
     * The connection is an input, never something this factory opens. That is
     * what keeps "Doctrine opens a second connection" from being true of the
     * standalone path too.
     */
    #[Test]
    public function itRefusesToGuessAConnection(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('needs a ' . DbalConnection::class);

        (new DoctrineTransportFactory())->create(DoctrineTransportFactory::ALIAS, []);
    }

    /**
     * `auto_setup` is on because a standalone install has no guaranteed
     * migration tool (F6 6.3): the table has to appear on first use.
     */
    #[Test]
    public function autoSetupCreatesTheQueueTableOnFirstUse(): void
    {
        $dbal = $this->connection();

        self::assertSame([], $this->tables($dbal), 'The fixture database must start empty.');

        $transport = $this->transport($dbal);
        $transport->send(new Envelope(new RecordCommand('x')));

        self::assertContains(DoctrineTransportFactory::DEFAULT_TABLE, $this->tables($dbal));
    }

    /**
     * Level 1's acceptance criterion, executed: enqueue and consume through the
     * `CommandWorker`, `doctrine` over SQLite, no `middag-io/core`.
     */
    #[Test]
    public function aStandaloneKernelEnqueuesAndDrainsThroughDoctrine(): void
    {
        $dbal = $this->connection();

        // Order matters: DBAL first, then its native PDO. One connection, one
        // database — the queue table and the domain tables in the same session.
        $pdo = $dbal->getNativeConnection();
        self::assertInstanceOf(PDO::class, $pdo);

        $kernel = new StandaloneKernel(
            pdo: $pdo,
            services: new ServiceMap([
                RecordCommand::class . 'Handler' => static fn (): RecordCommandHandler => new RecordCommandHandler(),
            ]),
            transports: [DoctrineTransportFactory::ALIAS => $this->transport($dbal)],
            routes: [RecordCommand::class => [DoctrineTransportFactory::ALIAS]],
        );

        $kernel->bus()->dispatch(new RecordCommand('durable'));

        self::assertSame([], RecordCommandHandler::$handled, 'A routed command must not run at dispatch time.');
        self::assertSame(1, $this->queueDepth($dbal), 'The message must be a row, not a promise.');

        self::assertSame(1, $kernel->worker(DoctrineTransportFactory::ALIAS)->drain());

        self::assertSame(['durable'], RecordCommandHandler::$handled);
        self::assertSame(0, $this->queueDepth($dbal), 'An acked message must leave the pipe.');
    }

    /**
     * F6 acceptance: stamps on {@see CommandSerializer}'s whitelist (F3 3.10)
     * survive the round trip through the table.
     */
    #[Test]
    public function whitelistedStampsSurviveTheRoundTrip(): void
    {
        $dbal = $this->connection();
        $transport = $this->transport($dbal);

        $transport->send(new Envelope(new RecordCommand('stamped'), [
            new BusNameStamp('middag.bus'),
            new TransportNamesStamp(['heavy']),
            new RedeliveryStamp(4),
        ]));

        $received = null;
        foreach ($transport->get() as $envelope) {
            $received = $envelope;
        }

        self::assertInstanceOf(Envelope::class, $received);

        $message = $received->getMessage();
        self::assertInstanceOf(RecordCommand::class, $message);
        self::assertSame('stamped', $message->value);

        self::assertSame('middag.bus', $received->last(BusNameStamp::class)?->getBusName());
        self::assertSame(['heavy'], $received->last(TransportNamesStamp::class)?->getTransportNames());
        self::assertSame(4, $received->last(RedeliveryStamp::class)?->getRetryCount());

        $transport->ack($received);
    }

    /**
     * The subclass exists so the bridge satisfies the MIDDAG-scoped interface;
     * it must not cost the bridge's other capabilities on the way — `setup()`
     * in particular, which is the `auto_setup` path.
     */
    #[Test]
    public function theBridgesOwnCapabilitiesSurviveTheNominalFix(): void
    {
        $transport = $this->transport($this->connection());

        self::assertInstanceOf(SetupableTransportInterface::class, $transport);
        self::assertInstanceOf(MiddagDoctrineTransport::class, $transport);
    }

    #[Test]
    public function aCustomTableNameIsHonoured(): void
    {
        $dbal = $this->connection();

        $transport = (new DoctrineTransportFactory(new CommandSerializer()))->create(
            DoctrineTransportFactory::ALIAS,
            ['connection' => $dbal, 'table_name' => 'product_queue'],
        );
        $transport->send(new Envelope(new RecordCommand('x')));

        $tables = $this->tables($dbal);
        self::assertContains('product_queue', $tables);
        self::assertNotContains(DoctrineTransportFactory::DEFAULT_TABLE, $tables);
    }

    private function connection(): DbalConnection
    {
        return $this->dbal ??= DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    }

    private function transport(DbalConnection $dbal): MiddagDoctrineTransport
    {
        $transport = (new DoctrineTransportFactory())->create(
            DoctrineTransportFactory::ALIAS,
            ['connection' => $dbal],
        );

        self::assertInstanceOf(MiddagDoctrineTransport::class, $transport);

        return $transport;
    }

    /**
     * @return list<string>
     */
    private function tables(DbalConnection $dbal): array
    {
        return $dbal->createSchemaManager()->listTableNames();
    }

    private function queueDepth(DbalConnection $dbal): int
    {
        return (int) $dbal->fetchOne(
            'SELECT COUNT(*) FROM ' . DoctrineTransportFactory::DEFAULT_TABLE,
        );
    }
}
