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

use LogicException;
use Middag\Framework\Database\PdoConnectionAdapter;
use Middag\Framework\Exception\MiddagNotFoundException;
use Middag\Framework\Persistence\Model;
use Middag\Framework\Persistence\ModelQuery;
use Middag\Framework\Persistence\Query\Page;
use Middag\Framework\Persistence\Query\QueryBuilder;
use Middag\Framework\Tests\Persistence\Fixture\NoTableModel;
use Middag\Framework\Tests\Persistence\Fixture\Widget;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Model::class)]
#[CoversClass(ModelQuery::class)]
final class ModelTest extends TestCase
{
    private PdoConnectionAdapter $adapter;

    protected function setUp(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('CREATE TABLE widgets (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, price REAL, active INTEGER)');
        $pdo->exec("INSERT INTO widgets (name, price, active) VALUES ('Gear', 9.5, 1), ('Cog', 3.0, 0)");

        $this->adapter = new PdoConnectionAdapter($pdo);
        Model::setConnection($this->adapter);
    }

    protected function tearDown(): void
    {
        Model::setConnectionResolver(null);
    }

    public function testAllReturnsHydratedModels(): void
    {
        $widgets = Widget::all();

        self::assertCount(2, $widgets);
        self::assertContainsOnlyInstancesOf(Widget::class, $widgets);
    }

    public function testFindReturnsTypedModelOrNull(): void
    {
        $widget = Widget::find(1);

        self::assertInstanceOf(Widget::class, $widget);
        self::assertSame('Gear', $widget->getAttribute('name'));
        self::assertNull(Widget::find(999));
    }

    public function testFindOrFailThrowsWhenMissing(): void
    {
        $this->expectException(MiddagNotFoundException::class);
        Widget::findOrFail(999);
    }

    public function testWhereFiltersAndReturnsModels(): void
    {
        $active = Widget::where('active', 1)->get();

        self::assertCount(1, $active);
        self::assertSame('Gear', $active[0]->getAttribute('name'));
    }

    public function testWhereWithSingleClosureArgumentBuildsNestedGroup(): void
    {
        // static::where() with 1 arg (the func_num_args() === 1 branch) forwards the closure as-is,
        // which ModelQuery/QueryBuilder treat as a nested where-group rather than a column name.
        $active = Widget::where(static fn (QueryBuilder $query): QueryBuilder => $query->where('active', 1))->get();

        self::assertCount(1, $active);
        self::assertSame('Gear', $active[0]->getAttribute('name'));
    }

    public function testFirstWithOrdering(): void
    {
        $widget = Widget::query()->orderBy('price', 'asc')->first();

        self::assertInstanceOf(Widget::class, $widget);
        self::assertSame('Cog', $widget->getAttribute('name'));
    }

    public function testCastsAreApplied(): void
    {
        $gear = Widget::find(1);
        $cog = Widget::find(2);

        self::assertSame(1, $gear->getAttribute('id'));
        self::assertSame(9.5, $gear->getAttribute('price'));
        self::assertTrue($gear->getAttribute('active'));
        self::assertFalse($cog->getAttribute('active'));
    }

    public function testSaveInsertsNewModel(): void
    {
        $widget = new Widget(['name' => 'Bolt', 'price' => 1.5, 'active' => 1]);

        self::assertFalse($widget->exists());
        self::assertTrue($widget->save());
        self::assertTrue($widget->exists());
        self::assertGreaterThan(0, $widget->getKey());
        self::assertSame('Bolt', Widget::find($widget->getKey())->getAttribute('name'));
    }

    public function testSaveUpdatesExistingModel(): void
    {
        $widget = Widget::find(1);
        $widget->setAttribute('price', 99.0);

        self::assertTrue($widget->save());
        self::assertSame(99.0, Widget::find(1)->getAttribute('price'));
        self::assertCount(2, Widget::all());
    }

    public function testDeleteRemovesModel(): void
    {
        $widget = Widget::find(2);

        self::assertTrue($widget->delete());
        self::assertFalse($widget->exists());
        self::assertNull(Widget::find(2));
        self::assertCount(1, Widget::all());
    }

    public function testMassAssignmentRespectsFillable(): void
    {
        $widget = new Widget(['name' => 'Sprocket', 'id' => 123, 'secret' => 'x']);

        self::assertSame('Sprocket', $widget->getAttribute('name'));
        self::assertNull($widget->getAttribute('id'));
        self::assertNull($widget->getAttribute('secret'));
    }

    public function testToArrayAndJsonSerializeUseCasts(): void
    {
        $gear = Widget::find(1);

        self::assertSame(
            ['id' => 1, 'name' => 'Gear', 'price' => 9.5, 'active' => true],
            $gear->toArray(),
        );
        self::assertSame($gear->toArray(), $gear->jsonSerialize());
    }

    public function testPaginateReturnsModelsInPage(): void
    {
        $page = Widget::query()->orderBy('id')->paginate(1, 1);

        self::assertInstanceOf(Page::class, $page);
        self::assertSame(2, $page->total());
        self::assertSame(2, $page->lastPage());
        self::assertCount(1, $page->items());
        self::assertInstanceOf(Widget::class, $page->items()[0]);
        self::assertSame('Gear', $page->items()[0]->getAttribute('name'));
    }

    public function testGetTableThrowsWhenUnset(): void
    {
        $this->expectException(LogicException::class);
        (new NoTableModel())->getTable();
    }

    public function testResolveConnectionThrowsWithoutResolver(): void
    {
        Model::setConnectionResolver(null);

        $this->expectException(LogicException::class);
        Widget::all();
    }

    public function testOnConnectionBypassesGlobalResolver(): void
    {
        Model::setConnectionResolver(null);

        $widgets = Widget::onConnection($this->adapter)->orderBy('id')->get();

        self::assertCount(2, $widgets);
        self::assertSame('Gear', $widgets[0]->getAttribute('name'));
    }

    public function testAggregatesThroughModelQuery(): void
    {
        self::assertSame(12.5, Widget::query()->sum('price'));
        self::assertSame(6.25, Widget::query()->avg('price'));
        self::assertSame(3.0, (float) Widget::query()->min('price'));
        self::assertSame(9.5, (float) Widget::query()->max('price'));
    }

    public function testQueryBuilderPassthroughsThroughModelQuery(): void
    {
        $byPriceDesc = Widget::query()->orderByDesc('price')->get();
        self::assertSame('Gear', $byPriceDesc[0]->getAttribute('name'));

        // whereColumn: Gear id(1) > active(1) is false; Cog id(2) > active(0) is true.
        $cog = Widget::query()->whereColumn('id', '>', 'active')->get();
        self::assertCount(1, $cog);
        self::assertSame('Cog', $cog[0]->getAttribute('name'));

        $groups = Widget::query()->select('active', 'COUNT(*) AS total')->groupBy('active')->get();
        self::assertCount(2, $groups);
    }

    public function testCursorThroughModelQueryHydratesModels(): void
    {
        $models = [];
        foreach (Widget::query()->orderBy('id')->cursor() as $widget) {
            $models[] = $widget;
        }

        self::assertCount(2, $models);
        self::assertContainsOnlyInstancesOf(Widget::class, $models);
        self::assertSame('Gear', $models[0]->getAttribute('name'));
    }
}
