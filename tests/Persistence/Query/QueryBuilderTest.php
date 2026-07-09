<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Persistence\Query;

use InvalidArgumentException;
use LogicException;
use Middag\Framework\Database\PdoConnectionAdapter;
use Middag\Framework\Persistence\Query\Page;
use Middag\Framework\Persistence\Query\QueryBuilder;
use Middag\Framework\Persistence\Query\RelationRef;
use Middag\Framework\Shared\Enum\Operator;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(QueryBuilder::class)]
final class QueryBuilderTest extends TestCase
{
    // ---------------------------------------------------------------------
    // OFF mode — compilation
    // ---------------------------------------------------------------------

    public function testBareSelect(): void
    {
        self::assertSame('SELECT * FROM users', QueryBuilder::for('users')->toSql());
    }

    public function testWhereEqualityShortcut(): void
    {
        [$sql, $bindings] = QueryBuilder::for('users')->where('id', 5)->compile();

        self::assertSame('SELECT * FROM users WHERE id = ?', $sql);
        self::assertSame([5], $bindings);
    }

    public function testMultipleWheresAreAnded(): void
    {
        [$sql, $bindings] = QueryBuilder::for('users')->where('a', 1)->where('b', 2)->compile();

        self::assertSame('SELECT * FROM users WHERE a = ? AND b = ?', $sql);
        self::assertSame([1, 2], $bindings);
    }

    public function testOrWhere(): void
    {
        [$sql, $bindings] = QueryBuilder::for('users')->where('a', 1)->orWhere('b', 2)->compile();

        self::assertSame('SELECT * FROM users WHERE a = ? OR b = ?', $sql);
        self::assertSame([1, 2], $bindings);
    }

    public function testExplicitComparisonOperator(): void
    {
        [$sql, $bindings] = QueryBuilder::for('users')->where('age', Operator::Gt, 18)->compile();

        self::assertSame('SELECT * FROM users WHERE age > ?', $sql);
        self::assertSame([18], $bindings);
    }

    public function testStringComparisonOperator(): void
    {
        self::assertSame(
            'SELECT * FROM users WHERE name LIKE ?',
            QueryBuilder::for('users')->where('name', 'like', '%a%')->toSql(),
        );
    }

    public function testRejectsNonComparisonOperatorInWhere(): void
    {
        $this->expectException(InvalidArgumentException::class);
        QueryBuilder::for('users')->where('id', Operator::In, [1, 2]);
    }

    public function testWhereIn(): void
    {
        [$sql, $bindings] = QueryBuilder::for('users')->whereIn('id', [1, 2, 3])->compile();

        self::assertSame('SELECT * FROM users WHERE id IN (?, ?, ?)', $sql);
        self::assertSame([1, 2, 3], $bindings);
    }

    public function testWhereNotIn(): void
    {
        self::assertSame(
            'SELECT * FROM users WHERE id NOT IN (?, ?)',
            QueryBuilder::for('users')->whereNotIn('id', [1, 2])->toSql(),
        );
    }

    public function testEmptyWhereInMatchesNothing(): void
    {
        [$sql, $bindings] = QueryBuilder::for('users')->whereIn('id', [])->compile();

        self::assertSame('SELECT * FROM users WHERE 1 = 0', $sql);
        self::assertSame([], $bindings);
    }

    public function testWhereBetween(): void
    {
        [$sql, $bindings] = QueryBuilder::for('users')->whereBetween('age', 18, 30)->compile();

        self::assertSame('SELECT * FROM users WHERE age BETWEEN ? AND ?', $sql);
        self::assertSame([18, 30], $bindings);
    }

    public function testWhereNullAndNotNull(): void
    {
        self::assertSame(
            'SELECT * FROM users WHERE deleted_at IS NULL',
            QueryBuilder::for('users')->whereNull('deleted_at')->toSql(),
        );
        self::assertSame(
            'SELECT * FROM users WHERE deleted_at IS NOT NULL',
            QueryBuilder::for('users')->whereNotNull('deleted_at')->toSql(),
        );
    }

    public function testNestedWhereGroup(): void
    {
        [$sql, $bindings] = QueryBuilder::for('users')
            ->where(static fn (QueryBuilder $q): QueryBuilder => $q->where('a', 1)->orWhere('b', 2))
            ->where('c', 3)
            ->compile();

        self::assertSame('SELECT * FROM users WHERE (a = ? OR b = ?) AND c = ?', $sql);
        self::assertSame([1, 2, 3], $bindings);
    }

