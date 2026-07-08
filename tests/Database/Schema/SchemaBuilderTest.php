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
use RuntimeException;

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
    /** @var list<string> */
    private array $tempDirs = [];

    protected function tearDown(): void
    {
        foreach ($this->tempDirs as $dir) {
            foreach (glob($dir . '/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($dir);
        }
        $this->tempDirs = [];

        parent::tearDown();
    }

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

    public function testRegisterRejectsEmptyName(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new SchemaBuilder())->register(['name' => '']);
    }

    public function testRegisterRejectsMissingName(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new SchemaBuilder())->register(['columns' => []]);
    }

    public function testAllReturnsEveryDescriptorIndexedByTableName(): void
    {
        $builder = (new SchemaBuilder())
            ->register(['name' => 'one', 'columns' => []])
            ->register(['name' => 'two', 'columns' => []]);

        $all = $builder->all();

        self::assertSame(['one', 'two'], array_keys($all));
        self::assertSame('two', $all['two']['name']);
    }

    public function testLoadFromDirectoryRegistersEveryDescriptorFile(): void
    {
        $dir = $this->makeSchemaDir([
            'alpha.php' => "<?php return ['name' => 'alpha', 'columns' => []];",
            'beta.php' => "<?php return ['name' => 'beta', 'columns' => []];",
        ]);

        $builder = (new SchemaBuilder())->loadFromDirectory($dir);

        self::assertTrue($builder->has('alpha'));
        self::assertTrue($builder->has('beta'));
    }

    public function testLoadFromDirectoryToleratesTrailingSlashAndEmptyDirectory(): void
    {
        $dir = $this->makeSchemaDir([]);

        $builder = (new SchemaBuilder())->loadFromDirectory($dir . '/');

        // No *.php files → nothing registered, but the call is a chainable no-op.
        self::assertSame([], $builder->tables());
    }

    public function testLoadFromDirectoryThrowsWhenDirectoryIsMissing(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Schema directory not found');

        (new SchemaBuilder())->loadFromDirectory(sys_get_temp_dir() . '/middag_missing_' . uniqid());
    }

    public function testLoadFromDirectoryThrowsOnDescriptorWithoutName(): void
    {
        $dir = $this->makeSchemaDir([
            'bad.php' => "<?php return ['columns' => []];",
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid schema descriptor');

        (new SchemaBuilder())->loadFromDirectory($dir);
    }

    public function testLoadFromDirectoryThrowsWhenDescriptorIsNotAnArray(): void
    {
        $dir = $this->makeSchemaDir([
            'scalar.php' => '<?php return 42;',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid schema descriptor');

        (new SchemaBuilder())->loadFromDirectory($dir);
    }

    /**
     * Create a throwaway schema directory populated with the given files.
     *
     * @param array<string, string> $files filename => PHP source
     */
    private function makeSchemaDir(array $files): string
    {
        $dir = sys_get_temp_dir() . '/middag_schema_' . uniqid();
        if (!mkdir($dir, 0o777, true) && !is_dir($dir)) {
            throw new RuntimeException('Unable to create temp schema dir: ' . $dir);
        }

        foreach ($files as $name => $source) {
            file_put_contents($dir . '/' . $name, $source);
        }

        $this->tempDirs[] = $dir;

        return $dir;
    }
}
