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

/**
 * Serializable command fixture for async bus tests.
 *
 * @internal
 */
final readonly class RecordCommand implements CommandInterface
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
        return new self((string) $payload['value']);
    }
}
