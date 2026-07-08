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
use Middag\Framework\Database\Attribute\Column;
use Middag\Framework\Database\Attribute\Index;
use Middag\Framework\Database\Attribute\Key;
use Middag\Framework\Database\Attribute\Table;
use Middag\Framework\Database\Schema\SchemaAttributeReader;
use Middag\Framework\Tests\Database\Schema\Fixture\NoCommentSchema;
use Middag\Framework\Tests\Database\Schema\Fixture\PlainClass;
use Middag\Framework\Tests\Database\Schema\Fixture\SampleSchema;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The reader reflects `#[Table]`/`#[Column]`/`#[Key]`/`#[Index]` into the exact
 * descriptor array the DDL adapters already consume — optional fields omitted
 * when unset, so the output mirrors the sparse hand-written descriptor files.
 *
 * @internal
 */
#[CoversClass(SchemaAttributeReader::class)]
#[CoversClass(Table::class)]
#[CoversClass(Column::class)]
#[CoversClass(Key::class)]
#[CoversClass(Index::class)]
final class SchemaAttributeReaderTest extends TestCase
{
    public function testReadsFullDescriptorInCanonicalShape(): void
    {
        $descriptor = (new SchemaAttributeReader())->read(SampleSchema::class);

        self::assertSame([
            'name' => 'sample_table',
            'comment' => 'A sample table',
            'columns' => [
                ['name' => 'id', 'type' => 'int', 'length' => 10, 'notnull' => true, 'sequence' => true],
                ['name' => 'label', 'type' => 'char', 'length' => 100, 'notnull' => true, 'sequence' => false, 'default' => 'draft', 'comment' => 'The label'],
                ['name' => 'body', 'type' => 'text', 'notnull' => false, 'sequence' => false, 'comment' => 'Body text'],
                ['name' => 'score', 'type' => 'decimal', 'length' => 10, 'notnull' => false, 'sequence' => false, 'decimals' => 2],
                ['name' => 'ownerid', 'type' => 'int', 'length' => 10, 'notnull' => false, 'sequence' => false, 'comment' => 'Owner'],
            ],
            'keys' => [
                ['name' => 'primary', 'type' => 'primary', 'fields' => ['id']],
                ['name' => 'ownerid', 'type' => 'foreign', 'fields' => ['ownerid'], 'reftable' => 'user', 'reffields' => ['id']],
                ['name' => 'label', 'type' => 'foreign-unique', 'fields' => ['label'], 'reftable' => 'other', 'reffields' => ['id']],
            ],
            'indexes' => [
                ['name' => 'labelscore', 'unique' => false, 'fields' => ['label', 'score']],
                ['name' => 'body_idx', 'unique' => true, 'fields' => ['body']],
            ],
        ], $descriptor);
    }

    public function testTextColumnOmitsLength(): void
    {
        $descriptor = (new SchemaAttributeReader())->read(SampleSchema::class);
        $body = $descriptor['columns'][2];

        self::assertSame('body', $body['name']);
        self::assertArrayNotHasKey('length', $body);
    }

    public function testAlwaysEmitsNotnullAndSequence(): void
    {
        $descriptor = (new SchemaAttributeReader())->read(SampleSchema::class);

        foreach ($descriptor['columns'] as $column) {
            self::assertArrayHasKey('notnull', $column);
            self::assertArrayHasKey('sequence', $column);
        }
    }

    public function testOmitsUnsetOptionalColumnFields(): void
    {
        $descriptor = (new SchemaAttributeReader())->read(SampleSchema::class);
        $id = $descriptor['columns'][0];

        self::assertArrayNotHasKey('default', $id);
        self::assertArrayNotHasKey('comment', $id);
        self::assertArrayNotHasKey('decimals', $id);
    }

    public function testPreservesForeignUniqueTokenLossless(): void
    {
        $descriptor = (new SchemaAttributeReader())->read(SampleSchema::class);

        self::assertSame('foreign-unique', $descriptor['keys'][2]['type']);
    }

    public function testPrimaryKeyOmitsReferenceFields(): void
    {
        $descriptor = (new SchemaAttributeReader())->read(SampleSchema::class);
        $primary = $descriptor['keys'][0];

        self::assertArrayNotHasKey('reftable', $primary);
        self::assertArrayNotHasKey('reffields', $primary);
    }

    public function testTableWithoutCommentOmitsCommentButKeepsEmptyCollections(): void
    {
        $descriptor = (new SchemaAttributeReader())->read(NoCommentSchema::class);

        self::assertArrayNotHasKey('comment', $descriptor);
        self::assertSame([], $descriptor['keys']);
        self::assertSame([], $descriptor['indexes']);
        self::assertCount(1, $descriptor['columns']);
    }

    public function testCachesResultPerClass(): void
    {
        $reader = new SchemaAttributeReader();

        self::assertSame($reader->read(SampleSchema::class), $reader->read(SampleSchema::class));
    }

    public function testRejectsClassWithoutTableAttribute(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(PlainClass::class);

        (new SchemaAttributeReader())->read(PlainClass::class);
    }
}
