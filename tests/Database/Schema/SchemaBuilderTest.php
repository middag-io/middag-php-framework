<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Database\Schema;

use InvalidArgumentException;
use Middag\Framework\Database\Schema\SchemaBuilder;
use Middag\Framework\Tests\Database\Schema\Fixture\NoCommentSchema;
use Middag\Framework\Tests\Database\Schema\Fixture\PlainClass;
use Middag\Framework\Tests\Database\Schema\Fixture\SampleSchema;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * `registerClass()`/`loadFromClasses()` feed attribute-authored schema classes
 * into the same array registry `loadFromDirectory()` populates, so every
 * existing accessor (`table`, `columns`, `keys`, `indexes`) keeps working.
 *
 * @internal
 */
#[CoversClass(SchemaBuilder::class)]
final class SchemaBuilderTest extends TestCase
{
    public function testRegisterClassExposesDescriptorThroughArrayAccessors(): void
    {
        $builder = (new SchemaBuilder())->registerClass(SampleSchema::class);

        self::assertTrue($builder->has('sample_table'));
        self::assertSame(['sample_table'], $builder->tables());

        $descriptor = $builder->table('sample_table');
        self::assertNotNull($descriptor);
        self::assertSame('sample_table', $descriptor['name']);

        self::assertCount(5, $builder->columns('sample_table'));
        self::assertCount(3, $builder->keys('sample_table'));
        self::assertCount(2, $builder->indexes('sample_table'));
    }

    public function testRegisterClassProducesSameDescriptorAsRegisterArray(): void
    {
        $viaClass = (new SchemaBuilder())->registerClass(SampleSchema::class)->table('sample_table');

        self::assertNotNull($viaClass);

        // Feeding the same descriptor array back through register() is a no-op:
        // the class path is just a typed producer for the existing array wire format.
        $viaArray = (new SchemaBuilder())->register($viaClass)->table('sample_table');

        self::assertSame($viaClass, $viaArray);
    }

    public function testLoadFromClassesRegistersEveryClass(): void
    {
        $builder = (new SchemaBuilder())->loadFromClasses([SampleSchema::class, NoCommentSchema::class]);

        self::assertSame(['sample_table', 'nc_table'], $builder->tables());
    }

    public function testLoadFromClassesIsChainable(): void
    {
        $builder = new SchemaBuilder();

        self::assertSame($builder, $builder->loadFromClasses([SampleSchema::class]));
    }

    public function testRegisterClassRejectsClassWithoutTableAttribute(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new SchemaBuilder())->registerClass(PlainClass::class);
    }
}
