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

use Middag\Framework\Form\Field\DateField;
use Middag\Ui\Form\FieldDefinition;
use Middag\Ui\Shared\Enum\FieldType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Covers DateField's own surface: the FieldType::DATE declaration plus the
 * date-specific attribute setters (minDate/maxDate/optional). The base
 * AbstractField fluent API is exercised by AbstractFieldTest.
 *
 * @internal
 */
#[CoversClass(DateField::class)]
final class DateFieldTest extends TestCase
{
    #[Test]
    public function toDefinitionDeclaresDateFieldType(): void
    {
        $def = (new DateField('birth_date'))->toDefinition();

        self::assertInstanceOf(FieldDefinition::class, $def);
        self::assertSame('birth_date', $def->name);
        self::assertSame(FieldType::DATE, $def->type);
    }

    #[Test]
    public function minDateStoresIsoValueInAttributesAndChainsFluently(): void
    {
        $field = new DateField('start_on');

        self::assertSame($field, $field->minDate('2026-01-01'));

        $def = $field->toDefinition();

        self::assertSame('2026-01-01', $def->attributes['min_date']);
    }

    #[Test]
    public function maxDateStoresIsoValueInAttributesAndChainsFluently(): void
    {
        $field = new DateField('end_on');

        self::assertSame($field, $field->maxDate('2026-12-31'));

        $def = $field->toDefinition();

        self::assertSame('2026-12-31', $def->attributes['max_date']);
    }

    #[Test]
    public function optionalDefaultsToTrueAndAcceptsExplicitFalse(): void
    {
        $enabled = (new DateField('due_on'))->optional()->toDefinition();
        self::assertTrue($enabled->attributes['optional']);

        $disabled = (new DateField('due_on'))->optional(false)->toDefinition();
        self::assertFalse($disabled->attributes['optional']);
    }
}
