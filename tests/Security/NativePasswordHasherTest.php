<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Security;

use Middag\Framework\Security\Contract\PasswordHasherInterface;
use Middag\Framework\Security\NativePasswordHasher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(NativePasswordHasher::class)]
final class NativePasswordHasherTest extends TestCase
{
    public function testHashIsVerifiableAndNotPlaintext(): void
    {
        $hasher = new NativePasswordHasher();

        self::assertInstanceOf(PasswordHasherInterface::class, $hasher);

        $hash = $hasher->hash('correct horse battery staple');

        self::assertNotSame('correct horse battery staple', $hash);
        self::assertTrue($hasher->verify('correct horse battery staple', $hash));
    }

    public function testVerifyRejectsWrongPassword(): void
    {
        $hasher = new NativePasswordHasher();
        $hash = $hasher->hash('s3cret');

        self::assertFalse($hasher->verify('wrong', $hash));
    }

    public function testVerifyRejectsEmptyHash(): void
    {
        $hasher = new NativePasswordHasher();

        self::assertFalse($hasher->verify('anything', ''));
    }

    public function testHashesAreSaltedSoSamePasswordDiffers(): void
    {
        $hasher = new NativePasswordHasher();

        self::assertNotSame($hasher->hash('same'), $hasher->hash('same'));
    }

    public function testFreshDefaultHashDoesNotNeedRehash(): void
    {
        $hasher = new NativePasswordHasher();
        $hash = $hasher->hash('s3cret');

        self::assertFalse($hasher->needsRehash($hash));
    }
}
