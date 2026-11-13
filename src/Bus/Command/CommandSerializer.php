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
use JsonException;
use Middag\Framework\Bus\Context\UserContextStamp;
use Middag\Framework\Bus\Contract\CommandInterface;
use Middag\Framework\Exception\MiddagInfrastructureException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\StampInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use Symfony\Component\Messenger\Transport\Serialization\Serializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Throwable;

/**
 * Transport serializer that round-trips commands through their own
 * toPayload()/fromPayload(). Lets persistent transports (PDO, doctrine, a host
 * queue) store a command as plain primitives — no reflection, no PHP serialize().
 *
 * Preserves a fixed whitelist of {@see StampInterface} classes across the
 * round trip, keyed `X-Message-Stamp-<alias>` under the stable alias of
 * {@see self::STAMP_ALIASES} (the prefix mirrors the convention of Symfony's
 * own {@see Serializer}; the suffix deliberately does not, so a stamp class
 * that moves or is renamed does not orphan the rows already queued under its
 * old FQCN). Any stamp outside the whitelist is dropped silently — this is a
 * deliberate allowlist, not a generic stamp serializer.
 *
 * **Transition (framework#109).** Every stamp is written under BOTH its alias
 * and its FQCN, and read from either, alias first. Dual-write is what makes a
 * rollback safe: a row written here stays readable by the previous release,
 * which only knows the FQCN key, so a downgrade mid-queue does not silently
 * drop the retry count or the bus name. Reading the FQCN key covers the other
 * direction, rows queued before this version. The FQCN half is removable once
 * no deployed release reads it — a 2.0.0 cleanup, not a 1.x one.
 *
 * **Every** `decode()` failure is a {@see MessageDecodingFailedException}: an
 * unreadable row is a poison message, and that is the one exception type the
 * transport layer and {@see CommandWorker} treat as non-retryable and route
 * straight to dead-letter. Throwing anything else — an infrastructure error, a
 * `JsonException`, or whatever a command's own `fromPayload()` raises — escapes
 * the worker loop and kills the process, which then meets the same row again on
 * the next claim and dies again (framework#108). The original error is always
 * kept as `previous`, so a store writing `last_error` keeps the real cause.
 *
 * @api
 */
final class CommandSerializer implements SerializerInterface
{
    private const STAMP_HEADER_PREFIX = 'X-Message-Stamp-';

    /**
     * The whitelist, keyed by stamp class, with the stable alias each one is
     * written under. Single source of truth: a class absent from this map is
     * not serialized and not read back.
     *
     * @var array<class-string<StampInterface>, string>
     */
    private const STAMP_ALIASES = [
        RedeliveryStamp::class => 'redelivery',
        TransportNamesStamp::class => 'transport-names',
        BusNameStamp::class => 'bus-name',
        UserContextStamp::class => 'user-context',
    ];

    /**
     * @param array<string, mixed> $encodedEnvelope
     *
     * @throws MessageDecodingFailedException on every failure, so the row is
     *                                        treated as a poison message instead of killing the worker
     */
    public function decode(array $encodedEnvelope): Envelope
    {
        $type = $encodedEnvelope['headers']['type'] ?? null;
        $body = $encodedEnvelope['body'] ?? null;

        if (!is_string($type) || !is_subclass_of($type, CommandInterface::class) || !is_string($body)) {
            throw $this->undecodable('CommandSerializer cannot decode the envelope: missing or invalid type/body.');
        }

        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw $this->undecodable('CommandSerializer could not parse the envelope body as JSON.', $jsonException);
        }

        if (!is_array($decoded)) {
            throw $this->undecodable('CommandSerializer decoded a non-array command payload.');
        }

        $payload = [];
        foreach ($decoded as $key => $value) {
            $payload[(string) $key] = $value;
        }

        $headers = $encodedEnvelope['headers'] ?? [];

        try {
            $stamps = is_array($headers) ? $this->decodeStamps($headers) : [];
        } catch (Throwable $throwable) {
            throw $this->undecodable('CommandSerializer could not decode the envelope stamp headers.', $throwable);
        }