    public function testSelectAndDistinct(): void
    {
        self::assertSame('SELECT id, name FROM users', QueryBuilder::for('users')->select('id', 'name')->toSql());
        self::assertSame('SELECT DISTINCT id FROM users', QueryBuilder::for('users')->select('id')->distinct()->toSql());
    }

    public function testJoins(): void
    {
        self::assertSame(
            'SELECT * FROM users INNER JOIN roles ON users.role_id = roles.id',
            QueryBuilder::for('users')->join('roles', 'users.role_id', '=', 'roles.id')->toSql(),
        );
        self::assertSame(
            'SELECT * FROM users LEFT JOIN roles ON users.role_id = roles.id',
            QueryBuilder::for('users')->leftJoin('roles', 'users.role_id', '=', 'roles.id')->toSql(),
        );
    }

    public function testJoinRef(): void
    {
        $ref = new RelationRef(
            targetTable: 'roles',
            localField: 'role_id',
            targetField: 'id',
            defaultAlias: 'r',
            cardinality: RelationRef::CARDINALITY_MANY_TO_ONE,
            hostPolicy: RelationRef::HOST_POLICY_AGNOSTIC,
        );

        self::assertSame(
            'SELECT * FROM users INNER JOIN roles r ON r.id = users.role_id',
            QueryBuilder::for('users')->joinRef($ref)->toSql(),
        );
    }

    public function testOrderByLimitOffset(): void
    {
        self::assertSame(
            'SELECT * FROM users ORDER BY name asc, age desc LIMIT 10 OFFSET 20',
            QueryBuilder::for('users')->orderBy('name')->orderBy('age', 'desc')->limit(10)->offset(20)->toSql(),
        );
    }

    public function testOffsetWithoutLimit(): void
    {
        self::assertSame('SELECT * FROM users LIMIT -1 OFFSET 5', QueryBuilder::for('users')->offset(5)->toSql());
    }

    public function testForPage(): void
    {
        self::assertSame('SELECT * FROM users LIMIT 15 OFFSET 15', QueryBuilder::for('users')->forPage(2, 15)->toSql());
    }

    public function testGetBindings(): void
    {
        self::assertSame([1, 2], QueryBuilder::for('users')->where('a', 1)->where('b', 2)->getBindings());
    }

    public function testImmutability(): void
    {
        $base = QueryBuilder::for('users');
        $filtered = $base->where('a', 1);

        self::assertSame('SELECT * FROM users', $base->toSql());
        self::assertSame('SELECT * FROM users WHERE a = ?', $filtered->toSql());
    }

    public function testOffModeTerminalsThrow(): void
    {
        $this->expectException(LogicException::class);
        QueryBuilder::for('users')->get();
    }

    // ---------------------------------------------------------------------
    // ON mode — execution against in-memory sqlite
    // ---------------------------------------------------------------------

    public function testGetReturnsSeededRows(): void
    {
        $builder = $this->seededUsers();

        $rows = $builder->orderBy('id')->get();

        self::assertCount(3, $rows);
        self::assertSame('Ada', $rows[0]['name']);
    }

    public function testWhereExecutes(): void
    {
        $rows = $this->seededUsers()->where('active', 1)->get();

        self::assertCount(2, $rows);
    }

    public function testFirstAndFind(): void
    {
        $builder = $this->seededUsers();

        self::assertSame('Ada', $builder->orderBy('id')->first()['name']);
        self::assertSame('Linus', $builder->find(3)['name']);
        self::assertNull($builder->find(999));
    }

    public function testValueAndPluck(): void
    {
        $builder = $this->seededUsers();

        self::assertSame('Ada', $builder->orderBy('id')->value('name'));
        self::assertSame(['Ada', 'Grace', 'Linus'], $builder->orderBy('id')->pluck('name'));
        self::assertSame([1 => 'Ada', 2 => 'Grace', 3 => 'Linus'], $builder->orderBy('id')->pluck('name', 'id'));
    }

    public function testCountAndExists(): void
    {
        $builder = $this->seededUsers();

        self::assertSame(3, $builder->count());
        self::assertSame(2, $builder->where('active', 1)->count());
        self::assertTrue($builder->where('active', 1)->exists());
        self::assertFalse($builder->where('name', 'Nobody')->exists());
    }

