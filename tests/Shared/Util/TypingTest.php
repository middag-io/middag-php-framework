<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Shared\Util;

use LogicException;
use Middag\Framework\Shared\Util\Typing;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;
use Stringable;

/**
 * @internal
 */
#[CoversClass(Typing::class)]
final class TypingTest extends TestCase
{
    #[Test]
    #[DataProvider('toIntProvider')]
    public function toIntNormalizesScalars(mixed $value, ?int $expected): void
    {
        self::assertSame($expected, Typing::toInt($value));
    }

    /**
     * @return iterable<string, array{mixed, null|int}>
     */
    public static function toIntProvider(): iterable
    {
        yield 'null becomes null' => [null, null];

        yield 'empty string becomes null' => ['', null];

        yield 'true becomes 1' => [true, 1];

        yield 'false becomes 0' => [false, 0];

        yield 'numeric string' => ['10', 10];

        yield 'integer passthrough' => [10, 10];

        yield 'float is truncated' => [10.9, 10];

        yield 'negative numeric string' => ['-4', -4];
    }

    #[Test]
    public function toIntThrowsOnNonNumericString(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Typing::toInt()');

        Typing::toInt('10abc');
    }

    #[Test]
    #[DataProvider('toPositiveIntProvider')]
    public function toPositiveIntKeepsOnlyPositives(mixed $value, ?int $expected): void
    {
        self::assertSame($expected, Typing::toPositiveInt($value));
    }

    /**
     * @return iterable<string, array{mixed, null|int}>
     */
    public static function toPositiveIntProvider(): iterable
    {
        yield 'positive stays positive' => ['5', 5];

        yield 'zero becomes null' => [0, null];

        yield 'negative becomes null' => [-1, null];

        yield 'null stays null' => [null, null];
    }

    #[Test]
    #[DataProvider('toBoolTrueProvider')]
    #[DataProvider('toBoolFalseProvider')]
    public function toBoolNormalizesRepresentations(mixed $value, bool $expected): void
    {
        self::assertSame($expected, Typing::toBool($value));
    }

    /**
     * @return iterable<string, array{mixed, bool}>
     */
    public static function toBoolTrueProvider(): iterable
    {
        yield 'bool true' => [true, true];

        yield 'int one' => [1, true];

        yield 'string one' => ['1', true];

        yield 'string true' => ['true', true];

        yield 'string yes' => ['yes', true];

        yield 'string on' => ['on', true];

        yield 'uppercase and padded' => ['  ON  ', true];
    }

    /**
     * @return iterable<string, array{mixed, bool}>
     */
    public static function toBoolFalseProvider(): iterable
    {
        yield 'bool false' => [false, false];

        yield 'int zero' => [0, false];

        yield 'string zero' => ['0', false];

        yield 'string false' => ['false', false];

        yield 'string no' => ['no', false];

        yield 'string off' => ['off', false];

        yield 'empty string' => ['', false];
    }

    #[Test]
    public function toBoolThrowsOnAmbiguousString(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Typing::toBool()');

        Typing::toBool('maybe');
    }

    #[Test]
    public function toBoolThrowsOnOutOfRangeInteger(): void
    {
        $this->expectException(LogicException::class);

        Typing::toBool(2);
    }

    #[Test]
    public function toBoolThrowsOnNonScalar(): void
    {
        $this->expectException(LogicException::class);

        Typing::toBool([]);
    }

