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

use Middag\Framework\Form\Field\AbstractField;
use Middag\Framework\Form\Field\TextField;
use Middag\Ui\Form\FieldConstraints;
use Middag\Ui\Form\FieldDefinition;
use Middag\Ui\Shared\Enum\FieldType;
use Middag\Ui\Shared\ValueObject\Translatable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Pins the ui 0.5.0 contract boundary of AbstractField::toDefinition():
 * Translatable label/help (component -> domain) and a typed FieldConstraints.
 *
 * @internal
 */
#[CoversClass(AbstractField::class)]
#[CoversClass(TextField::class)]
final class AbstractFieldTest extends TestCase
{
    #[Test]
    public function toDefinitionBuildsTypedConstraintsAndTranslatableLabels(): void
    {
        $def = (new TextField('username'))
            ->label('field_username', 'core_user')
            ->help('field_username_help', 'core_user')
            ->required()
            ->max(64)
            ->toDefinition();

        self::assertInstanceOf(FieldDefinition::class, $def);
        self::assertSame('username', $def->name);
        self::assertSame(FieldType::TEXT, $def->type);

        self::assertInstanceOf(Translatable::class, $def->label);
        self::assertSame('field_username', $def->label->key);
        self::assertSame('core_user', $def->label->domain);

        self::assertInstanceOf(Translatable::class, $def->help);
        self::assertSame('field_username_help', $def->help->key);
        self::assertSame('core_user', $def->help->domain);

        self::assertInstanceOf(FieldConstraints::class, $def->constraints);
        self::assertTrue($def->constraints->required);

        // min/max/pattern stay in the untyped attributes bag; FormValidator
        // interprets them by field type (text = char length, int/float = numeric value).
        self::assertSame(64, $def->attributes['max']);
        self::assertFalse($def->attributes['readonly']);
        self::assertSame([], $def->attributes['custom_rules']);
    }

    #[Test]
    public function toDefinitionLeavesLabelsNullAndNotRequiredByDefault(): void
    {
        $def = (new TextField('note'))->toDefinition();

        self::assertNull($def->label);
        self::assertNull($def->help);
        self::assertFalse($def->constraints->required);
    }

    #[Test]
    public function placeholderStaysRawIntentInAttributes(): void
    {
        $def = (new TextField('email'))->placeholder('field_email_ph', 'core_user')->toDefinition();

        self::assertSame(
            ['key' => 'field_email_ph', 'component' => 'core_user'],
            $def->attributes['placeholder'],
        );
    }
}
