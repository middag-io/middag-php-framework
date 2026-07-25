<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Shared\ValueObject;

use JsonSerializable;
use Middag\Framework\Exception\MiddagValidationException;
use Middag\Framework\Shared\ValueObject\Uuid;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Stringable;
use Symfony\Component\Uid\Uuid as SymfonyUuid;

/**
 * The Uuid value object: parses any RFC 4122 version, mints v4/v7 explicitly, and
 * refuses everything that is not a canonical identifier.
 *
 * @internal
 */
#[CoversClass(Uuid::class)]
final class UuidTest extends TestCase
{
    /**
     * A canonical v4 literal, used wherever the concrete value is irrelevant.
     */
    private const V4 = '9fe30b4e-7058-4eba-bff9-5337d64b0460';

    /**
     * How many v7 values the monotonicity test generates in a row.
     */
    private const MONOTONIC_SAMPLE = 50;

    public function testV4MintsAParsableVersion4Uuid(): void
    {
        $uuid = Uuid::v4();

        self::assertSame(4, $uuid->version());
        self::assertSame($uuid->value, Uuid::fromString($uuid->value)->value);
    }

    public function testV7MintsAParsableVersion7Uuid(): void
    {
        $uuid = Uuid::v7();

        self::assertSame(7, $uuid->version());
        self::assertSame($uuid->value, Uuid::fromString($uuid->value)->value);
    }

    public function testV4ValuesAreDistinct(): void
    {
        self::assertNotSame(Uuid::v4()->value, Uuid::v4()->value);
    }

    public function testTwoV7ValuesGeneratedInSequenceSortAscendingAsStrings(): void
    {
        $first = Uuid::v7()->value;
        $second = Uuid::v7()->value;

        self::assertLessThan(0, strcmp($first, $second), sprintf(
            'v7 must be time-ordered: "%s" should sort before "%s".',
            $first,
            $second,
        ));
    }

    public function testASequenceOfV7ValuesIsStrictlyMonotonic(): void
    {
        $generated = [];

        for ($i = 0; $i < self::MONOTONIC_SAMPLE; ++$i) {
            $generated[] = Uuid::v7()->value;
        }

        $ascending = $generated;
        sort($ascending, SORT_STRING);

        self::assertSame($ascending, $generated, 'v7 keys must already be in ascending string order.');
        self::assertSame($generated, array_values(array_unique($generated)), 'v7 keys must be unique.');
    }

    /**
     * @param int<1, 8> $expectedVersion
     */
    #[DataProvider('generatedUuids')]
    public function testParsesAnyRfc4122VersionAndRoundTrips(string $raw, int $expectedVersion): void
    {
        $uuid = new Uuid($raw);

        self::assertSame($expectedVersion, $uuid->version());
        self::assertSame($raw, $uuid->value);
        self::assertSame($raw, (string) Uuid::fromString((string) $uuid));
    }

    /**
     * Real values straight out of symfony/uid, so the VO is proven against the
     * generators it wraps and not only against handcrafted literals.
     *
     * @return iterable<string, array{string, int}>
     */
    public static function generatedUuids(): iterable
    {
        yield 'v1 (time + MAC)' => [SymfonyUuid::v1()->toRfc4122(), 1];

        yield 'v4 (random)' => [SymfonyUuid::v4()->toRfc4122(), 4];

        yield 'v6 (reordered time)' => [SymfonyUuid::v6()->toRfc4122(), 6];

        yield 'v7 (unix time ordered)' => [SymfonyUuid::v7()->toRfc4122(), 7];
    }

    /**
     * @param int<1, 8> $expectedVersion
     */
    #[DataProvider('versionNibbles')]
    public function testDetectsTheVersionNibble(string $raw, int $expectedVersion): void
    {
        self::assertSame($expectedVersion, (new Uuid($raw))->version());
    }