    public function testPaginateReturnsPage(): void
    {
        $page = $this->seededUsers()->orderBy('id')->paginate(1, 2);

        self::assertInstanceOf(Page::class, $page);
        self::assertSame(3, $page->total());
        self::assertSame(2, $page->lastPage());
        self::assertCount(2, $page->items());
        self::assertSame('Ada', $page->items()[0]['name']);
    }

    public function testCountHonoursDistinct(): void
    {
        $builder = $this->seededUsers();
        // Seeded ages are 36/50/28 (all distinct); add a duplicate age.
        $builder->insert(['name' => 'Bjarne', 'age' => 36, 'active' => 1]);

        self::assertSame(4, $builder->count());
        self::assertSame(3, $builder->select('age')->distinct()->count());
    }

    public function testPaginateHonoursDistinctCount(): void
    {
        $builder = $this->seededUsers();
        $builder->insert(['name' => 'Bjarne', 'age' => 36, 'active' => 1]);

        $page = $builder->select('age')->distinct()->orderBy('age')->paginate(1, 2);

        self::assertSame(3, $page->total());
        self::assertCount(2, $page->items());
    }

    public function testDistinctCountCarriesWhereBindings(): void
    {
        $builder = $this->seededUsers();
        // active=1 → Ada(36), Linus(28); add Bjarne(36, active 1) → distinct ages {36,28} = 2.
        $builder->insert(['name' => 'Bjarne', 'age' => 36, 'active' => 1]);

        // The WHERE binding must flow into the distinct subquery exactly once.
        self::assertSame(2, $builder->where('active', 1)->select('age')->distinct()->count());
        self::assertSame(1, $builder->where('active', 0)->select('age')->distinct()->count());
    }

    public function testInsertUpdateDelete(): void
    {
        $builder = $this->seededUsers();

        $id = $builder->insert(['name' => 'Margaret', 'age' => 45, 'active' => 1]);
        self::assertGreaterThan(0, $id);
        self::assertSame('Margaret', $builder->find($id)['name']);

        $affected = $builder->where('id', $id)->update(['age' => 46]);
        self::assertSame(1, $affected);
        self::assertSame(46, (int) $builder->find($id)['age']);

        $deleted = $builder->where('id', $id)->delete();
        self::assertSame(1, $deleted);
        self::assertNull($builder->find($id));
    }

    public function testLockForUpdateAppendsClauseAfterLimit(): void
    {
        $sql = QueryBuilder::for('users')->where('active', 1)->limit(5)->lockForUpdate()->toSql();

        self::assertSame('SELECT * FROM users WHERE active = ? LIMIT 5 FOR UPDATE', $sql);
    }

    public function testSharedLockAppendsForShare(): void
    {
        $sql = QueryBuilder::for('users')->sharedLock()->toSql();

        self::assertSame('SELECT * FROM users FOR SHARE', $sql);
    }

    public function testNoLockClauseByDefault(): void
    {
        $sql = QueryBuilder::for('users')->toSql();

        self::assertStringNotContainsString('FOR UPDATE', $sql);
        self::assertStringNotContainsString('FOR SHARE', $sql);
    }

    public function testLockIsImmutable(): void
    {
        $base = QueryBuilder::for('users');
        $locked = $base->lockForUpdate();

        self::assertStringNotContainsString('FOR UPDATE', $base->toSql());
        self::assertStringContainsString('FOR UPDATE', $locked->toSql());
    }

    // ---------------------------------------------------------------------
    // Phase A — whereColumn / groupBy+having / orderByDesc / union (OFF)
    // ---------------------------------------------------------------------

    public function testWhereColumnComparesTwoColumnsWithoutBinding(): void
    {
        [$sql, $bindings] = QueryBuilder::for('events')->whereColumn('updated_at', '>', 'created_at')->compile();

        self::assertSame('SELECT * FROM events WHERE updated_at > created_at', $sql);
        self::assertSame([], $bindings);
    }

    public function testWhereColumnEqualityShortcut(): void
    {
        self::assertSame(
            'SELECT * FROM events WHERE a = b',
            QueryBuilder::for('events')->whereColumn('a', 'b')->toSql(),
        );
    }

    public function testOrWhereColumn(): void
    {
        [$sql, $bindings] = QueryBuilder::for('events')->where('x', 1)->orWhereColumn('a', 'b')->compile();

        self::assertSame('SELECT * FROM events WHERE x = ? OR a = b', $sql);
        self::assertSame([1], $bindings);
    }

