<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Logging\Processor;

use DateTimeImmutable;
use Middag\Framework\Logging\Contract\ActorResolverInterface;
use Middag\Framework\Logging\Contract\OriginResolverInterface;
use Middag\Framework\Logging\Processor\ActorOriginProcessor;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The processor stamps each record's `extra` with the resolved actor and
 * origin.
 *
 * @internal
 */
#[CoversClass(ActorOriginProcessor::class)]
final class ActorOriginProcessorTest extends TestCase
{
    #[Test]
    public function stampsActorAndOriginIntoExtra(): void
    {
        $processor = new ActorOriginProcessor(
            $this->actorResolver('user:7'),
            $this->originResolver('ip:198.51.100.4'),
        );

        $record = $processor($this->record());

        self::assertSame('user:7', $record->extra['actor']);
        self::assertSame('ip:198.51.100.4', $record->extra['origin']);
    }

    #[Test]
    public function overwritesAnyPreexistingActorAndOrigin(): void
    {
        $processor = new ActorOriginProcessor(
            $this->actorResolver('system'),
            $this->originResolver('cli'),
        );

        $record = $processor($this->record(['actor' => 'stale', 'origin' => 'stale']));

        self::assertSame('system', $record->extra['actor']);
        self::assertSame('cli', $record->extra['origin']);
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function record(array $extra = []): LogRecord
    {
        return new LogRecord(
            datetime: new DateTimeImmutable('2026-07-08 09:30:00'),
            channel: 'core/system',
            level: Level::Info,
            message: 'ping',
            context: [],
            extra: $extra,
        );
    }

    private function actorResolver(string $value): ActorResolverInterface
    {
        return new class($value) implements ActorResolverInterface {
            public function __construct(private readonly string $value) {}

            public function resolve(): string
            {
                return $this->value;
            }
        };
    }

    private function originResolver(string $value): OriginResolverInterface
    {
        return new class($value) implements OriginResolverInterface {
            public function __construct(private readonly string $value) {}

            public function resolve(): string
            {
                return $this->value;
            }
        };
    }
}
