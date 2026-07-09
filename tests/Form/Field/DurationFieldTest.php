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

use Middag\Framework\Form\Field\DurationField;
use Middag\Ui\Form\FieldDefinition;
use Middag\Ui\Shared\Enum\FieldType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Covers DurationField's own surface: the DURATION field type it declares and
 * the two type-specific attributes it adds (units, default_unit). The base
 * AbstractField fluent API is exercised by AbstractFieldTest.
 *
 * @internal
 */
#[CoversClass(DurationField::class)]
final class DurationFieldTest extends TestCase
{
    #[Test]
    public function toDefinitionDeclaresDurationFieldType(): void
    {
        $def = (new DurationField('lesson_length'))->toDefinition();

        self::assertInstanceOf(FieldDefinition::class, $def);
        self::assertSame('lesson_length', $def->name);
        self::assertSame(FieldType::Duration, $def->type);
    }

    #[Test]
    public function unitsStoresUnitSizesInAttributesAndChainsFluently(): void
    {
        $field = new DurationField('timeout');

        self::assertSame($field, $field->units([60, 3600, 86400]));

        $def = $field->toDefinition();

        self::assertSame([60, 3600, 86400], $def->attributes['units']);
        self::assertSame(FieldType::Duration, $def->type);
    }

    #[Test]
    public function defaultUnitStoresDefaultUnitInAttributesAndChainsFluently(): void
    {
        $field = new DurationField('duration');

        self::assertSame($field, $field->defaultUnit(3600));

        $def = $field->toDefinition();

        self::assertSame(3600, $def->attributes['default_unit']);
    }

    #[Test]
    public function unitsAndDefaultUnitCoexistInDefinitionAttributes(): void
    {
        $def = (new DurationField('window'))
            ->units([1, 60, 3600])
            ->defaultUnit(60)
            ->toDefinition();

        self::assertSame([1, 60, 3600], $def->attributes['units']);
        self::assertSame(60, $def->attributes['default_unit']);
        self::assertSame(FieldType::Duration, $def->type);
    }
}