    public function testWhereColumnRejectsNonComparisonOperator(): void
    {
        $this->expectException(InvalidArgumentException::class);
        QueryBuilder::for('events')->whereColumn('a', 'IN', 'b');
    }

    public function testOrderByDesc(): void
    {
        self::assertSame(
            'SELECT * FROM users ORDER BY created_at desc',
            QueryBuilder::for('users')->orderByDesc('created_at')->toSql(),
        );
    }

    public function testGroupByAndHaving(): void
    {
        [$sql, $bindings] = QueryBuilder::for('orders')
            ->select('customer_id', 'COUNT(*) AS total')
            ->groupBy('customer_id')
            ->having('total', '>', 5)
            ->compile();

        self::assertSame('SELECT customer_id, COUNT(*) AS total FROM orders GROUP BY customer_id HAVING total > ?', $sql);
        self::assertSame([5], $bindings);
    }

    public function testGroupByMultipleColumns(): void
    {
        self::assertSame(
            'SELECT * FROM orders GROUP BY customer_id, status',
            QueryBuilder::for('orders')->groupBy('customer_id', 'status')->toSql(),
        );
    }

    public function testHavingEqualityShortcutAndOrHaving(): void
    {
        [$sql, $bindings] = QueryBuilder::for('orders')
            ->groupBy('status')
            ->having('total', 10)
            ->orHaving('total', '<', 2)
            ->compile();

        self::assertSame('SELECT * FROM orders GROUP BY status HAVING total = ? OR total < ?', $sql);
        self::assertSame([10, 2], $bindings);
    }

    public function testClauseOrderingWhereGroupHavingOrderLimit(): void
    {
        [$sql, $bindings] = QueryBuilder::for('orders')
            ->where('active', 1)
            ->groupBy('customer_id')
            ->having('total', '>', 5)
            ->orderByDesc('total')
            ->limit(10)
            ->compile();

        self::assertSame(
            'SELECT * FROM orders WHERE active = ? GROUP BY customer_id HAVING total > ? ORDER BY total desc LIMIT 10',
            $sql,
        );
        self::assertSame([1, 5], $bindings);
    }

    public function testUnionConcatenatesSelectsAndBindings(): void
    {
        [$sql, $bindings] = QueryBuilder::for('a')->where('x', 1)
            ->union(QueryBuilder::for('b')->where('y', 2))
            ->compile();

        self::assertSame('SELECT * FROM a WHERE x = ? UNION SELECT * FROM b WHERE y = ?', $sql);
        self::assertSame([1, 2], $bindings);
    }

    public function testUnionAll(): void
    {
        self::assertSame(
            'SELECT * FROM a UNION ALL SELECT * FROM b',
            QueryBuilder::for('a')->unionAll(QueryBuilder::for('b'))->toSql(),
        );
    }

    // ---------------------------------------------------------------------
    // Phase A — aggregates / insertGetId / updateOrInsert / group (ON, sqlite)
    // ---------------------------------------------------------------------

    public function testAggregatesSumAvgMinMax(): void
    {
        $builder = $this->seededUsers(); // ages 36, 50, 28

        self::assertSame(114, $builder->sum('age'));
        self::assertSame(38.0, $builder->avg('age'));
        self::assertSame(28, (int) $builder->min('age'));
        self::assertSame(50, (int) $builder->max('age'));
    }

    public function testAggregateHonoursWhere(): void
    {
        $builder = $this->seededUsers(); // active=1 → Ada(36), Linus(28)

        self::assertSame(64, $builder->where('active', 1)->sum('age'));
    }

    public function testSumOfNoRowsReturnsZero(): void
    {
        self::assertSame(0, $this->seededUsers()->where('name', 'Nobody')->sum('age'));
    }

    public function testMinAvgOfNoRowsReturnNull(): void
    {
        $builder = $this->seededUsers()->where('name', 'Nobody');

        self::assertNull($builder->min('age'));
        self::assertNull($builder->avg('age'));
    }

    public function testInsertGetIdReturnsNewKey(): void
    {
        $builder = $this->seededUsers();

        $id = $builder->insertGetId(['name' => 'Margaret', 'age' => 45, 'active' => 1]);

        self::assertGreaterThan(0, $id);
        self::assertSame('Margaret', $builder->find($id)['name']);
    }

