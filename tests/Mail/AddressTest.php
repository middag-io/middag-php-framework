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
}
