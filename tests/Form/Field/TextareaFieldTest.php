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

use Middag\Framework\Form\Field\TextareaField;
use Middag\Ui\Form\FieldDefinition;
use Middag\Ui\Shared\Enum\FieldType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Covers TextareaField's own surface: the FieldType::TEXTAREA declaration and
 * the sizing attribute setters (max/min/rows/cols) it layers on AbstractField.
 *
 * @internal
 */
#[CoversClass(TextareaField::class)]
final class TextareaFieldTest extends TestCase
{
    #[Test]
    public function toDefinitionDeclaresTextareaFieldType(): void
    {
        $def = (new TextareaField('bio'))->toDefinition();

        self::assertInstanceOf(FieldDefinition::class, $def);
        self::assertSame('bio', $def->name);
        self::assertSame(FieldType::TEXTAREA, $def->type);
    }

    #[Test]
    public function maxMinRowsAndColsLandInTheAttributesBag(): void
    {
        $def = (new TextareaField('description'))
            ->min(10)
            ->max(500)
            ->rows(8)
            ->cols(40)
            ->toDefinition();

        self::assertSame(10, $def->attributes['min']);
        self::assertSame(500, $def->attributes['max']);
        self::assertSame(8, $def->attributes['rows']);
        self::assertSame(40, $def->attributes['cols']);
    }

    #[Test]
    public function sizingSettersReturnTheSameInstanceForChaining(): void
    {
        $field = new TextareaField('notes');

        self::assertSame($field, $field->min(1));
        self::assertSame($field, $field->max(255));
        self::assertSame($field, $field->rows(5));
        self::assertSame($field, $field->cols(20));
    }
}
