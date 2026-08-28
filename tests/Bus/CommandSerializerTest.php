<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Bus;

use DateTimeImmutable;
use Middag\Framework\Bus\Command\CommandSerializer;
use Middag\Framework\Bus\Context\UserContextStamp;
use Middag\Framework\Exception\MiddagInfrastructureException;
use Middag\Framework\Tests\Bus\Fixture\RecordCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

/**
 * @internal
 */
#[CoversClass(CommandSerializer::class)]
final class CommandSerializerTest extends TestCase
{
    private CommandSerializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = new CommandSerializer();
    }

    public function testEncodeDecodeRoundTripViaPayload(): void
    {
        $encoded = $this->serializer->encode(new Envelope(new RecordCommand('payload-x')));

        self::assertSame(RecordCommand::class, $encoded['headers']['type']);
        self::assertSame('{"value":"payload-x"}', $encoded['body']);

        $decoded = $this->serializer->decode($encoded)->getMessage();

        self::assertInstanceOf(RecordCommand::class, $decoded);
        self::assertSame('payload-x', $decoded->value);
    }

    public function testDecodeRejectsInvalidEnvelope(): void
    {
        $this->expectException(MiddagInfrastructureException::class);

        $this->serializer->decode(['headers' => ['type' => 'NotACommand'], 'body' => '{}']);
    }

    public function testDecodeRejectsNonStringBody(): void
    {
        $this->expectException(MiddagInfrastructureException::class);

        // Valid command type, but the body is not a string.
        $this->serializer->decode(['headers' => ['type' => RecordCommand::class], 'body' => 42]);
    }

    public function testDecodeRejectsNonArrayPayload(): void
    {
        $this->expectException(MiddagInfrastructureException::class);
        $this->expectExceptionMessage('non-array command payload');

        // Valid JSON, but it decodes to a scalar rather than a payload map.
        $this->serializer->decode(['headers' => ['type' => RecordCommand::class], 'body' => '"just a string"']);
    }

    public function testEncodeRejectsNonCommandMessage(): void
    {
        $this->expectException(MiddagInfrastructureException::class);
        $this->expectExceptionMessage('only encodes');

        $this->serializer->encode(new Envelope(new stdClass()));
    }

    public function testEncodeDecodeRoundTripPreservesWhitelistedStamps(): void
    {
        $redeliveredAt = new DateTimeImmutable('2026-01-15T10:30:00+00:00');
        $envelope = new Envelope(new RecordCommand('payload-x'), [
            new RedeliveryStamp(3, $redeliveredAt),
            new BusNameStamp('command.bus'),
        ]);

        $encoded = $this->serializer->encode($envelope);

        self::assertArrayHasKey('X-Message-Stamp-' . RedeliveryStamp::class, $encoded['headers']);
        self::assertArrayHasKey('X-Message-Stamp-' . BusNameStamp::class, $encoded['headers']);

        $decoded = $this->serializer->decode($encoded);

        $redeliveryStamps = $decoded->all(RedeliveryStamp::class);
        self::assertCount(1, $redeliveryStamps);
        self::assertSame(3, $redeliveryStamps[0]->getRetryCount());
        self::assertSame(
            $redeliveredAt->format(DATE_ATOM),
            $redeliveryStamps[0]->getRedeliveredAt()->format(DATE_ATOM),
        );

        $busNameStamps = $decoded->all(BusNameStamp::class);
        self::assertCount(1, $busNameStamps);
        self::assertSame('command.bus', $busNameStamps[0]->getBusName());
    }

    public function testEncodeDiscardsNonWhitelistedStampsSilently(): void
    {
        $envelope = new Envelope(new RecordCommand('payload-x'), [
            new DelayStamp(5000),
        ]);

        $encoded = $this->serializer->encode($envelope);

        self::assertArrayNotHasKey('X-Message-Stamp-' . DelayStamp::class, $encoded['headers']);

        $decoded = $this->serializer->decode($encoded);

        self::assertCount(0, $decoded->all(DelayStamp::class));
    }

    public function testEncodeDecodeRoundTripWithoutStampsStaysUnaffected(): void
    {
        $encoded = $this->serializer->encode(new Envelope(new RecordCommand('payload-x')));

        self::assertSame(['type' => RecordCommand::class], $encoded['headers']);

        $decoded = $this->serializer->decode($encoded);

        self::assertInstanceOf(RecordCommand::class, $decoded->getMessage());
        self::assertSame([], $decoded->all()[RedeliveryStamp::class] ?? []);
    }

    public function testEncodeDecodeRoundTripPreservesMultipleStampsOfSameClass(): void
    {
        $envelope = new Envelope(new RecordCommand('payload-x'), [
            new TransportNamesStamp('async'),
            new TransportNamesStamp(['async', 'failed']),
        ]);

        $encoded = $this->serializer->encode($envelope);
        $decoded = $this->serializer->decode($encoded);

        $transportNamesStamps = $decoded->all(TransportNamesStamp::class);
        self::assertCount(2, $transportNamesStamps);
        self::assertSame(['async'], $transportNamesStamps[0]->getTransportNames());
        self::assertSame(['async', 'failed'], $transportNamesStamps[1]->getTransportNames());
    }

    public function testEncodeDecodeRoundTripPreservesUserContextStamp(): void
    {
        $encoded = $this->serializer->encode(new Envelope(new RecordCommand('payload-x'), [
            new UserContextStamp(42),
        ]));

        self::assertArrayHasKey('X-Message-Stamp-' . UserContextStamp::class, $encoded['headers']);

        $stamp = $this->serializer->decode($encoded)->last(UserContextStamp::class);

        self::assertInstanceOf(UserContextStamp::class, $stamp);
        self::assertSame(42, $stamp->getUserId());
    }
}
