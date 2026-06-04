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

use Middag\Framework\Bus\Contract\CommandInterface;
use Middag\Framework\Exception\MiddagInfrastructureException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * Transport serializer that round-trips commands through their own
 * toPayload()/fromPayload(). Lets persistent transports (PDO, doctrine, a host
 * queue) store a command as plain primitives — no reflection, no PHP serialize().
 *
 * @api
 */
final class CommandSerializer implements SerializerInterface
{
    /**
     * @param array<string, mixed> $encodedEnvelope
     */
    public function decode(array $encodedEnvelope): Envelope
    {
        $type = $encodedEnvelope['headers']['type'] ?? null;
        $body = $encodedEnvelope['body'] ?? null;

        if (!is_string($type) || !is_subclass_of($type, CommandInterface::class) || !is_string($body)) {
            throw new MiddagInfrastructureException('CommandSerializer cannot decode the envelope: missing or invalid type/body.');
        }

        $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($decoded)) {
            throw new MiddagInfrastructureException('CommandSerializer decoded a non-array command payload.');
        }

        $payload = [];
        foreach ($decoded as $key => $value) {
            $payload[(string) $key] = $value;
        }

        return new Envelope($type::fromPayload($payload));
    }

    /**
     * @return array<string, mixed>
     */
    public function encode(Envelope $envelope): array
    {
        $command = $envelope->getMessage();

        if (!$command instanceof CommandInterface) {
            throw new MiddagInfrastructureException(sprintf(
                'CommandSerializer only encodes %s, got %s.',
                CommandInterface::class,
                $command::class,
            ));
        }

        return [
            'body' => json_encode($command->toPayload(), JSON_THROW_ON_ERROR),
            'headers' => ['type' => $command::class],
        ];
    }
}
