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

use BadMethodCallException;
use Middag\Framework\Database\PdoConnectionAdapter;
use Middag\Framework\Persistence\Model;
use Middag\Framework\Persistence\ModelQuery;
use Middag\Framework\Persistence\Query\QueryBuilder;
use Middag\Framework\Tests\Persistence\Fixture\Task;
use Middag\Framework\Tests\Persistence\Fixture\Writer;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the model-aware query wrapper end to end against a real SQLite
 * engine: every fluent passthrough rebinds the builder, and the terminals
 * hydrate rows into model instances.
 *
 * @internal
 */
#[CoversClass(ModelQuery::class)]
final class ModelQueryTest extends TestCase
{
    protected function setUp(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('CREATE TABLE tasks (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT, status TEXT, tags TEXT, priority INTEGER, owner_id INTEGER)');
        $pdo->exec("INSERT INTO tasks (title, status, tags, priority, owner_id) VALUES ('A', 'pending', NULL, 5, 1), ('B', 'done', NULL, 3, 1), ('C', 'pending', NULL, 1, 2)");
        $pdo->exec('CREATE TABLE owners (id INTEGER PRIMARY KEY, name TEXT)');
        $pdo->exec("INSERT INTO owners (id, name) VALUES (1, 'Ana'), (2, 'Bob')");
        // For the eager-load path (attributed to ModelQuery here, not Relation).
        $pdo->exec('CREATE TABLE writers (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');
        $pdo->exec('CREATE TABLE books (id INTEGER PRIMARY KEY AUTOINCREMENT, writer_id INTEGER, title TEXT)');
        $pdo->exec("INSERT INTO writers (name) VALUES ('Ada'), ('Linus')");
        $pdo->exec("INSERT INTO books (writer_id, title) VALUES (1, 'Analytical'), (1, 'Notes'), (2, 'Kernel')");

        Model::setConnection(new PdoConnectionAdapter($pdo));
    }

    protected function tearDown(): void
    {
        Model::setConnectionResolver(null);
    }

    // ---- local-scope dispatch (__call) ------------------------------------

    #[Test]
    public function localScopeIsDispatchedThroughMagicCall(): void
    {
        $done = Task::query()->done()->get();

        self::assertCount(1, $done);
        self::assertSame('B', $done[0]->getAttribute('title'));
    }

    #[Test]
    public function unknownScopeThrowsBadMethodCall(): void
    {
        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('no local scope "scopeNope"');
        Task::query()->nope();
    }

    #[Test]
    public function whereForwardsEveryArity(): void
    {
        // 1-arg closure, 3-arg operator, 4-arg boolean — each match arm.
        self::assertSame(1, Task::query()->where(static fn (QueryBuilder $q): QueryBuilder => $q->where('status', 'done'))->count());
        self::assertSame(2, Task::query()->where('priority', '>', 2)->count());
        self::assertSame(2, Task::query()->where('id', 1)->where('id', '=', 3, 'or')->count());
    }

    #[Test]
    public function orWhereForwardsClosureAndThreeArg(): void
    {
        self::assertSame(2, Task::query()->where('id', 1)->orWhere(static fn (QueryBuilder $q): QueryBuilder => $q->where('status', 'done'))->count());
        self::assertSame(2, Task::query()->where('id', 3)->orWhere('priority', '>', 4)->count());
    }

    #[Test]
    public function orWhereColumnThreeArgComparesColumns(): void
    {
        // tasks where id > owner_id: id2(owner1) and id3(owner2).
        self::assertSame(2, Task::query()->where('id', 999)->orWhereColumn('id', '>', 'owner_id')->count());
    }

    #[Test]
    public function firstEagerLoadsQueuedRelations(): void
    {
        $writer = Writer::query()->orderBy('id')->with('books')->first();

        self::assertNotNull($writer);
        self::assertTrue($writer->relationLoaded('books'));
        self::assertCount(2, $writer->getRelation('books'));
    }

    // ---- where family ------------------------------------------------------

    #[Test]
    public function whereInAndWhereNotInFilterRows(): void
    {
        self::assertCount(2, Task::query()->whereIn('id', [1, 2])->get());
        self::assertCount(2, Task::query()->whereNotIn('id', [1])->get());
    }

    #[Test]
    public function whereBetweenBoundsInclusive(): void
    {
        $ids = array_map(
            static fn (Task $t): int => (int) $t->getAttribute('id'),
            Task::query()->whereBetween('priority', 2, 5)->orderBy('id')->get(),
        );

        self::assertSame([1, 2], $ids);
    }

    #[Test]
    public function whereNullAndWhereNotNull(): void
    {
        self::assertSame(3, Task::query()->whereNull('tags')->count());
        self::assertSame(0, Task::query()->whereNotNull('tags')->count());
    }

    #[Test]
    public function orWhereWidensTheResultSet(): void
    {
        $rows = Task::query()->where('status', 'done')->orWhere('priority', 1)->orderBy('id')->get();

        self::assertSame([2, 3], array_map(static fn (Task $t): int => (int) $t->getAttribute('id'), $rows));
    }

    // ---- ordering / paging -------------------------------------------------