    public function testUpdateOrInsertInsertsWhenAbsent(): void
    {
        $builder = $this->seededUsers();

        $result = $builder->updateOrInsert(['name' => 'Margaret'], ['age' => 45, 'active' => 1]);

        self::assertTrue($result);
        self::assertSame(45, (int) $builder->where('name', 'Margaret')->first()['age']);
        self::assertSame(4, $builder->count());
    }

    public function testUpdateOrInsertUpdatesWhenPresent(): void
    {
        $builder = $this->seededUsers();

        $result = $builder->updateOrInsert(['name' => 'Ada'], ['age' => 99]);

        self::assertTrue($result);
        self::assertSame(99, (int) $builder->where('name', 'Ada')->first()['age']);
        self::assertSame(3, $builder->count()); // no new row inserted
    }

    public function testGroupByHavingExecutes(): void
    {
        $builder = $this->seededUsers(); // active=1 → 2 rows (Ada, Linus); active=0 → 1 (Grace)

        // HAVING references the aggregate expression, not the SELECT alias:
        // alias-in-HAVING is a MySQL extension SQLite/PostgreSQL reject.
        $rows = $builder
            ->select('active', 'COUNT(*) AS total')
            ->groupBy('active')
            ->having('COUNT(*)', '>', 1)
            ->get();

        self::assertCount(1, $rows);
        self::assertSame(2, (int) $rows[0]['total']);
        self::assertSame(1, (int) $rows[0]['active']);
    }

    public function testUnionExecutes(): void
    {
        $builder = $this->seededUsers();

        // The unioned builder is OFF-mode: only its compiled SQL is used; the
        // whole UNION executes on the base builder's connection.
        $rows = $builder->where('name', 'Ada')
            ->union(QueryBuilder::for('users')->where('name', 'Grace'))
            ->get();

        self::assertCount(2, $rows);
    }

    // ---------------------------------------------------------------------
    // Phase B — streaming: cursor / chunk / lazy (ON, sqlite)
    // ---------------------------------------------------------------------

    public function testCursorStreamsRows(): void
    {
        $names = [];
        foreach ($this->seededUsers()->orderBy('id')->cursor() as $row) {
            $names[] = $row['name'];
        }

        self::assertSame(['Ada', 'Grace', 'Linus'], $names);
    }

    public function testCursorThrowsInOffMode(): void
    {
        $this->expectException(LogicException::class);
        QueryBuilder::for('users')->cursor();
    }

    public function testChunkIteratesInPages(): void
    {
        $pages = [];
        $result = $this->seededUsers()->orderBy('id')->chunk(2, static function (array $rows) use (&$pages): void {
            $pages[] = count($rows);
        });

        self::assertTrue($result);
        self::assertSame([2, 1], $pages);
    }

    public function testChunkStopsWhenCallbackReturnsFalse(): void
    {
        $seen = 0;
        $result = $this->seededUsers()->orderBy('id')->chunk(1, static function (array $rows) use (&$seen): bool {
            ++$seen;

            return false;
        });

        self::assertFalse($result);
        self::assertSame(1, $seen);
    }

    public function testLazyYieldsEveryRow(): void
    {
        $names = [];
        foreach ($this->seededUsers()->orderBy('id')->lazy(2) as $row) {
            $names[] = $row['name'];
        }

        self::assertSame(['Ada', 'Grace', 'Linus'], $names);
    }

    // ---------------------------------------------------------------------
    // Phase C — upsert (ON, sqlite ON CONFLICT)
    // ---------------------------------------------------------------------

    public function testUpsertInsertsThenUpdatesOnConflict(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('CREATE TABLE settings (skey TEXT PRIMARY KEY, val TEXT)');

        $builder = QueryBuilder::on(new PdoConnectionAdapter($pdo), 'settings');

        $builder->upsert(['skey' => 'theme', 'val' => 'dark'], 'skey');
        self::assertSame('dark', $builder->where('skey', 'theme')->value('val'));

        // Conflict on the primary key updates the value instead of inserting.
        $builder->upsert(['skey' => 'theme', 'val' => 'light'], 'skey');
        self::assertSame('light', $builder->where('skey', 'theme')->value('val'));
        self::assertSame(1, $builder->count());

        // Bulk upsert: one conflict (theme) + one insert (lang).
        $builder->upsert(
            [['skey' => 'theme', 'val' => 'auto'], ['skey' => 'lang', 'val' => 'pt']],
            'skey',
        );
        self::assertSame('auto', $builder->where('skey', 'theme')->value('val'));
        self::assertSame('pt', $builder->where('skey', 'lang')->value('val'));
        self::assertSame(2, $builder->count());
    }

