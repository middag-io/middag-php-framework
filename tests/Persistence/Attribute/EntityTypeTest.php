<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Persistence\Attribute;

use Middag\Framework\Persistence\Attribute\EntityType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * The generic EntityType marker attribute: property promotion and its use as a
 * class-level attribute read back through reflection.
 *
 * @internal
 */
#[CoversClass(EntityType::class)]
final class EntityTypeTest extends TestCase
{
    #[Test]
    public function exposesKeyAndDefaultsLabelToNull(): void
    {
        $attribute = new EntityType('task');

        self::assertSame('task', $attribute->key);
        self::assertNull($attribute->label);
    }

    #[Test]
    public function carriesAnOptionalLabel(): void
    {
        $attribute = new EntityType('task', 'Task');

        self::assertSame('task', $attribute->key);
        self::assertSame('Task', $attribute->label);
    }

    #[Test]
    public function isReadableAsAClassAttribute(): void
    {
        $reflection = new ReflectionClass(AnnotatedEntity::class);
        $attributes = $reflection->getAttributes(EntityType::class);

        self::assertCount(1, $attributes);

        $instance = $attributes[0]->newInstance();

        self::assertInstanceOf(EntityType::class, $instance);
        self::assertSame('annotated', $instance->key);
        self::assertSame('Annotated', $instance->label);
    }
}

/**
 * @internal
 */
#[EntityType('annotated', 'Annotated')]
final class AnnotatedEntity {}
