<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Persistence\Entity;

use Middag\Framework\Persistence\Contract\EntityTypeInterface;
use Middag\Framework\Persistence\Contract\EntityTypeRegistryInterface;
use RuntimeException;

/**
 * In-memory entity type registry.
 *
 * @api
 */
final class EntityTypeRegistry implements EntityTypeRegistryInterface
{
    /** @var array<string, EntityTypeInterface> */
    private array $types = [];

    /** Indexes the type under its {@see EntityTypeInterface::getKey()}; re-registering a key overwrites the prior type (last write wins). */
    public function register(EntityTypeInterface $type): void
    {
        $this->types[$type->getKey()] = $type;
    }

    /**
     * @throws RuntimeException "Entity type not registered: {$key}" when no type is registered under $key
     */
    public function get(string $key): EntityTypeInterface
    {
        return $this->types[$key] ?? throw new RuntimeException('Entity type not registered: ' . $key);
    }

    public function has(string $key): bool
    {
        return isset($this->types[$key]);
    }

    public function all(): array
    {
        return $this->types;
    }
}
