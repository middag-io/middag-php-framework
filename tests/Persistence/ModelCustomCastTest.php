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
use Middag\Framework\Persistence\Contract\CastInterface;
use Middag\Framework\Persistence\Model;
use Middag\Framework\Tests\Persistence\Fixture\Money;
use Middag\Framework\Tests\Persistence\Fixture\MoneyCast;
use Middag\Framework\Tests\Persistence\Fixture\PrefixCast;
use Middag\Framework\Tests\Persistence\Fixture\Product;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use RuntimeException;

/**
 * Consumer-defined casts via `$casts` (issue #62): the CastInterface seam
 * beyond the fixed built-in list. Get/set round-trips, coexistence with
 * built-ins, container-vs-new instantiation, and per-FQCN caching.
 *
 * @internal
 */
#[CoversClass(Model::class)]
final class ModelCustomCastTest extends TestCase
{
    protected function tearDown(): void
    {
        Model::setCastResolver(null);
        Model::setConnectionResolver(null);
    }

    #[Test]
    public function customCastTransformsDbValueToPhpOnRead(): void
    {
        $model = $this->withCast(['price' => MoneyCast::class]);
        $model->setAttribute('price', 1500);

        $value = $model->getAttribute('price');
        self::assertInstanceOf(Money::class, $value);
        self::assertSame(1500, $value->cents);
    }

    #[Test]
    public function builtinCastsKeepWorkingAlongsideACustomCast(): void
    {
        $model = $this->withCast(['price' => MoneyCast::class, 'qty' => 'int', 'meta' => 'json']);

        $model->setAttribute('qty', '7');
        $model->setAttribute('meta', '{"a":1}');

        self::assertSame(7, $model->getAttribute('qty'));
        self::assertSame(['a' => 1], $model->getAttribute('meta'));
    }

    #[Test]
    public function nullAndUncastKeysBypassTheCustomCast(): void
    {
        $model = $this->withCast(['price' => MoneyCast::class]);

        $model->setAttribute('price', null);
        self::assertNull($model->getAttribute('price'), 'null passes through uncast');

        $model->setAttribute('other', 'raw');
        self::assertSame('raw', $model->getAttribute('other'), 'a key without a cast is untouched');
    }

    #[Test]
    public function customCastRoundTripsThroughTheDatabaseWithBuiltinCasts(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE products (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, price INTEGER)');
        Model::setConnection(new PdoConnectionAdapter($pdo));

        // Money (PHP) -> int cents (DB) on write via CastInterface::set().
        $created = Product::create(['name' => 'Widget', 'price' => new Money(2599)]);
        self::assertSame(2599, (int) $pdo->query('SELECT price FROM products WHERE id = 1')->fetchColumn());

        // int cents (DB) -> Money (PHP) on read via CastInterface::get(), while
        // the built-in id => int cast still applies.
        $found = Product::findOrFail($created->getKey());
        self::assertSame(1, $found->getAttribute('id'));
        self::assertInstanceOf(Money::class, $found->getAttribute('price'));
        self::assertSame(2599, $found->getAttribute('price')->cents);
    }

    #[Test]
    public function castWithConstructorDependencyIsBuiltFromTheContainer(): void
    {
        $calls = ['count' => 0];
        Model::setCastResolver($this->container($calls));

        $model = $this->withCast(['label' => PrefixCast::class]);
        $model->setAttribute('label', 'x');

        // PrefixCast has a required $prefix arg — only the container can build it.
        self::assertSame('PRE:x', $model->getAttribute('label'));
    }

    #[Test]
    public function containerBuiltCastIsCachedPerFqcnAndBuiltOnce(): void
    {
        $calls = ['count' => 0];
        Model::setCastResolver($this->container($calls));

        $model = $this->withCast(['label' => PrefixCast::class]);
        $model->setAttribute('label', 'a');
        $model->getAttribute('label');
        $model->setAttribute('label', 'b');
        $model->getAttribute('label');

        self::assertSame(1, $calls['count'], 'the container builds the cast exactly once across reads');
    }

    #[Test]
    public function newFallbackBuildsZeroArgCastWithoutAContainer(): void
    {
        self::assertNull(Model::getConnectionResolver());

        $model = $this->withCast(['price' => MoneyCast::class]);
        $model->setAttribute('price', 99);

        self::assertSame(99, $model->getAttribute('price')->cents);
    }

    /**
     * A cast-carrying model with no DB. Casts apply on read via getAttribute().
     *
     * @param array<string, string> $casts
     */
    private function withCast(array $casts): Model
    {
        return new class($casts) extends Model {
            protected string $table = 'casting';

            /** @var list<string> */
            protected array $guarded = [];

            /**
             * @param array<string, string> $castMap
             */
            public function __construct(array $castMap)
            {
                $this->casts = $castMap;
                parent::__construct();
            }
        };
    }

    /**
     * A container that builds PrefixCast with a fixed prefix and counts builds
     * through the passed-by-reference $calls array.
     *
     * @param array{count: int} $calls
     */
    private function container(array &$calls): ContainerInterface
    {
        return new class($calls) implements ContainerInterface {
            /**
             * @param array{count: int} $calls
             */
            public function __construct(private array &$calls) {}

            public function get(string $id): mixed
            {
                if ($id === PrefixCast::class) {
                    ++$this->calls['count'];

                    return new PrefixCast('PRE:');
                }

                throw new RuntimeException('unexpected service ' . $id);
            }

            public function has(string $id): bool
            {
                return is_a($id, CastInterface::class, true);
            }
        };
    }
}
