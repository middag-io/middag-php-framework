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

use DateTime;
use DateTimeImmutable;
use InvalidArgumentException;
use Middag\Framework\Database\PdoConnectionAdapter;
use Middag\Framework\Persistence\Model;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Covers the attribute cast matrix, the magic accessors, mass-assignment
 * gating and connection-resolver plumbing — the parts of the active record
 * that need no relationships to exercise.
 *
 * @internal
 */
#[CoversClass(Model::class)]
final class ModelCastingTest extends TestCase
{
    protected function tearDown(): void
    {
        Model::setConnectionResolver(null);
    }

    #[Test]
    public function stringCastCoercesScalarsAndRejectsNonScalar(): void
    {
        $model = $this->casting();

        $model->setAttribute('s', 123);
        self::assertSame('123', $model->getAttribute('s'));

        $model->setAttribute('s', 'kept');
        self::assertSame('kept', $model->getAttribute('s'));

        $model->setAttribute('s', ['not', 'scalar']);
        $this->expectException(InvalidArgumentException::class);
        $model->getAttribute('s');
    }

    #[Test]
    public function datetimeCastAcceptsStringIntNumericAndDateTimeInterface(): void
    {
        $model = $this->casting();

        $model->setAttribute('dt', '2026-01-02 03:04:05');
        self::assertSame('2026-01-02 03:04:05', $model->getAttribute('dt')->format('Y-m-d H:i:s'));

        $model->setAttribute('dt', 1_700_000_000);
        self::assertInstanceOf(DateTimeImmutable::class, $model->getAttribute('dt'));

        $model->setAttribute('dt', '1700000000');
        self::assertInstanceOf(DateTimeImmutable::class, $model->getAttribute('dt'));

        $immutable = new DateTimeImmutable('2026-05-01');
        $model->setAttribute('dt', $immutable);
        self::assertSame($immutable, $model->getAttribute('dt'));

        $model->setAttribute('dt', new DateTime('2026-06-01 12:00:00'));
        self::assertSame('2026-06-01 12:00:00', $model->getAttribute('dt')->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function datetimeCastRejectsNonCastableValue(): void
    {
        $model = $this->casting();
        $model->setAttribute('dt', ['bad']);

        $this->expectException(InvalidArgumentException::class);
        $model->getAttribute('dt');
    }

    #[Test]
    public function arrayCastDecodesJsonPassesArraysAndDefaultsEmpty(): void
    {
        $model = $this->casting();

        $model->setAttribute('j', '["a","b"]');
        self::assertSame(['a', 'b'], $model->getAttribute('j'));

        $model->setAttribute('j', ['x']);
        self::assertSame(['x'], $model->getAttribute('j'));

        $model->setAttribute('j', '');
        self::assertSame([], $model->getAttribute('j'));
    }

    #[Test]
    public function nullValuesAndUnknownKeysBypassCasting(): void
    {
        $model = $this->casting();

        $model->setAttribute('dt', null);
        self::assertNull($model->getAttribute('dt'));

        // No cast declared for this key → value passes through unchanged.
        $model->setAttribute('raw', 42);
        self::assertSame(42, $model->getAttribute('raw'));

        // Absent key → null.
        self::assertNull($model->getAttribute('missing'));
    }

    #[Test]
    public function magicAccessorsReadWriteAndProbeAttributes(): void
    {
        $model = $this->casting();

        $model->s = 7;                       // __set
        self::assertSame('7', $model->s);    // __get (through the string cast)
        self::assertTrue(isset($model->s));  // __isset
        self::assertFalse(isset($model->never));
    }

    #[Test]
    public function guardedStarLocksEveryAttribute(): void
    {
        $model = new class extends Model {
            protected string $table = 'g';

            /** @var list<string> */
            protected array $guarded = ['*'];
        };

        $model->fill(['anything' => 'x']);

        self::assertNull($model->getAttribute('anything'));
    }

    #[Test]
    public function guardedListBlocksOnlyListedKeys(): void
    {
        $model = new class extends Model {
            protected string $table = 'g';

            /** @var list<string> */
            protected array $guarded = ['locked'];
        };

        $model->fill(['locked' => 'no', 'open' => 'yes']);

        self::assertNull($model->getAttribute('locked'));
        self::assertSame('yes', $model->getAttribute('open'));
    }

    #[Test]
    public function connectionResolverIsReadableAndClearable(): void
    {
        $pdo = new PDO('sqlite::memory:');
        Model::setConnection(new PdoConnectionAdapter($pdo));
        self::assertNotNull(Model::getConnectionResolver());

        Model::setConnectionResolver(null);
        self::assertNull(Model::getConnectionResolver());
    }

    /**
     * A model exposing the string/datetime/json casts, no DB needed — casts
     * apply on read via getAttribute().
     */
    private function casting(): Model
    {
        return new class extends Model {
            protected string $table = 'casting';

            /** @var list<string> */
            protected array $guarded = [];

            /** @var array<string, string> */
            protected array $casts = [
                's' => 'string',
                'dt' => 'datetime',
                'j' => 'json',
            ];
        };
    }
}
