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

use Middag\Framework\Form\Field\FileField;
use Middag\Ui\Shared\Enum\FieldType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Covers FileField's own surface: the FILE default type plus the
 * accept()/maxSize() storage descriptor attributes it adds on top of
 * AbstractField (whose fluent API is exercised by AbstractFieldTest).
 *
 * @internal
 */
#[CoversClass(FileField::class)]
final class FileFieldTest extends TestCase
{
    #[Test]
    public function defaultTypeIsFile(): void
    {
        $def = (new FileField('avatar'))->toDefinition();

        self::assertSame(FieldType::File, $def->type);
        self::assertSame('avatar', $def->name);
    }

    #[Test]
    public function acceptStoresMimeTypesAndExtensionsInAttributes(): void
    {
        $field = new FileField('attachment');

        self::assertSame($field, $field->accept(['image/png', '.pdf']));

        $def = $field->toDefinition();

        self::assertSame(['image/png', '.pdf'], $def->attributes['accept']);
    }

    #[Test]
    public function maxSizeStoresByteLimitInAttributes(): void
    {
        $field = new FileField('document');

        self::assertSame($field, $field->maxSize(1048576));

        $def = $field->toDefinition();

        self::assertSame(1048576, $def->attributes['max_size']);
    }

    #[Test]
    public function acceptAndMaxSizeCombineOnDefinitionAttributes(): void
    {
        $def = (new FileField('upload'))
            ->accept(['image/jpeg'])
            ->maxSize(2048)
            ->toDefinition();

        self::assertSame(FieldType::File, $def->type);
        self::assertSame(['image/jpeg'], $def->attributes['accept']);
        self::assertSame(2048, $def->attributes['max_size']);
    }
}