    public function testOrWhereThreeArgUsesTheOperator(): void
    {
        [$sql, $bindings] = QueryBuilder::for('users')->where('a', 1)->orWhere('b', '>', 2)->compile();

        self::assertSame('SELECT * FROM users WHERE a = ? OR b > ?', $sql);
        self::assertSame([1, 2], $bindings);
    }

    public function testOrWhereClosureNestsAnOrGroup(): void
    {
        [$sql, $bindings] = QueryBuilder::for('users')
            ->where('a', 1)
            ->orWhere(static fn (QueryBuilder $q): QueryBuilder => $q->where('b', 2)->where('c', 3))
            ->compile();

        self::assertSame('SELECT * FROM users WHERE a = ? OR (b = ? AND c = ?)', $sql);
        self::assertSame([1, 2, 3], $bindings);
    }

    public function testOrWhereWithoutValueThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        QueryBuilder::for('users')->where('a', 1)->orWhere('b');
    }

    public function testOrWhereIn(): void
    {
        [$sql, $bindings] = QueryBuilder::for('users')->where('a', 1)->orWhereIn('id', [7, 8])->compile();

        self::assertSame('SELECT * FROM users WHERE a = ? OR id IN (?, ?)', $sql);
        self::assertSame([1, 7, 8], $bindings);
    }

    public function testWhereColumnWithoutSecondColumnThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        QueryBuilder::for('users')->whereColumn('a');
    }

    public function testOrWhereColumnTwoAndThreeArg(): void
    {
        $two = QueryBuilder::for('users')->where('x', 1)->orWhereColumn('a', 'b')->toSql();
        self::assertSame('SELECT * FROM users WHERE x = ? OR a = b', $two);

        $three = QueryBuilder::for('users')->where('x', 1)->orWhereColumn('a', '>', 'b')->toSql();
        self::assertSame('SELECT * FROM users WHERE x = ? OR a > b', $three);
    }

    public function testAddSelectAppendsColumns(): void
    {
        self::assertSame('SELECT a, b, c FROM users', QueryBuilder::for('users')->select('a')->addSelect('b', 'c')->toSql());
        // From the bare `*` baseline, addSelect replaces the star.
        self::assertSame('SELECT a FROM users', QueryBuilder::for('users')->addSelect('a')->toSql());
    }

    public function testLatestOrdersByColumnDescending(): void
    {
        self::assertSame('SELECT * FROM users ORDER BY created desc', QueryBuilder::for('users')->latest('created')->toSql());
    }

    public function testOrderByRejectsUnknownDirection(): void
    {
        $this->expectException(InvalidArgumentException::class);
        QueryBuilder::for('users')->orderBy('id', 'sideways');
    }

    public function testHavingAndOrHavingCompile(): void
    {
        [$sql, $bindings] = QueryBuilder::for('users')
            ->select('active', 'COUNT(*) AS c')
            ->groupBy('active')
            ->having('c', '>', 1)
            ->orHaving('c', '=', 0)
            ->compile();

        self::assertStringContainsString('GROUP BY active', $sql);
        self::assertStringContainsString('HAVING c > ? OR c = ?', $sql);
        self::assertSame([1, 0], $bindings);
    }

    public function testGetConnectionReflectsMode(): void
    {
        self::assertNull(QueryBuilder::for('users')->getConnection());
        self::assertNotNull($this->seededUsers()->getConnection());
    }

    public function testUpdateAffectsMatchingRows(): void
    {
        $builder = $this->seededUsers();

        self::assertSame(1, $builder->where('id', 1)->update(['name' => 'Renamed']));
        self::assertSame('Renamed', $builder->find(1)['name']);
        self::assertSame(0, $builder->where('id', 1)->update([]), 'empty update is a no-op');
    }

    public function testUpdateOrInsertUpdatesThenInserts(): void
    {
        $builder = $this->seededUsers();

        // Existing row (id 1 / Ada) → update path.
        self::assertTrue($builder->updateOrInsert(['name' => 'Ada'], ['age' => 99]));
        self::assertSame(99, (int) $builder->where('name', 'Ada')->first()['age']);

        // No match → insert path.
        self::assertTrue($builder->updateOrInsert(['name' => 'Zed'], ['age' => 20, 'active' => 1]));
        self::assertNotNull($builder->where('name', 'Zed')->first());
    }

    public function testUpsertInsertsAndUpdatesOnConflict(): void
    {
        $builder = $this->seededUsers();

        // id 1 exists → update; id 4 is new → insert.
        $builder->upsert(
            [
                ['id' => 1, 'name' => 'Ada II', 'age' => 40, 'active' => 1],
                ['id' => 4, 'name' => 'Margaret', 'age' => 45, 'active' => 1],
            ],
            'id',
        );

        self::assertSame('Ada II', $builder->find(1)['name']);
        self::assertSame('Margaret', $builder->find(4)['name']);
    }

    public function testCursorStreamsEachRow(): void
    {
        $names = [];
        foreach ($this->seededUsers()->orderBy('id')->cursor() as $row) {
            $names[] = $row['name'];
        }

        self::assertSame(['Ada', 'Grace', 'Linus'], $names);
    }

    public function testChunkProcessesEveryBatch(): void
    {
        $seen = 0;
        $result = $this->seededUsers()->orderBy('id')->chunk(2, function (array $rows) use (&$seen): bool {
            $seen += count($rows);

            return true;
        });

        self::assertTrue($result);
        self::assertSame(3, $seen);
    }

    public function testNumericAggregateReturnsNullWhenNoRows(): void
    {
        self::assertNull($this->seededUsers()->where('id', 999)->avg('age'));
    }

    public function testSumMinMaxAggregate(): void
    {
        $builder = $this->seededUsers();

        self::assertSame(114, (int) $builder->sum('age'));
        self::assertSame(28, (int) $builder->min('age'));
        self::assertSame(50, (int) $builder->max('age'));
    }

    public function testAvgReturnsAFloat(): void
    {
        // (36 + 50 + 28) / 3 = 38.0 — the string-numeric float branch of the
        // aggregate coercion.
        self::assertEqualsWithDelta(38.0, $this->seededUsers()->avg('age'), 0.001);
    }

    public function testOldestOrdersAscending(): void
    {
        self::assertSame('SELECT * FROM users ORDER BY created asc', QueryBuilder::for('users')->oldest('created')->toSql());
    }

    public function testGetTableExposesTheTable(): void
    {
        self::assertSame('users', QueryBuilder::for('users')->getTable());
    }

    public function testChunkStopsImmediatelyOnAnEmptyResult(): void
    {
        $called = false;
        $result = $this->seededUsers()->where('id', 999)->chunk(2, function () use (&$called): bool {
            $called = true;

            return true;
        });

        self::assertTrue($result);
        self::assertFalse($called, 'the callback never runs when the first page is empty');
    }

    public function testWhereClosureMustReturnTheBuilder(): void
    {
        $this->expectException(InvalidArgumentException::class);
        // A where-group closure that does not return the builder is rejected.
        QueryBuilder::for('users')->where(static function (QueryBuilder $q): void {
            $q->where('a', 1);
        });
    }

    public function testUpdateOrInsertOnMatchWithNoValuesReturnsTrue(): void
    {
        // Existing row + empty $values → the early "nothing to change" true path.
        self::assertTrue($this->seededUsers()->updateOrInsert(['name' => 'Ada']));
    }

    public function testUpsertWithNoRowsIsANoOp(): void
    {
        self::assertSame(0, $this->seededUsers()->upsert([], 'id'));
    }

    public function testHavingAndOrHavingTwoArgShortcut(): void
    {
        [$sql, $bindings] = QueryBuilder::for('users')
            ->select('active', 'COUNT(*) AS c')
            ->groupBy('active')
            ->having('c', 1)
            ->orHaving('c', 2)
            ->compile();

        self::assertStringContainsString('HAVING c = ? OR c = ?', $sql);
        self::assertSame([1, 2], $bindings);
    }

    private function seededUsers(): QueryBuilder
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, age INTEGER, active INTEGER)');
        $pdo->exec("INSERT INTO users (name, age, active) VALUES ('Ada', 36, 1), ('Grace', 50, 0), ('Linus', 28, 1)");

        return QueryBuilder::on(new PdoConnectionAdapter($pdo), 'users');
    }
}
