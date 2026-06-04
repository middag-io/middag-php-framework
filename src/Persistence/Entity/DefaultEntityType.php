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

/**
 * Default {@see EntityTypeInterface} descriptor synthesized for a class that
 * declares `#[EntityType]` without implementing the interface itself.
 *
 * The label falls back to the key when none is given, so {@see self::getLabel()}
 * never returns an empty string.
 *
 * @api
 */
final readonly class DefaultEntityType implements EntityTypeInterface
{
    public function __construct(
        private string $key,
        private string $entityClass,
        private ?string $label = null,
    ) {}

    public function getKey(): string
    {
        return $this->key;
    }

    public function getEntityClass(): string
    {
        return $this->entityClass;
    }

    public function getLabel(): string
    {
        return $this->label ?? $this->key;
    }
}
