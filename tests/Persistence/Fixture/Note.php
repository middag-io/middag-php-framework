<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Persistence\Fixture;

use Middag\Framework\Persistence\Contract\EntityInterface;

/**
 * Persistence-ignorant domain entity fixture for Data-Mapper tests.
 *
 * @internal
 */
final class Note implements EntityInterface
{
    public function __construct(
        private readonly ?int $id,
        public string $title,
    ) {}

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['id' => $this->id, 'title' => $this->title];
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
}