    #[Test]
    #[DataProvider('toStringProvider')]
    public function toStringNormalizesScalars(mixed $value, string $expected): void
    {
        self::assertSame($expected, Typing::toString($value));
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function toStringProvider(): iterable
    {
        yield 'null becomes empty' => [null, ''];

        yield 'integer' => [5, '5'];

        yield 'float' => [2.5, '2.5'];

        yield 'true becomes one' => [true, '1'];

        yield 'false becomes empty' => [false, ''];

        yield 'string passthrough' => ['hello', 'hello'];
    }

    #[Test]
    public function toStringUsesStringableObjects(): void
    {
        $stringable = new class implements Stringable {
            public function __toString(): string
            {
                return 'from-object';
            }
        };

        self::assertSame('from-object', Typing::toString($stringable));
    }

    #[Test]
    public function toStringThrowsOnNonStringableObject(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Typing::toString()');

        Typing::toString(new stdClass());
    }

    #[Test]
    #[DataProvider('toFloatProvider')]
    public function toFloatNormalizesScalars(mixed $value, ?float $expected): void
    {
        self::assertSame($expected, Typing::toFloat($value));
    }

    /**
     * @return iterable<string, array{mixed, null|float}>
     */
    public static function toFloatProvider(): iterable
    {
        yield 'null becomes null' => [null, null];

        yield 'empty string becomes null' => ['', null];

        yield 'numeric string' => ['1.5', 1.5];

        yield 'integer becomes float' => [2, 2.0];

        yield 'float passthrough' => [3.14, 3.14];
    }

    #[Test]
    public function toFloatThrowsOnNonNumeric(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Typing::toFloat()');

        Typing::toFloat('abc');
    }

    #[Test]
    #[DataProvider('normalizeIdProvider')]
    public function normalizeIdKeepsOnlyPositiveIds(mixed $value, ?int $expected): void
    {
        self::assertSame($expected, Typing::normalizeId($value));
    }

    /**
     * @return iterable<string, array{mixed, null|int}>
     */
    public static function normalizeIdProvider(): iterable
    {
        yield 'positive id' => ['5', 5];

        yield 'zero is null' => [0, null];

        yield 'negative is null' => [-2, null];

        yield 'null is null' => [null, null];
    }

    #[Test]
    public function normalizeIdOrZeroFallsBackToZero(): void
    {
        self::assertSame(7, Typing::normalizeIdOrZero('7'));
        self::assertSame(0, Typing::normalizeIdOrZero(0));
        self::assertSame(0, Typing::normalizeIdOrZero(null));
        self::assertSame(0, Typing::normalizeIdOrZero(-3));
    }

    #[Test]
    public function normalizeIsAnAliasForToInt(): void
    {
        self::assertSame(10, Typing::normalize('10'));
        self::assertNull(Typing::normalize(''));
    }

    #[Test]
    public function normalizeRecordCastsFieldsFromArray(): void
    {
        $record = [
            'id' => '10',
            'legacy' => '7',
            'count' => '-3',
            'active' => 'on',
            'price' => '19.90',
            'label' => 55,
        ];

        $spec = [
            'id' => 'int',
            'legacy' => 'nint',
            'count' => 'posint',
            'active' => 'bool',
            'price' => 'float',
            'label' => 'string',
            'absent' => 'int',
        ];

        $out = Typing::normalizeRecord($record, $spec);

        self::assertInstanceOf(stdClass::class, $out);
        self::assertSame(10, $out->id);
        self::assertSame(7, $out->legacy);
        self::assertNull($out->count);
        self::assertTrue($out->active);
        self::assertSame(19.9, $out->price);
        self::assertSame('55', $out->label);
        self::assertFalse(property_exists($out, 'absent'));
    }

    #[Test]
    public function normalizeRecordClonesStdClassWithoutMutatingOriginal(): void
    {
        $record = new stdClass();
        $record->id = '4';

        $out = Typing::normalizeRecord($record, ['id' => 'int']);

        self::assertNotSame($record, $out);
        self::assertSame(4, $out->id);
        self::assertSame('4', $record->id);
    }

    #[Test]
    public function castArrayOfIntsCastsEachElement(): void
    {
        self::assertSame([1, 2, null], Typing::castArrayOfInts(['1', '2', null]));
    }

    #[Test]
    public function castArrayOfStringsCastsEachElement(): void
    {
        self::assertSame(['1', '2.5', '1'], Typing::castArrayOfStrings([1, 2.5, true]));
    }

    #[Test]
    #[DataProvider('toNumberProvider')]
    public function toNumberReturnsIntWhenIntegralAndFloatOtherwise(mixed $value, float|int|null $expected): void
    {
        self::assertSame($expected, Typing::toNumber($value));
    }

    /**
     * @return iterable<string, array{mixed, null|float|int}>
     */
    public static function toNumberProvider(): iterable
    {
        yield 'null' => [null, null];

        yield 'empty string' => ['', null];

        yield 'integral string' => ['5', 5];

        yield 'integral float' => [5.0, 5];

        yield 'fractional string' => ['5.5', 5.5];

        yield 'fractional float' => [5.5, 5.5];

        yield 'zero' => ['0', 0];

        yield 'negative integral' => ['-3', -3];

        yield 'negative fractional' => [-3.25, -3.25];
    }

    #[Test]
    public function toNumberThrowsOnNonNumeric(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Typing::toNumber()');

        Typing::toNumber('abc');
    }

    #[Test]
    #[DataProvider('isNumericStringProvider')]
    public function isNumericStringDetectsDigitOnlyStrings(mixed $value, bool $expected): void
    {
        self::assertSame($expected, Typing::isNumericString($value));
    }

    /**
     * @return iterable<string, array{mixed, bool}>
     */
    public static function isNumericStringProvider(): iterable
    {
        yield 'digits' => ['123', true];

        yield 'zero' => ['0', true];

        yield 'letters mixed' => ['12a', false];

        yield 'empty' => ['', false];

        yield 'integer is not a string' => [123, false];

        yield 'negative sign rejected' => ['-1', false];
    }
}