    /**
     * Every version the RFC defines, as a literal, so detection is covered end to end.
     *
     * @return iterable<string, array{string, int}>
     */
    public static function versionNibbles(): iterable
    {
        foreach (range(1, 8) as $version) {
            yield 'version ' . $version => [
                sprintf('00000000-0000-%d000-8000-000000000000', $version),
                $version,
            ];
        }
    }

    #[DataProvider('invalidValues')]
    public function testRefusesAnythingThatIsNotACanonicalUuid(string $invalid): void
    {
        $this->expectException(MiddagValidationException::class);

        new Uuid($invalid);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidValues(): iterable
    {
        yield 'empty string' => [''];

        yield 'arbitrary text' => ['not-a-uuid'];

        yield 'one hex digit short' => ['00000000-0000-4000-8000-00000000000'];

        yield 'one hex digit long' => ['00000000-0000-4000-8000-0000000000000'];

        yield 'hyphen out of place' => ['0000000-00000-4000-8000-000000000000'];

        yield 'no hyphens at all' => ['00000000000040008000000000000000'];

        yield 'non-hex character' => ['0000000g-0000-4000-8000-000000000000'];

        yield 'nil uuid' => ['00000000-0000-0000-0000-000000000000'];

        yield 'max uuid' => ['ffffffff-ffff-ffff-ffff-ffffffffffff'];

        yield 'version nibble 0' => ['00000000-0000-0000-8000-000000000000'];

        yield 'version nibble 9' => ['00000000-0000-9000-8000-000000000000'];

        yield 'non rfc-4122 variant' => ['00000000-0000-4000-c000-000000000000'];

        yield 'leading whitespace' => [' 00000000-0000-4000-8000-000000000000'];

        yield 'trailing newline' => ["00000000-0000-4000-8000-000000000000\n"];

        yield 'braced microsoft form' => ['{00000000-0000-4000-8000-000000000000}'];

        yield 'urn form' => ['urn:uuid:00000000-0000-4000-8000-000000000000'];

        yield 'base32 form' => ['4YV8VG1V8T80007G0000000000'];
    }

    public function testTheRefusedValueIsEchoedInTheMessage(): void
    {
        $this->expectException(MiddagValidationException::class);
        $this->expectExceptionMessage('"not-a-uuid" is not a valid RFC 4122 UUID.');

        new Uuid('not-a-uuid');
    }

    public function testAnOversizedRefusedValueIsTruncatedInTheMessage(): void
    {
        $this->expectException(MiddagValidationException::class);
        $this->expectExceptionMessage(sprintf('"%s..." is not a valid RFC 4122 UUID.', str_repeat('a', 48)));

        new Uuid(str_repeat('a', 5000));
    }

    public function testRefusalCarriesTheHttp422Status(): void
    {
        $caught = null;

        try {
            Uuid::fromString('nope');
        } catch (MiddagValidationException $middagValidationException) {
            $caught = $middagValidationException;
        }

        self::assertInstanceOf(MiddagValidationException::class, $caught);
        self::assertSame(422, $caught->getStatusCode());
    }

    public function testUppercaseInputIsNormalisedToLowercase(): void
    {
        $uuid = new Uuid('00000000-0000-4000-8000-00000000ABCD');

        self::assertSame('00000000-0000-4000-8000-00000000abcd', $uuid->value);
        self::assertSame('00000000-0000-4000-8000-00000000abcd', (string) $uuid);
    }

    public function testFromStringIsEquivalentToTheConstructor(): void
    {
        self::assertSame((new Uuid(self::V4))->value, Uuid::fromString(self::V4)->value);
    }

    public function testToStringReturnsTheCanonicalValue(): void
    {
        $uuid = new Uuid(self::V4);

        self::assertInstanceOf(Stringable::class, $uuid);
        self::assertSame(self::V4, (string) $uuid);
    }

    public function testJsonSerialisesToTheRawString(): void
    {
        $uuid = new Uuid(self::V4);

        self::assertInstanceOf(JsonSerializable::class, $uuid);
        self::assertSame(self::V4, $uuid->jsonSerialize());
        self::assertSame('"' . self::V4 . '"', json_encode($uuid));
    }
}
