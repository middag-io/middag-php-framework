<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Bus\Transport;

use Doctrine\DBAL\Connection as DbalConnection;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use InvalidArgumentException;
use Middag\Framework\Bus\Command\CommandSerializer;
use Middag\Framework\Bus\Command\CommandWorker;
use Middag\Framework\Bus\Contract\TransportInterface;
use Middag\Framework\Runtime\StandaloneKernel;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\Connection;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineTransport;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\PostgreSqlConnection;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Throwable;

/**
 * A durable, cross-process transport for a **standalone** PHP host, over
 * `symfony/doctrine-messenger` (core#164 F6 6.1).
 *
 * Thin plumbing on purpose — the same kind of thin that already justifies
 * {@see InMemoryTransport} living in OSS: it holds no MIDDAG know-how, it just
 * hands the bridge a DBAL connection and a serializer. That is why it is here
 * and not in the proprietary core (decision A2).
 *
 * ## Standalone only, and that is a product rule
 *
 * Under Moodle or WordPress this transport is the WRONG answer even though it
 * works, for three reasons that do not apply standalone: it opens a SECOND
 * connection to the same database (doubling connections per process, which
 * matters on RDS), it creates a table OUTSIDE `install.xml`/`dbDelta` (so
 * backup, restore and uninstall never see it), and it does not apply
 * `$CFG->prefix` (so two Moodle installs in one schema collide). Enforcing that
 * is the HOST's job, not this class's: the framework is host-agnostic by
 * definition and has no way to know what it is running under. `middag-io/core`
 * refuses the alias on those two hosts (F6 6.2) — the refusal lives where the
 * host is known.
 *
 * ## `messenger_messages` is a pipe, not a ledger (F6 6.3, G8)
 *
 * The table is the TRANSPORT: a row exists while a message is in flight and
 * disappears when it is acked. It is not, and must not become, the MIDDAG job
 * record — that is `middag_job`, which is permanent and auditable. Unifying
 * them would mean rewriting Symfony's transport, and then it would no longer be
 * Symfony's transport, which is the entire reason this factory exists.
 *
 * `auto_setup` is left ON by default: a standalone install has no guaranteed
 * migration tool, so the transport creating its own table on first use is the
 * only thing that reliably works.
 *
 * ## Polling versus notification (F6 6.4)
 *
 * On PostgreSQL the bridge uses `LISTEN/NOTIFY` and wakes almost immediately;
 * on every other engine it polls at roughly one second. That is a documented
 * characteristic of the transport, not a defect, and it is why this factory
 * picks {@see PostgreSqlConnection} when the platform is PostgreSQL — the same
 * choice the bridge's own factory makes.
 *
 * Footnote for Aurora PostgreSQL: `NOTIFY` does not cross instances, so a
 * notification issued on the writer never reaches a session on a reader.
 * Irrelevant here, because a worker claims a row and therefore connects to the
 * writer.
 *
 * ## Optional dependency, never a `require`
 *
 * `doctrine/dbal` and `symfony/doctrine-messenger` are `suggest` (plus
 * `require-dev`, so this factory is tested against the real bridge rather than
 * a mock). {@see self::missingReason()} is the runtime check a host calls to
 * degrade with a warning instead of fataling.
 *
 * @api
 */
final readonly class DoctrineTransportFactory
{
    /** Transport alias this factory answers to. */
    public const ALIAS = 'doctrine';

    /** Bridge's own default; named here so a host can show it. */
    public const DEFAULT_TABLE = 'messenger_messages';

    public const PACKAGE = 'symfony/doctrine-messenger';

    public const DBAL_PACKAGE = 'doctrine/dbal';

    public function __construct(private SerializerInterface $serializer = new CommandSerializer()) {}

    /**
     * Why the `doctrine` alias cannot be built here, or `null` when it can.
     *
     * User-facing: the sentence is what a host prints when it degrades.
     */
    public static function missingReason(): ?string
    {
        if (!class_exists(DbalConnection::class)) {
            return 'the optional package ' . self::DBAL_PACKAGE . ' is not installed';
        }

        if (!class_exists(DoctrineTransport::class)) {
            return 'the optional package ' . self::PACKAGE . ' is not installed';
        }

        return null;
    }

    public static function isAvailable(): bool
    {
        return self::missingReason() === null;
    }

    public function supports(string $alias): bool
    {
        return $alias === self::ALIAS;
    }

    /**
     * Build the transport.
     *
     * The DBAL connection arrives in `$config` rather than being created here:
     * a standalone host that also runs {@see StandaloneKernel} has exactly ONE
     * database connection, and the whole objection to Doctrine-under-Moodle is
     * about opening a second one. Handing the same connection in (and taking
     * its native PDO for the kernel) is what keeps that honest — and it is what
     * lets an in-memory SQLite integration suite see one database instead of
     * two.
     *
     * @param array<string, mixed> $config
     *                                     - `connection` (required): a `Doctrine\DBAL\Connection`
     *                                     - `table_name`: defaults to {@see self::DEFAULT_TABLE}
     *                                     - `queue_name`: defaults to `default`
     *                                     - `redeliver_timeout`: seconds a claimed row may sit before another worker may take it
     *                                     - `auto_setup`: defaults to true — see the class docblock
     *                                     - `use_notify`: defaults to true; PostgreSQL only
     *
     * @throws InvalidArgumentException when the optional packages are absent, or `connection` is missing/of the wrong type
     */
    public function create(string $alias, array $config): TransportInterface
    {
        $missing = self::missingReason();

        if ($missing !== null) {
            throw new InvalidArgumentException(sprintf('The "%s" transport is unavailable: %s.', self::ALIAS, $missing));
        }

        $connection = $config['connection'] ?? null;

        if (!$connection instanceof DbalConnection) {
            throw new InvalidArgumentException(sprintf(
                'The "%s" transport needs a %s in $config["connection"], got %s.',
                self::ALIAS,
                DbalConnection::class,
                get_debug_type($connection),
            ));
        }

        $configuration = [
            'table_name' => (string) ($config['table_name'] ?? self::DEFAULT_TABLE),
            'queue_name' => (string) ($config['queue_name'] ?? 'default'),
            'redeliver_timeout' => (int) ($config['redeliver_timeout'] ?? 3600),
            'auto_setup' => (bool) ($config['auto_setup'] ?? true),
        ];

        $bridge = $this->usesNotify($config, $connection)
            ? new PostgreSqlConnection($configuration, $connection)
            : new Connection($configuration, $connection);

        return new MiddagDoctrineTransport($bridge, $this->serializer);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function usesNotify(array $config, DbalConnection $connection): bool
    {
        if (($config['use_notify'] ?? true) === false) {
            return false;
        }

        // A platform lookup can open the connection, and a host that merely
        // wants to know whether the alias is buildable must not be broken by an
        // unreachable database at this point — the caller ({@see CommandWorker}
        // or the host's resolver) is the one that decides what a failure means.
        try {
            return $connection->getDatabasePlatform() instanceof PostgreSQLPlatform;
        } catch (Throwable) {
            return false;
        }
    }
}
