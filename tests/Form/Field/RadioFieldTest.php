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

use Middag\Framework\Form\Field\RadioField;
use Middag\Ui\Shared\Enum\FieldType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Covers RadioField's own surface: the RADIO type declaration and the
 * options() choice-list setter. The inherited fluent API is exercised by
 * {@see AbstractFieldTest}.
 *
 * @internal
 */
#[CoversClass(RadioField::class)]
final class RadioFieldTest extends TestCase
{
    #[Test]
    public function toDefinitionDeclaresTheRadioFieldType(): void
    {
        $def = (new RadioField('newsletter_choice'))->toDefinition();

        self::assertSame(FieldType::RADIO, $def->type);
        self::assertSame('newsletter_choice', $def->name);
    }

    #[Test]
    public function optionsSetsTheChoiceListOnTheDefinition(): void
    {
        $items = ['yes' => 'Yes', 'no' => 'No', 'maybe' => 'Maybe'];

        $def = (new RadioField('answer'))->options($items)->toDefinition();

        self::assertSame($items, $def->options);
    }

    #[Test]
    public function optionsDefaultsToAnEmptyListWhenNotSet(): void
    {
        $def = (new RadioField('answer'))->toDefinition();

        self::assertSame([], $def->options);
    }

    #[Test]
    public function optionsReturnsTheSameInstanceForFluentChaining(): void
    {
        $field = new RadioField('answer');

        self::assertSame($field, $field->options(['a' => 'A']));
    }
}
