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
use Middag\Framework\Tests\Persistence\Fixture\Status;
use Middag\Framework\Tests\Persistence\Fixture\Task;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Phase D — creators, timestamps, rich casts, fresh/refresh/replicate, scopes.
 *
 * @internal
 */
#[CoversClass(Model::class)]
#[CoversClass(ModelQuery::class)]
final class ModelConveniencesTest extends TestCase
{
    protected function setUp(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec(
            'CREATE TABLE tasks (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT, status TEXT, tags TEXT, created_at TEXT, updated_at TEXT)'
        );
        Model::setConnection(new PdoConnectionAdapter($pdo));
    }

    protected function tearDown(): void
    {
        Model::setConnectionResolver(null);
    }

    public function testCreatePersistsAndCastsRichTypes(): void
    {
        $task = Task::create(['title' => 'Write', 'status' => Status::Pending, 'tags' => ['a', 'b']]);

        self::assertTrue($task->exists());
        self::assertGreaterThan(0, $task->getKey());

        $fresh = Task::find($task->getKey());
        self::assertSame('Write', $fresh->getAttribute('title'));
        self::assertSame(Status::Pending, $fresh->getAttribute('status'));
        self::assertSame(['a', 'b'], $fresh->getAttribute('tags'));
    }

    public function testTimestampsSetOnInsertAndUpdate(): void
    {
        $task = Task::create(['title' => 'A', 'status' => 'pending', 'tags' => []]);

        $created = Task::find($task->getKey());
        self::assertNotNull($created->getAttribute('created_at'));
        self::assertNotNull($created->getAttribute('updated_at'));

        $task->setAttribute('title', 'B');
        $task->save();

        self::assertSame('B', Task::find($task->getKey())->getAttribute('title'));
    }

    public function testFirstOrCreateCreatesThenReturnsExisting(): void
    {
        $a = Task::firstOrCreate(['title' => 'Once'], ['status' => 'pending', 'tags' => []]);
        $b = Task::firstOrCreate(['title' => 'Once'], ['status' => 'done', 'tags' => []]);

        self::assertSame($a->getKey(), $b->getKey());
        self::assertSame(1, Task::query()->where('title', 'Once')->count());
    }

    public function testFirstOrNewDoesNotPersist(): void
    {
        $task = Task::firstOrNew(['title' => 'Draft'], ['status' => 'pending', 'tags' => []]);

        self::assertFalse($task->exists());
        self::assertSame('Draft', $task->getAttribute('title'));
        self::assertSame(0, Task::query()->where('title', 'Draft')->count());
    }

    public function testUpdateOrCreateUpdatesExisting(): void
    {
        Task::create(['title' => 'Edit', 'status' => 'pending', 'tags' => []]);

        $task = Task::updateOrCreate(['title' => 'Edit'], ['status' => 'done']);

        self::assertSame(Status::Done, $task->getAttribute('status'));
        self::assertSame(1, Task::query()->where('title', 'Edit')->count());
    }

    public function testFreshAndRefresh(): void
    {
        $task = Task::create(['title' => 'Orig', 'status' => 'pending', 'tags' => []]);

        // Mutate the row behind this instance's back.
        Task::query()->where('id', $task->getKey())->update(['title' => 'Changed']);

        self::assertSame('Orig', $task->getAttribute('title')); // stale in memory
        self::assertSame('Changed', $task->fresh()->getAttribute('title'));

        $task->refresh();
        self::assertSame('Changed', $task->getAttribute('title'));
    }

    public function testReplicateDropsKeyAndTimestamps(): void
    {
        $task = Task::create(['title' => 'Clone me', 'status' => 'pending', 'tags' => ['x']]);

        $copy = $task->replicate();

        self::assertFalse($copy->exists());
        self::assertNull($copy->getKey());
        self::assertSame('Clone me', $copy->getAttribute('title'));

        $copy->save();
        self::assertNotSame($task->getKey(), $copy->getKey());
        self::assertSame(2, Task::query()->count());
    }

    public function testLocalScopeViaStaticAndQuery(): void
    {
        Task::create(['title' => 'P', 'status' => 'pending', 'tags' => []]);
        Task::create(['title' => 'D', 'status' => 'done', 'tags' => []]);

        $doneStatic = Task::done()->get();
        self::assertCount(1, $doneStatic);
        self::assertSame('D', $doneStatic[0]->getAttribute('title'));

        self::assertSame(1, Task::query()->done()->count());
    }
}
