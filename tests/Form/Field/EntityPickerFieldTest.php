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

use Middag\Framework\Form\Field\EntityPickerField;
use Middag\Ui\Shared\Enum\FieldType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Covers EntityPickerField's own surface: the ENTITY_PICKER type it declares and
 * the async-picker attributes its type-specific fluent setters write. The base
 * AbstractField fluent API is exercised by AbstractFieldTest and not re-tested here.
 *
 * @internal
 */
#[CoversClass(EntityPickerField::class)]
final class EntityPickerFieldTest extends TestCase
{
    #[Test]
    public function toDefinitionDeclaresEntityPickerType(): void
    {
        $def = (new EntityPickerField('owner'))->toDefinition();

        self::assertSame('owner', $def->name);
        self::assertSame(FieldType::EntityPicker, $def->type);
    }

    #[Test]
    public function sourceSetsBothSourceAndSourceKeyAttributes(): void
    {
        $field = new EntityPickerField('owner');

        self::assertSame($field, $field->source('mycomponent/users'));

        $attributes = $field->toDefinition()->attributes;
        self::assertSame('mycomponent/users', $attributes['source']);
        self::assertSame('mycomponent/users', $attributes['source_key']);
    }

    #[Test]
    public function typeKeyStoresTypeKeyAttribute(): void
    {
        $field = new EntityPickerField('owner');

        self::assertSame($field, $field->typeKey('user'));

        self::assertSame('user', $field->toDefinition()->attributes['type_key']);
    }

    #[Test]
    public function displayFieldStoresDisplayFieldAttribute(): void
    {
        $field = new EntityPickerField('owner');

        self::assertSame($field, $field->displayField('fullname'));

        self::assertSame('fullname', $field->toDefinition()->attributes['display_field']);
    }

    #[Test]
    public function valueFieldStoresValueFieldAttribute(): void
    {
        $field = new EntityPickerField('owner');

        self::assertSame($field, $field->valueField('id'));

        self::assertSame('id', $field->toDefinition()->attributes['value_field']);
    }

    #[Test]
    public function autocompleteHrefStoresAsyncSearchEndpoint(): void
    {
        $field = new EntityPickerField('owner');

        self::assertSame($field, $field->autocompleteHref('/api/users/search'));

        self::assertSame('/api/users/search', $field->toDefinition()->attributes['autocomplete_href']);
    }

    #[Test]
    public function minCharsStoresAutocompleteThreshold(): void
    {
        $field = new EntityPickerField('owner');

        self::assertSame($field, $field->minChars(3));

        self::assertSame(3, $field->toDefinition()->attributes['autocomplete_min_chars']);
    }

    #[Test]
    public function chainedSettersAccumulateEveryPickerAttribute(): void
    {
        $attributes = (new EntityPickerField('owner'))
            ->source('mycomponent/users')
            ->typeKey('user')
            ->displayField('fullname')
            ->valueField('id')
            ->autocompleteHref('/api/users/search')
            ->minChars(2)
            ->toDefinition()
            ->attributes;

        self::assertSame('mycomponent/users', $attributes['source']);
        self::assertSame('mycomponent/users', $attributes['source_key']);
        self::assertSame('user', $attributes['type_key']);
        self::assertSame('fullname', $attributes['display_field']);
        self::assertSame('id', $attributes['value_field']);
        self::assertSame('/api/users/search', $attributes['autocomplete_href']);
        self::assertSame(2, $attributes['autocomplete_min_chars']);
    }
}
