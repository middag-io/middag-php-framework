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

use DateTimeImmutable;
use Middag\Framework\Bus\Contract\CommandInterface;
use Middag\Framework\Exception\MiddagInfrastructureException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\StampInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use Symfony\Component\Messenger\Transport\Serialization\Serializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * Transport serializer that round-trips commands through their own
 * toPayload()/fromPayload(). Lets persistent transports (PDO, doctrine, a host
 * queue) store a command as plain primitives — no reflection, no PHP serialize().
 *
 * Preserves a fixed whitelist of {@see StampInterface} classes across the
 * round trip (keyed `X-Message-Stamp-<FQCN>`, mirroring the convention used by
 * Symfony's own {@see Serializer}).
 * Any stamp outside the whitelist is dropped silently — this is a deliberate
 * allowlist, not a generic stamp serializer.
 *
 * @api
 */
final class CommandSerializer implements SerializerInterface
{
    private const STAMP_HEADER_PREFIX = 'X-Message-Stamp-';

    /**
     * @var list<class-string<StampInterface>>
     */
    private const WHITELISTED_STAMP_CLASSES = [
        RedeliveryStamp::class,
        TransportNamesStamp::class,
        BusNameStamp::class,
    ];

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

        $headers = $encodedEnvelope['headers'] ?? [];
        $stamps = is_array($headers) ? $this->decodeStamps($headers) : [];

        return new Envelope($type::fromPayload($payload), $stamps);
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
            'headers' => ['type' => $command::class, ...$this->encodeStamps($envelope)],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function encodeStamps(Envelope $envelope): array
    {
        $headers = [];

        foreach ($envelope->all() as $stampClass => $stamps) {
            if (!in_array($stampClass, self::WHITELISTED_STAMP_CLASSES, true)) {
                continue;
            }

            $serialized = array_map(
                fn (StampInterface $stamp): array => $this->stampToArray($stamp),
                $stamps,
            );

            $headers[self::STAMP_HEADER_PREFIX . $stampClass] = json_encode($serialized, JSON_THROW_ON_ERROR);
        }

        return $headers;
    }

    /**
     * @param array<string, mixed> $headers
     *
     * @return list<StampInterface>
     */
    private function decodeStamps(array $headers): array
    {
        $stamps = [];

        foreach ($headers as $name => $value) {
            if (!str_starts_with($name, self::STAMP_HEADER_PREFIX) || !is_string($value)) {
                continue;
            }

            /** @var class-string $stampClass */
            $stampClass = substr($name, strlen(self::STAMP_HEADER_PREFIX));

            if (!in_array($stampClass, self::WHITELISTED_STAMP_CLASSES, true)) {
                continue;
            }

            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);

            if (!is_array($decoded)) {
                continue;
            }

            foreach ($decoded as $stampData) {
                if (is_array($stampData)) {
                    $stamps[] = $this->arrayToStamp($stampClass, $stampData);
                }
            }
        }

        return $stamps;
    }

    /**
     * @return array<string, mixed>
     */
    private function stampToArray(StampInterface $stamp): array
    {
        return match (true) {
            $stamp instanceof RedeliveryStamp => [
                'retryCount' => $stamp->getRetryCount(),
                'redeliveredAt' => $stamp->getRedeliveredAt()->format(DATE_ATOM),
            ],
            $stamp instanceof TransportNamesStamp => [
                'transportNames' => $stamp->getTransportNames(),
            ],
            $stamp instanceof BusNameStamp => [
                'busName' => $stamp->getBusName(),
            ],
            default => throw new MiddagInfrastructureException(sprintf(
                'CommandSerializer has no encoding rule for whitelisted stamp %s.',
                $stamp::class,
            )),
        };
    }

    /**
     * @param class-string         $stampClass
     * @param array<string, mixed> $data
     */
    private function arrayToStamp(string $stampClass, array $data): StampInterface
    {
        return match ($stampClass) {
            RedeliveryStamp::class => new RedeliveryStamp(
                (int) ($data['retryCount'] ?? 0),
                isset($data['redeliveredAt']) && is_string($data['redeliveredAt'])
                    ? new DateTimeImmutable($data['redeliveredAt'])
                    : null,
            ),
            TransportNamesStamp::class => new TransportNamesStamp(
                is_array($data['transportNames'] ?? null) ? $data['transportNames'] : [],
            ),
            BusNameStamp::class => new BusNameStamp((string) ($data['busName'] ?? '')),
            default => throw new MiddagInfrastructureException(sprintf(
                'CommandSerializer has no decoding rule for whitelisted stamp %s.',
                $stampClass,
            )),
        };
    }
}
