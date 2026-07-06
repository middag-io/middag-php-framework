<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Http\Inertia;

use Closure;
use Middag\Framework\Http\Inertia\InertiaManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The global shared-prop registry for Inertia responses. Static by design, so
 * every test brackets the shared state with flush() for isolation.
 *
 * @internal
 */
#[CoversClass(InertiaManager::class)]
final class InertiaManagerTest extends TestCase
{
    protected function setUp(): void
    {
        InertiaManager::flush();
    }

    protected function tearDown(): void
    {
        InertiaManager::flush();
    }

    #[Test]
    public function getSharedReturnsEmptyArrayWhenNothingHasBeenShared(): void
    {
        self::assertSame([], InertiaManager::getShared());
    }

    #[Test]
    public function shareStoresAValueRetrievableViaGetSharedUnderItsKey(): void
    {
        InertiaManager::share('locale', 'pt_BR');

        self::assertSame(['locale' => 'pt_BR'], InertiaManager::getShared());
    }

    #[Test]
    public function getSharedReturnsClosurePropsRawWithoutExecutingThem(): void
    {
        $calls = 0;
        InertiaManager::share('csrf', function () use (&$calls): string {
            ++$calls;

            return 'token';
        });

        $shared = InertiaManager::getShared();

        self::assertInstanceOf(Closure::class, $shared['csrf']);
        self::assertSame(0, $calls, 'getShared() must not invoke shared closures');
    }

    #[Test]
    public function shareOverwritesTheValueOfAnExistingKey(): void
    {
        InertiaManager::share('mode', 'first');
        InertiaManager::share('mode', 'second');

        self::assertSame('second', InertiaManager::getShared()['mode']);
    }

    #[Test]
    public function flushClearsAllPreviouslySharedProps(): void
    {
        InertiaManager::share('a', 1);
        InertiaManager::share('b', 2);

        InertiaManager::flush();

        self::assertSame([], InertiaManager::getShared());
    }
}
