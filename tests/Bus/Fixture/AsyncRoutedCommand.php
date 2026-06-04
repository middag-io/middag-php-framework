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
use Symfony\Component\Messenger\Attribute\AsMessage;

/**
 * Command fixture routed to the "async" transport via Symfony's #[AsMessage].
 *
 * @internal
 */
#[AsMessage('async')]
final readonly class AsyncRoutedCommand implements CommandInterface
{
    public function __construct(public string $value = '') {}

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
