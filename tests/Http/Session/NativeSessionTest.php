<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Http\Session;

use Middag\Framework\Http\Session\NativeSession;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Native PHP session store over {@see $_SESSION}.
 *
 * The suite runs under the CLI SAPI, where start() and regenerate() are
 * deliberate no-ops (no transport, no "headers already sent"); the accessors
 * read and write the superglobal directly, which is reset around every test.
 *
 * @internal
 */
#[CoversClass(NativeSession::class)]
final class NativeSessionTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    #[Test]
    public function startIsANoOpUnderTheCliSapiAndOpensNoSession(): void
    {
        $session = new NativeSession();

        $session->start();

        $this->assertSame([], $_SESSION);
    }

    #[Test]
    public function setAndGetRoundTripThroughTheSuperglobal(): void
    {
        $session = new NativeSession();

        $session->set('user', 'Ada');

        $this->assertSame('Ada', $_SESSION['user']);
        $this->assertSame('Ada', $session->get('user'));
    }

    #[Test]
    public function getFallsBackToTheDefaultForMissingKeys(): void
    {
        $session = new NativeSession();

        $this->assertNull($session->get('missing'));
        $this->assertSame('x', $session->get('missing', 'x'));
    }

    #[Test]
    public function hasReflectsPresence(): void
    {
        $session = new NativeSession();

        $this->assertFalse($session->has('k'));
        $session->set('k', 1);

        $this->assertTrue($session->has('k'));
    }

    #[Test]
    public function removeDeletesASingleKeyOnly(): void
    {
        $_SESSION = ['a' => 1, 'b' => 2];
        $session = new NativeSession();

        $session->remove('a');

        $this->assertArrayNotHasKey('a', $_SESSION);
        $this->assertArrayHasKey('b', $_SESSION);
    }

    #[Test]
    public function clearEmptiesTheSuperglobal(): void
    {
        $_SESSION = ['a' => 1, 'b' => 2];
        $session = new NativeSession();

        $session->clear();

        $this->assertSame([], $_SESSION);
    }

    #[Test]
    public function regenerateIsANoOpUnderTheCliSapiAndPreservesState(): void
    {
        $_SESSION = ['token' => 'abc'];
        $session = new NativeSession();

        $session->regenerate();
        $session->regenerate(false);

        $this->assertSame(['token' => 'abc'], $_SESSION);
    }
}
