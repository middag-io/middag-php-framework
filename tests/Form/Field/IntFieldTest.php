<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Form\Field;

use Middag\Framework\Form\Field\IntField;
use Middag\Ui\Form\FieldDefinition;
use Middag\Ui\Shared\Enum\FieldType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Covers IntField's own surface: the FieldType::INT declaration and the
 * numeric attribute setters (min/max/step) it layers on AbstractField.
 *
 * @internal
 */
#[CoversClass(IntField::class)]
final class IntFieldTest extends TestCase
{
    #[Test]
    public function toDefinitionDeclaresIntFieldType(): void
    {
        $def = (new IntField('age'))->toDefinition();

        self::assertInstanceOf(FieldDefinition::class, $def);
        self::assertSame('age', $def->name);
        self::assertSame(FieldType::INT, $def->type);
    }

    #[Test]
    public function minMaxAndStepLandInTheAttributesBag(): void
    {
        $def = (new IntField('quantity'))
            ->min(1)
            ->max(99)
            ->step(2)
            ->toDefinition();

        self::assertSame(1, $def->attributes['min']);
        self::assertSame(99, $def->attributes['max']);
        self::assertSame(2, $def->attributes['step']);
    }

    #[Test]
    public function numericSettersReturnTheSameInstanceForChaining(): void
    {
        $field = new IntField('score');

        self::assertSame($field, $field->min(0));
        self::assertSame($field, $field->max(10));
        self::assertSame($field, $field->step(5));
    }
}
