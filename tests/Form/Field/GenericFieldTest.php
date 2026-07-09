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
use Middag\Framework\Form\Field\GenericField;
use Middag\Ui\Form\FieldDefinition;
use Middag\Ui\Shared\Enum\FieldType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Covers GenericField: the type-agnostic field whose FieldType is supplied
 * explicitly at construction (checkbox/switch/hidden/etc.) and which therefore
 * has no inferable default type.
 *
 * @internal
 */
#[CoversClass(GenericField::class)]
final class GenericFieldTest extends TestCase
{
    #[Test]
    public function exposesTheExplicitCheckboxTypeInTheDefinition(): void
    {
        $def = (new GenericField('agree_terms', FieldType::Checkbox))->toDefinition();

        self::assertInstanceOf(FieldDefinition::class, $def);
        self::assertSame('agree_terms', $def->name);
        self::assertSame(FieldType::Checkbox, $def->type);
    }

    #[Test]
    public function carriesAnyExplicitTypeVerbatim(): void
    {
        $def = (new GenericField('secret_token_ref', FieldType::Hidden))->toDefinition();

        self::assertSame(FieldType::Hidden, $def->type);
    }

    #[Test]
    public function defaultTypeThrowsBecauseNoTypeCanBeInferred(): void
    {
        $field = new GenericField('feature_enabled', FieldType::Switch);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('GenericField requires explicit FieldType via constructor.');

        (new ReflectionMethod($field, 'defaultType'))->invoke($field);
    }
}
