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

use Closure;
use Middag\Framework\Form\Field\SelectField;
use Middag\Ui\Form\FieldDefinition;
use Middag\Ui\Shared\Enum\FieldType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Pins SelectField's own surface: the FieldType::Select declaration and the
 * options()/optionsFrom()/searchable() members it adds on top of AbstractField.
 * The inherited AbstractField fluent API is exercised by AbstractFieldTest.
 *
 * @internal
 */
#[CoversClass(SelectField::class)]
final class SelectFieldTest extends TestCase
{
    #[Test]
    public function toDefinitionDeclaresSelectFieldType(): void
    {
        $def = (new SelectField('country'))->toDefinition();

        self::assertInstanceOf(FieldDefinition::class, $def);
        self::assertSame('country', $def->name);
        self::assertSame(FieldType::Select, $def->type);
    }

    #[Test]
    public function optionsStoresItemsInDefinitionOptionsAndReturnsSelf(): void
    {
        $field = new SelectField('country');

        self::assertSame($field, $field->options(['br' => 'Brazil', 'pt' => 'Portugal']));
        self::assertSame(
            ['br' => 'Brazil', 'pt' => 'Portugal'],
            $field->toDefinition()->options,
        );
    }

    #[Test]
    public function optionsFromStoresLoaderClosureInAttributesAndReturnsSelf(): void
    {
        $field = new SelectField('country');
        $loader = static fn (): array => ['br' => 'Brazil'];

        self::assertSame($field, $field->optionsFrom($loader));
        self::assertSame($loader, $field->toDefinition()->attributes['options_loader']);
    }

    #[Test]
    public function optionsFromLoaderRemainsInvokableFromAttributes(): void
    {
        $def = (new SelectField('country'))
            ->optionsFrom(static fn (): array => ['us' => 'United States'])
            ->toDefinition();

        $loader = $def->attributes['options_loader'];

        self::assertInstanceOf(Closure::class, $loader);
        self::assertSame(['us' => 'United States'], $loader());
    }

    #[Test]
    public function searchableDefaultsToTrueInAttributesAndReturnsSelf(): void
    {
        $field = new SelectField('country');

        self::assertSame($field, $field->searchable());
        self::assertTrue($field->toDefinition()->attributes['searchable']);
    }

    #[Test]
    public function searchableAcceptsExplicitFalse(): void
    {
        $def = (new SelectField('country'))->searchable(false)->toDefinition();

        self::assertFalse($def->attributes['searchable']);
    }

    #[Test]
    public function selectMembersChainTogetherIntoDefinition(): void
    {
        $def = (new SelectField('country'))
            ->options(['br' => 'Brazil'])
            ->searchable()
            ->toDefinition();

        self::assertSame(FieldType::Select, $def->type);
        self::assertSame(['br' => 'Brazil'], $def->options);
        self::assertTrue($def->attributes['searchable']);
    }
}
