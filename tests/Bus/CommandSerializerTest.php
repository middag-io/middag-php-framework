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

use Middag\Framework\Bus\Command\CommandSerializer;
use Middag\Framework\Exception\MiddagInfrastructureException;
use Middag\Framework\Tests\Bus\Fixture\RecordCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Messenger\Envelope;

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
}
