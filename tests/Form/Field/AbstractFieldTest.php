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

use InvalidArgumentException;
use Middag\Framework\Form\Field\AbstractField;
use Middag\Framework\Form\Field\TextField;
use Middag\Ui\Condition\Condition;
use Middag\Ui\Form\FieldConstraints;
use Middag\Ui\Form\FieldDefinition;
use Middag\Ui\Shared\Enum\ConditionOperator;
use Middag\Ui\Shared\Enum\FieldType;
use Middag\Ui\Shared\ValueObject\Translatable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints\NotBlank;

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

    #[Test]
    public function defaultValueSurfacesInDefinition(): void
    {
        $def = (new TextField('nickname'))->default('anon')->toDefinition();

        self::assertSame('anon', $def->default);
    }

    #[Test]
    public function readonlyFlagsBothInternalStateAndAttribute(): void
    {
        $def = (new TextField('locked'))->readonly()->toDefinition();

        self::assertTrue($def->attributes['readonly']);
    }

    #[Test]
    public function conditionalMethodsAppendEveryConditionKindInOrder(): void
    {
        $def = (new TextField('conditional'))
            ->visibleWhen('a', ConditionOperator::EQ, '1')
            ->hiddenWhen('b', ConditionOperator::NEQ, '2')
            ->requiredWhen('c', ConditionOperator::IN, ['x'])
            ->disabledWhen('d', ConditionOperator::TRUTHY, true)
            ->toDefinition();

        self::assertContainsOnlyInstancesOf(Condition::class, $def->conditions);
        self::assertSame([
            Condition::KIND_VISIBLE_WHEN,
            Condition::KIND_HIDDEN_WHEN,
            Condition::KIND_REQUIRED_WHEN,
            Condition::KIND_DISABLED_WHEN,
        ], array_map(static fn (Condition $c): string => $c->kind, $def->conditions));
    }

    #[Test]
    public function customRuleTravelsInTheAttributesBag(): void
    {
        $rule = new NotBlank();
        $def = (new TextField('required_note'))->rule($rule)->toDefinition();

        self::assertSame([$rule], $def->attributes['custom_rules']);
    }

    #[Test]
    public function metaNestsUnderTheAttributesMetaKey(): void
    {
        $def = (new TextField('tagged'))->meta('weight', 42)->toDefinition();

        self::assertSame(42, $def->attributes['meta']['weight']);
    }

    #[Test]
    public function constructorRejectsANonSnakeCaseName(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new TextField('BadName');
    }

    #[Test]
    public function constructorRejectsAReservedName(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new TextField('id');
    }
}
