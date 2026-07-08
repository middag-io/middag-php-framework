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
use Middag\Framework\Http\Session\FlashBag;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * One-shot flash semantics: queued messages and errors are read exactly
 * once, then cleared.
 *
 * @internal
 */
#[CoversClass(FlashBag::class)]
final class FlashBagTest extends TestCase
{
    #[Test]
    public function pullReturnsQueuedMessagesThenClearsThem(): void
    {
        $flash = new FlashBag($session = new ArraySession());
        $flash->add('success', 'Saved.');
        $flash->add('info', 'FYI');

        $this->assertSame(['success' => 'Saved.', 'info' => 'FYI'], $flash->pull());
        $this->assertSame([], $flash->pull(), 'flash is one-shot');
        $this->assertFalse($session->has('_middag_flash'));
    }

    #[Test]
    public function pullErrorsReturnsValidationMapThenClearsIt(): void
    {
        $flash = new FlashBag(new ArraySession());
        $flash->setErrors(['email' => 'Invalid email', 'password' => ['Too short', 'No digit']]);

        $this->assertSame(
            ['email' => 'Invalid email', 'password' => ['Too short', 'No digit']],
            $flash->pullErrors(),
        );
        $this->assertSame([], $flash->pullErrors(), 'errors are one-shot');
    }

    #[Test]
    public function flashAndErrorsAreIndependentBuckets(): void
    {
        $flash = new FlashBag(new ArraySession());
        $flash->add('success', 'Saved.');
        $flash->setErrors(['name' => 'Required']);

        $this->assertSame(['name' => 'Required'], $flash->pullErrors());
        $this->assertSame(['success' => 'Saved.'], $flash->pull(), 'pulling errors must not clear flash');
    }

    #[Test]
    public function emptyByDefault(): void
    {
        $flash = new FlashBag(new ArraySession());

        $this->assertSame([], $flash->pull());
        $this->assertSame([], $flash->pullErrors());
    }
}
