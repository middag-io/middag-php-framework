<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Mail;

use InvalidArgumentException;
use Middag\Framework\Mail\Address;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Address::class)]
final class AddressTest extends TestCase
{
    public function testBareEmail(): void
    {
        $address = new Address('jane@example.test');

        self::assertSame('jane@example.test', $address->email);
        self::assertNull($address->name);
        self::assertSame('jane@example.test', $address->toString());
    }

    public function testEmailWithDisplayName(): void
    {
        $address = new Address('jane@example.test', 'Jane Doe');

        self::assertSame('Jane Doe <jane@example.test>', $address->toString());
    }

    public function testToStringQuotesNameContainingComma(): void
    {
        $address = new Address('jane@example.test', 'Doe, Jane');

        self::assertSame('"Doe, Jane" <jane@example.test>', $address->toString());
    }

    public function testToStringEscapesQuotesInQuotedName(): void
    {
        $address = new Address('jane@example.test', 'Jane "JD" Doe');

        self::assertSame('"Jane \"JD\" Doe" <jane@example.test>', $address->toString());
    }

    public function testToStringEscapesBackslashInQuotedName(): void
    {
        $address = new Address('jane@example.test', 'Back\slash');

        self::assertSame('"Back\\\slash" <jane@example.test>', $address->toString());
    }

    public function testParseRfcStyleString(): void
    {
        $address = Address::parse('Jane Doe <jane@example.test>');

        self::assertSame('jane@example.test', $address->email);
        self::assertSame('Jane Doe', $address->name);
    }

    public function testParseQuotedDisplayName(): void
    {
        $address = Address::parse('"Doe, Jane" <jane@example.test>');

        self::assertSame('jane@example.test', $address->email);
        self::assertSame('Doe, Jane', $address->name);
    }

    public function testParseBareEmail(): void
    {
        $address = Address::parse('  jane@example.test ');

        self::assertSame('jane@example.test', $address->email);
        self::assertNull($address->name);
    }

    public function testParseAngleBracketsWithoutName(): void
    {
        $address = Address::parse('<jane@example.test>');

        self::assertSame('jane@example.test', $address->email);
        self::assertNull($address->name);
    }

    public function testRejectsEmptyEmail(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Address('');
    }

    public function testRejectsEmailWithoutAt(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Address('not-an-email');
    }

    public function testRejectsEmailWithEmptyDomain(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Address('user@');
    }

    public function testRejectsEmailWithEmptyLocalPart(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Address('@example.test');
    }

    public function testRejectsEmailContainingWhitespace(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Address('jane doe@example.test');
    }

    public function testParseRejectsBareEmailWithTrailingJunk(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Address::parse('jane@x junk');
    }

    public function testRoundTripParseOfToString(): void
    {
        $original = new Address('jane@example.test', 'Doe, Jane');
        $parsed = Address::parse($original->toString());

        self::assertSame($original->email, $parsed->email);
        self::assertSame($original->name, $parsed->name);
    }
}
