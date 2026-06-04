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

use Middag\Framework\Persistence\Contract\EntityTypeInterface;

/**
 * Declares an entity type by implementing the interface directly.
 *
 * @internal
 */
final class InterfaceEntityType implements EntityTypeInterface
{
    public function getKey(): string
    {
        return 'gadget';
    }

    public function getEntityClass(): string
    {
        return self::class;
    }

    public function getLabel(): string
    {
        return 'Gadget';
    }
}
