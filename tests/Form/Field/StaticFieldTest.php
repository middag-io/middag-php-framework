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

use Middag\Framework\Form\Field\StaticField;
use Middag\Ui\Form\FieldDefinition;
use Middag\Ui\Shared\Enum\FieldType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Covers the StaticField subclass surface: its FieldType::Static declaration
 * and the content() i18n intent it stores in the definition attributes.
 * The inherited AbstractField fluent API is exercised by AbstractFieldTest.
 *
 * @internal
 */
#[CoversClass(StaticField::class)]
final class StaticFieldTest extends TestCase
{
    #[Test]
    public function toDefinitionDeclaresStaticFieldType(): void
    {
        $def = (new StaticField('notice'))->toDefinition();

        self::assertInstanceOf(FieldDefinition::class, $def);
        self::assertSame('notice', $def->name);
        self::assertSame(FieldType::Static, $def->type);
    }

    #[Test]
    public function contentStoresI18nIntentInAttributes(): void
    {
        $def = (new StaticField('notice'))
            ->content('page_intro', 'local_helico')
            ->toDefinition();

        self::assertSame(
            ['key' => 'page_intro', 'component' => 'local_helico'],
            $def->attributes['content'],
        );
    }

    #[Test]
    public function contentDefaultsComponentToEmptyString(): void
    {
        $def = (new StaticField('notice'))->content('page_intro')->toDefinition();

        self::assertSame(
            ['key' => 'page_intro', 'component' => ''],
            $def->attributes['content'],
        );
    }

    #[Test]
    public function contentReturnsSameInstanceForFluentChaining(): void
    {
        $field = new StaticField('notice');

        self::assertSame($field, $field->content('page_intro'));
    }
}
