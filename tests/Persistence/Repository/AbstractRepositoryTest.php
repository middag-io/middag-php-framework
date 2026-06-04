<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Persistence\Repository;

use Middag\Framework\Database\PdoConnectionAdapter;
use Middag\Framework\Persistence\Repository\AbstractRepository;
use Middag\Framework\Tests\Persistence\Fixture\Note;
use Middag\Framework\Tests\Persistence\Fixture\NoteRepository;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(AbstractRepository::class)]
final class AbstractRepositoryTest extends TestCase
{
    private NoteRepository $repo;

    protected function setUp(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('CREATE TABLE notes (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT)');
        $pdo->exec("INSERT INTO notes (title) VALUES ('first'), ('second')");

        $this->repo = new NoteRepository(new PdoConnectionAdapter($pdo));
    }

    public function testFindReturnsHydratedEntityOrNull(): void
    {
        $note = $this->repo->find(1);

        self::assertInstanceOf(Note::class, $note);
        self::assertSame('first', $note->title);
        self::assertNull($this->repo->find(999));
    }

    public function testFindAllReturnsEntities(): void
    {
        $notes = $this->repo->findAll();

        self::assertCount(2, $notes);
        self::assertContainsOnlyInstancesOf(Note::class, $notes);
    }

    public function testSaveInsertsWhenEntityHasNoId(): void
    {
        $this->repo->save(new Note(null, 'third'));

        self::assertCount(3, $this->repo->findAll());
        self::assertSame('third', $this->repo->find(3)->title);
    }

    public function testSaveUpdatesWhenEntityHasId(): void
    {
        $this->repo->save(new Note(1, 'updated'));

        self::assertSame('updated', $this->repo->find(1)->title);
        self::assertCount(2, $this->repo->findAll());
    }

    public function testDeleteRemovesEntity(): void
    {
        $this->repo->delete(new Note(2, 'second'));

        self::assertNull($this->repo->find(2));
        self::assertCount(1, $this->repo->findAll());
    }
}
