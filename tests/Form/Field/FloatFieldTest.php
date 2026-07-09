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

use Middag\Framework\Form\Field\FloatField;
use Middag\Ui\Shared\Enum\FieldType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Pins FloatField's own surface: the FieldType::Float declaration and the
 * numeric min()/max()/step() attributes it adds on top of AbstractField.
 *
 * @internal
 */
#[CoversClass(FloatField::class)]
final class FloatFieldTest extends TestCase
{
    #[Test]
    public function toDefinitionDeclaresFloatFieldType(): void
    {
        $def = (new FloatField('weight'))->toDefinition();

        self::assertSame('weight', $def->name);
        self::assertSame(FieldType::Float, $def->type);
    }

    #[Test]
    public function maxStoresValueInAttributesAndReturnsSelf(): void
    {
        $field = new FloatField('ratio');

        self::assertSame($field, $field->max(9.5));
        self::assertSame(9.5, $field->toDefinition()->attributes['max']);
    }

    #[Test]
    public function minStoresValueInAttributesAndReturnsSelf(): void
    {
        $field = new FloatField('ratio');

        self::assertSame($field, $field->min(-1.25));
        self::assertSame(-1.25, $field->toDefinition()->attributes['min']);
    }

    #[Test]
    public function stepStoresValueInAttributesAndReturnsSelf(): void
    {
        $field = new FloatField('ratio');

        self::assertSame($field, $field->step(0.01));
        self::assertSame(0.01, $field->toDefinition()->attributes['step']);
    }

    #[Test]
    public function numericAttributesChainTogetherIntoDefinition(): void
    {
        $attributes = (new FloatField('temperature'))
            ->min(0.0)
            ->max(100.0)
            ->step(0.5)
            ->toDefinition()
            ->attributes;

        self::assertSame(0.0, $attributes['min']);
        self::assertSame(100.0, $attributes['max']);
        self::assertSame(0.5, $attributes['step']);
    }
}
