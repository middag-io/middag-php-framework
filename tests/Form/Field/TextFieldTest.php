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

use Middag\Framework\Form\Field\TextField;
use Middag\Ui\Form\FieldDefinition;
use Middag\Ui\Shared\Enum\FieldType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Covers TextField's own surface: the FieldType::Text declaration and the
 * max/min/pattern attribute setters it layers on AbstractField.
 *
 * @internal
 */
#[CoversClass(TextField::class)]
final class TextFieldTest extends TestCase
{
    #[Test]
    public function toDefinitionDeclaresTextFieldType(): void
    {
        $def = (new TextField('title'))->toDefinition();

        self::assertInstanceOf(FieldDefinition::class, $def);
        self::assertSame('title', $def->name);
        self::assertSame(FieldType::Text, $def->type);
    }

    #[Test]
    public function maxMinAndPatternLandInTheAttributesBag(): void
    {
        $def = (new TextField('username'))
            ->min(3)
            ->max(64)
            ->pattern('[a-z0-9_]+')
            ->toDefinition();

        self::assertSame(3, $def->attributes['min']);
        self::assertSame(64, $def->attributes['max']);
        self::assertSame('[a-z0-9_]+', $def->attributes['pattern']);
    }

    #[Test]
    public function textSettersReturnTheSameInstanceForChaining(): void
    {
        $field = new TextField('nickname');

        self::assertSame($field, $field->min(1));
        self::assertSame($field, $field->max(32));
        self::assertSame($field, $field->pattern('.+'));
    }
}
