<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Kernel\Manager;

use Middag\Framework\Kernel\Manager\HookManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(HookManager::class)]
final class HookManagerTest extends TestCase
{
    public function testApplyFiltersTransformsValueThroughCallbacks(): void
    {
        $hooks = new HookManager();
        $hooks->addFilter('price', static fn (int $v): int => $v + 10);
        $hooks->addFilter('price', static fn (int $v): int => $v * 2);

        self::assertSame(40, $hooks->applyFilters('price', 10));
    }

    public function testApplyFiltersRunsLowerPriorityFirst(): void
    {
        $hooks = new HookManager();
        // Registered out of order; priority decides execution order.
        $hooks->addFilter('seq', static fn (string $v): string => $v . 'B', priority: 20);
        $hooks->addFilter('seq', static fn (string $v): string => $v . 'A', priority: 10);

        // Lower priority (10) runs first → 'A' prepended before 'B'.
        self::assertSame('AB', $hooks->applyFilters('seq', ''));
    }

    public function testApplyFiltersReturnsValueUnchangedWhenNoCallbacks(): void
    {
        $hooks = new HookManager();

        self::assertSame('untouched', $hooks->applyFilters('missing', 'untouched'));
    }

    public function testAcceptedArgsLimitsExtraArgumentsPassedToCallback(): void
    {
        $hooks = new HookManager();
        $received = [];
        $hooks->addFilter('cut', static function (mixed ...$args) use (&$received): mixed {
            $received = $args;

            return $args[0];
        }, args: 2);

        $hooks->applyFilters('cut', 'value', 'keep', 'dropped');

        self::assertSame(['value', 'keep'], $received);
    }

    public function testDoActionRunsCallbacksAndHasAction(): void
    {
        $hooks = new HookManager();
        $calls = [];
        $hooks->addAction('boot', static function (string $who) use (&$calls): void {
            $calls[] = $who;
        });

        self::assertTrue($hooks->hasAction('boot'));
        self::assertFalse($hooks->hasAction('never'));

        $hooks->doAction('boot', 'kernel');

        self::assertSame(['kernel'], $calls);
    }

    public function testResetClearsAllHooks(): void
    {
        $hooks = new HookManager();
        $hooks->addFilter('f', static fn (int $v): int => $v + 1);
        $hooks->addAction('a', static fn (): null => null);

        $hooks->reset();

        self::assertFalse($hooks->hasAction('a'));
        self::assertSame(5, $hooks->applyFilters('f', 5));
    }

    public function testInstancesDoNotShareState(): void
    {
        $first = new HookManager();
        $second = new HookManager();
        $first->addFilter('x', static fn (int $v): int => $v + 100);

        // Per-instance state: the second manager never saw the filter.
        self::assertSame(1, $second->applyFilters('x', 1));
        self::assertSame(101, $first->applyFilters('x', 1));
    }
}