    #[Test]
    public function latestAndOldestOrderByColumn(): void
    {
        self::assertSame(3, (int) Task::query()->latest()->first()->getAttribute('id'));
        self::assertSame(1, (int) Task::query()->oldest()->first()->getAttribute('id'));
    }

    #[Test]
    public function limitOffsetAndForPageSliceRows(): void
    {
        self::assertSame(2, (int) Task::query()->orderBy('id')->limit(1)->offset(1)->get()[0]->getAttribute('id'));
        self::assertSame(2, (int) Task::query()->orderBy('id')->forPage(2, 1)->get()[0]->getAttribute('id'));
    }

    // ---- projections / joins ----------------------------------------------

    #[Test]
    public function selectRestrictsColumns(): void
    {
        $first = Task::query()->select('title')->orderBy('id')->get()[0];

        self::assertSame('A', $first->getAttribute('title'));
        self::assertNull($first->getAttribute('priority'));
    }

    #[Test]
    public function joinAndLeftJoinResolveMatchingRows(): void
    {
        self::assertCount(3, Task::query()->join('owners', 'tasks.owner_id', '=', 'owners.id')->get());
        self::assertCount(3, Task::query()->leftJoin('owners', 'tasks.owner_id', '=', 'owners.id')->get());
    }

    #[Test]
    public function whereColumnAndOrWhereColumnCompareTwoColumns(): void
    {
        // tasks row 1 has id === owner_id (1 === 1).
        self::assertSame([1], array_map(
            static fn (Task $t): int => (int) $t->getAttribute('id'),
            Task::query()->whereColumn('id', 'owner_id')->get(),
        ));

        self::assertSame([1], array_map(
            static fn (Task $t): int => (int) $t->getAttribute('id'),
            Task::query()->where('id', 999)->orWhereColumn('id', 'owner_id')->get(),
        ));
    }

    #[Test]
    public function groupByHavingAndOrHavingAggregate(): void
    {
        $groups = Task::query()
            ->select('status', 'COUNT(*) AS c')
            ->groupBy('status')
            ->having('c', '>', 0)
            ->get();
        self::assertCount(2, $groups);

        $orGroups = Task::query()
            ->select('status', 'COUNT(*) AS c')
            ->groupBy('status')
            ->having('c', '>', 5)
            ->orHaving('c', '>', 0)
            ->get();
        self::assertCount(2, $orGroups);
    }

    // ---- scalar / boolean terminals ---------------------------------------

    #[Test]
    public function existsReflectsMatches(): void
    {
        self::assertTrue(Task::query()->where('id', 1)->exists());
        self::assertFalse(Task::query()->where('id', 999)->exists());
    }

    #[Test]
    public function valueReturnsASingleColumn(): void
    {
        self::assertSame('A', Task::query()->where('id', 1)->value('title'));
    }

    #[Test]
    public function pluckReturnsListOrKeyedMap(): void
    {
        self::assertSame(['A', 'B', 'C'], Task::query()->orderBy('id')->pluck('title'));
        self::assertSame([1 => 'A', 2 => 'B', 3 => 'C'], Task::query()->orderBy('id')->pluck('title', 'id'));
    }

    // ---- streaming terminals ----------------------------------------------

    #[Test]
    public function lazyYieldsHydratedModels(): void
    {
        $models = iterator_to_array(Task::query()->orderBy('id')->lazy(2));

        self::assertCount(3, $models);
        self::assertContainsOnlyInstancesOf(Task::class, $models);
    }

    #[Test]
    public function chunkHydratesEachBatch(): void
    {
        $seen = 0;
        $result = Task::query()->orderBy('id')->chunk(2, function (array $models) use (&$seen): bool {
            self::assertContainsOnlyInstancesOf(Task::class, $models);
            $seen += count($models);

            return true;
        });

        self::assertTrue($result);
        self::assertSame(3, $seen);
    }

    // ---- write terminals ---------------------------------------------------

    #[Test]
    public function updateWritesThroughTheBuilder(): void
    {
        self::assertSame(1, Task::query()->where('id', 1)->update(['title' => 'Z']));
        self::assertSame('Z', Task::query()->where('id', 1)->value('title'));
    }

    #[Test]
    public function deleteRemovesThroughTheBuilder(): void
    {
        self::assertSame(1, Task::query()->where('id', 3)->delete());
        self::assertSame(2, Task::query()->count());
    }

    // ---- builder / model accessors ----------------------------------------

    #[Test]
    public function toSqlToBuilderAndGetModelExposeUnderlyingState(): void
    {
        $query = Task::query()->where('id', 1);

        self::assertStringContainsStringIgnoringCase('select', $query->toSql());
        self::assertStringContainsString('tasks', $query->toSql());
        self::assertInstanceOf(QueryBuilder::class, $query->toBuilder());
        self::assertInstanceOf(Task::class, $query->getModel());
    }

    // ---- eager loading (attributed to ModelQuery here) ---------------------

    #[Test]
    public function withEagerLoadsRelationsOntoResults(): void
    {
        $writers = Writer::query()->orderBy('id')->with('books')->get();

        self::assertTrue($writers[0]->relationLoaded('books'));
        self::assertCount(2, $writers[0]->getRelation('books'));
        self::assertCount(1, $writers[1]->getRelation('books'));
    }
}