        // fromPayload() is the command's own contract: a row written by an older
        // release, or by a command whose payload shape has since changed, fails
        // here. That is still an unreadable row, not a transient failure.
        try {
            $command = $type::fromPayload($payload);
        } catch (Throwable $throwable) {
            throw $this->undecodable(sprintf('CommandSerializer could not rebuild %s from its payload.', $type), $throwable);
        }

        return new Envelope($command, $stamps);
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
     * Writes each whitelisted stamp twice: once under its stable alias, once
     * under its FQCN — see the transition note on the class.
     *
     * @return array<string, string>
     */
    private function encodeStamps(Envelope $envelope): array
    {
        $headers = [];

        foreach ($envelope->all() as $stampClass => $stamps) {
            $alias = self::STAMP_ALIASES[$stampClass] ?? null;

            if ($alias === null) {
                continue;
            }

            $serialized = json_encode(
                array_map(fn (StampInterface $stamp): array => $this->stampToArray($stamp), $stamps),
                JSON_THROW_ON_ERROR,
            );

            $headers[self::STAMP_HEADER_PREFIX . $alias] = $serialized;
            $headers[self::STAMP_HEADER_PREFIX . $stampClass] = $serialized;
        }

        return $headers;
    }

    /**
     * Reads the alias key, and falls back to the legacy FQCN key for rows
     * written before the aliases existed. A row carrying both — every row this
     * version writes — yields each stamp once: the alias key wins and the FQCN
     * key for the same class is ignored, never appended on top.
     *
     * @param array<string, mixed> $headers
     *
     * @return list<StampInterface>
     */
    private function decodeStamps(array $headers): array
    {
        /** @var array<class-string<StampInterface>, string> $byAlias */
        $byAlias = [];

        /** @var array<class-string<StampInterface>, string> $byClass */
        $byClass = [];

        foreach ($headers as $name => $value) {
            if (!str_starts_with($name, self::STAMP_HEADER_PREFIX) || !is_string($value)) {
                continue;
            }

            $key = substr($name, strlen(self::STAMP_HEADER_PREFIX));
            $aliased = array_search($key, self::STAMP_ALIASES, true);

            if ($aliased !== false) {
                $byAlias[$aliased] = $value;

                continue;
            }

            /** @var class-string<StampInterface> $key */
            if (isset(self::STAMP_ALIASES[$key])) {
                $byClass[$key] = $value;
            }
        }

        $stamps = [];

        // Whitelist order, not header order, so the result does not depend on
        // how a transport happened to store its header map.
        foreach (array_keys(self::STAMP_ALIASES) as $stampClass) {
            $value = $byAlias[$stampClass] ?? $byClass[$stampClass] ?? null;

            if ($value === null) {
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
            $stamp instanceof UserContextStamp => [
                'userId' => $stamp->getUserId(),
            ],
            default => throw new MiddagInfrastructureException(sprintf(
                'CommandSerializer has no encoding rule for whitelisted stamp %s.',
                $stamp::class,
            )),
        };
    }

    /**
     * @param class-string<StampInterface> $stampClass
     * @param array<string, mixed>         $data
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
            UserContextStamp::class => new UserContextStamp(
                isset($data['userId']) ? (int) $data['userId'] : null,
            ),
            default => throw new MiddagInfrastructureException(sprintf(
                'CommandSerializer has no decoding rule for whitelisted stamp %s.',
                $stampClass,
            )),
        };
    }

    /**
     * The single exit for a failed decode.
     *
     * The raw row is deliberately not passed on: the exception's
     * `encodedEnvelope` argument only exists from symfony/messenger 8.1, and
     * this package supports `^7.0 || ^8.0`, where the class takes the plain
     * three-argument shape. The message plus `previous` carry the cause, and a
     * stored payload stays out of whatever logs the exception.
     */
    private function undecodable(string $message, ?Throwable $previous = null): MessageDecodingFailedException
    {
        return new MessageDecodingFailedException($message, 0, $previous);
    }
}
