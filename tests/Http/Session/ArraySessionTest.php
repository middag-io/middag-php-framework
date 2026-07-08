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

use Middag\Framework\Http\Session\ArraySession;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * In-memory session store: plain-array get/set/has/remove/clear with no
 * transport, so start() and regenerate() are inert.
 *
 * @internal
 */
#[CoversClass(ArraySession::class)]
final class ArraySessionTest extends TestCase
{
    #[Test]
    public function startIsANoOpAndLeavesSeededStateIntact(): void
    {
        $session = new ArraySession(['seed' => 'value']);

        $session->start();

        $this->assertTrue($session->has('seed'));
        $this->assertSame('value', $session->get('seed'));
    }

    #[Test]
    public function getReturnsStoredValueAndFallsBackToDefault(): void
    {
        $session = new ArraySession(['name' => 'Ada']);

        $this->assertSame('Ada', $session->get('name'));
        $this->assertSame('fallback', $session->get('missing', 'fallback'));
        $this->assertNull($session->get('missing'));
    }

    #[Test]
    public function setStoresValueAndHasReflectsIt(): void
    {
        $session = new ArraySession();

        $this->assertFalse($session->has('k'));
        $session->set('k', 42);

        $this->assertTrue($session->has('k'));
        $this->assertSame(42, $session->get('k'));
    }

    #[Test]
    public function removeDeletesASingleKeyOnly(): void
    {
        $session = new ArraySession(['a' => 1, 'b' => 2]);

        $session->remove('a');

        $this->assertFalse($session->has('a'));
        $this->assertTrue($session->has('b'));
    }

    #[Test]
    public function clearEmptiesEverything(): void
    {
        $session = new ArraySession(['a' => 1, 'b' => 2]);

        $session->clear();

        $this->assertFalse($session->has('a'));
        $this->assertFalse($session->has('b'));
    }

    #[Test]
    public function regenerateIsANoOpThatPreservesState(): void
    {
        $session = new ArraySession(['token' => 'abc']);

        $session->regenerate();
        $session->regenerate(false);

        $this->assertSame('abc', $session->get('token'));
    }

    #[Test]
    public function hasDistinguishesAStoredNullFromAnAbsentKey(): void
    {
        // has() uses array_key_exists, so a stored null counts as present,
        // while get() (?? default) still returns the default for it.
        $session = new ArraySession(['present' => null]);

        $this->assertTrue($session->has('present'));
        $this->assertFalse($session->has('absent'));
        $this->assertSame('d', $session->get('present', 'd'));
    }
}
