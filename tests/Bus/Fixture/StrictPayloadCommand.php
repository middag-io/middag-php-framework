<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Bus\Fixture;

use Middag\Framework\Bus\Contract\CommandInterface;
use Middag\Framework\Exception\MiddagValidationException;

/**
 * Command whose `fromPayload()` refuses a payload it cannot rebuild from —
 * the realistic shape of a row written by an earlier release of the command.
 *
 * @internal
 */
final readonly class StrictPayloadCommand implements CommandInterface
{
    public function __construct(public string $value) {}

    /**
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return ['value' => $this->value];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromPayload(array $payload): static
    {
        if (!isset($payload['value']) || !is_string($payload['value'])) {
            throw new MiddagValidationException('StrictPayloadCommand requires a string "value".');
        }

        return new self($payload['value']);
    }
}
