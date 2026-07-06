<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Persistence;

use Middag\Framework\Database\PdoConnectionAdapter;
use Middag\Framework\Persistence\Model;
use Middag\Framework\Persistence\ModelQuery;
use Middag\Framework\Persistence\Relation\BelongsTo;
use Middag\Framework\Persistence\Relation\BelongsToMany;
use Middag\Framework\Persistence\Relation\HasMany;
use Middag\Framework\Persistence\Relation\HasOne;
use Middag\Framework\Persistence\Relation\Relation;
use Middag\Framework\Tests\Persistence\Fixture\Bio;
use Middag\Framework\Tests\Persistence\Fixture\Book;
use Middag\Framework\Tests\Persistence\Fixture\Topic;
use Middag\Framework\Tests\Persistence\Fixture\Writer;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Phase E — hasOne/hasMany/belongsTo/belongsToMany + with()/load() eager loading.
 *
 * @internal
 */
#[CoversClass(Relation::class)]
#[CoversClass(HasMany::class)]
#[CoversClass(HasOne::class)]
#[CoversClass(BelongsTo::class)]
#[CoversClass(BelongsToMany::class)]
#[CoversClass(Model::class)]
#[CoversClass(ModelQuery::class)]
final class RelationshipsTest extends TestCase
{
    protected function setUp(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('CREATE TABLE writers (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');
        $pdo->exec('CREATE TABLE books (id INTEGER PRIMARY KEY AUTOINCREMENT, writer_id INTEGER, title TEXT)');
        $pdo->exec('CREATE TABLE bios (id INTEGER PRIMARY KEY AUTOINCREMENT, writer_id INTEGER, summary TEXT)');
        $pdo->exec('CREATE TABLE topics (id INTEGER PRIMARY KEY AUTOINCREMENT, label TEXT)');
        $pdo->exec('CREATE TABLE book_topic (book_id INTEGER, topic_id INTEGER)');
        $pdo->exec("INSERT INTO writers (name) VALUES ('Ada'), ('Linus')");
        $pdo->exec("INSERT INTO books (writer_id, title) VALUES (1, 'Analytical'), (1, 'Notes'), (2, 'Kernel')");
        $pdo->exec("INSERT INTO bios (writer_id, summary) VALUES (1, 'Mathematician')");
        $pdo->exec("INSERT INTO topics (label) VALUES ('Math'), ('OS')");
        $pdo->exec('INSERT INTO book_topic (book_id, topic_id) VALUES (1, 1), (1, 2), (3, 2)');

        Model::setConnection(new PdoConnectionAdapter($pdo));
    }

    protected function tearDown(): void
    {
        Model::setConnectionResolver(null);
    }

    public function testHasManyLazy(): void
    {
        $writer = Writer::findOrFail(1);
        $books = $writer->books;

        self::assertIsArray($books);
        self::assertCount(2, $books);
        self::assertContainsOnlyInstancesOf(Book::class, $books);
        self::assertTrue($writer->relationLoaded('books'));
    }

    public function testHasManyEagerAvoidsNPlusOne(): void
    {
        $writers = Writer::query()->orderBy('id')->with('books')->get();

        self::assertTrue($writers[0]->relationLoaded('books'));

        $first = $writers[0]->getRelation('books');
        $second = $writers[1]->getRelation('books');
        self::assertIsArray($first);
        self::assertIsArray($second);
        self::assertCount(2, $first);
        self::assertCount(1, $second);
    }

    public function testHasOneLazyResolvesAndNull(): void
    {
        $bio = Writer::findOrFail(1)->bio;
        self::assertInstanceOf(Bio::class, $bio);
        self::assertSame('Mathematician', $bio->getAttribute('summary'));

        self::assertNull(Writer::findOrFail(2)->bio);
    }

    public function testHasOneEager(): void
    {
        $writers = Writer::query()->orderBy('id')->with('bio')->get();

        self::assertInstanceOf(Bio::class, $writers[0]->getRelation('bio'));
        self::assertNull($writers[1]->getRelation('bio'));
    }

    public function testBelongsToLazy(): void
    {
        $writer = Book::findOrFail(1)->writer;

        self::assertInstanceOf(Writer::class, $writer);
        self::assertSame('Ada', $writer->getAttribute('name'));
    }

    public function testBelongsToEager(): void
    {
        $books = Book::query()->orderBy('id')->with('writer')->get();

        $first = $books[0]->getRelation('writer');
        $third = $books[2]->getRelation('writer');
        self::assertInstanceOf(Writer::class, $first);
        self::assertInstanceOf(Writer::class, $third);
        self::assertSame('Ada', $first->getAttribute('name'));
        self::assertSame('Linus', $third->getAttribute('name'));
    }

    public function testBelongsToManyLazy(): void
    {
        $topics = Book::findOrFail(1)->topics;

        self::assertIsArray($topics);
        self::assertCount(2, $topics);
        self::assertContainsOnlyInstancesOf(Topic::class, $topics);
    }

    public function testBelongsToManyEager(): void
    {
        $books = Book::query()->orderBy('id')->with('topics')->get();

        foreach ([0 => 2, 1 => 0, 2 => 1] as $index => $expected) {
            $topics = $books[$index]->getRelation('topics');
            self::assertIsArray($topics);
            self::assertCount($expected, $topics);
        }
    }

    public function testInverseBelongsToMany(): void
    {
        $books = Topic::findOrFail(2)->books;

        self::assertIsArray($books);
        self::assertCount(2, $books);
        self::assertContainsOnlyInstancesOf(Book::class, $books);
    }

    public function testLoadOnExistingInstance(): void
    {
        $writer = Writer::findOrFail(1);

        self::assertFalse($writer->relationLoaded('books'));

        $writer->load('books');

        self::assertTrue($writer->relationLoaded('books'));
        $books = $writer->getRelation('books');
        self::assertIsArray($books);
        self::assertCount(2, $books);
    }
}
