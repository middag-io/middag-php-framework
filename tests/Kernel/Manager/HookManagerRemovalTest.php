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
 * WordPress-parity surface: remove*, has*, and the dispatch-introspection trio.
 *
 * @internal
 */
#[CoversClass(HookManager::class)]
final class HookManagerRemovalTest extends TestCase
{
    public function testRemoveFilterDetachesByIdentityAndStopsRunning(): void
    {
        $hooks = new HookManager();
        $double = static fn (int $v): int => $v * 2;
        $hooks->addFilter('n', $double);

        self::assertTrue($hooks->hasFilter('n'));
        self::assertTrue($hooks->removeFilter('n', $double));
        self::assertFalse($hooks->hasFilter('n'));
        // With the callback gone, the value passes through unchanged.
        self::assertSame(5, $hooks->applyFilters('n', 5));
    }

    public function testRemoveFilterReturnsFalseWhenNothingMatches(): void
    {
        $hooks = new HookManager();
        $hooks->addFilter('n', static fn (int $v): int => $v, priority: 10);

        // Different priority → no match.
        self::assertFalse($hooks->removeFilter('n', static fn (int $v): int => $v, priority: 20));
        // Unknown tag → no match.
        self::assertFalse($hooks->removeFilter('other', static fn (int $v): int => $v));
    }

    public function testRemoveFilterReturnsFalseWhenCallbackNotAtThatPriority(): void
    {
        $hooks = new HookManager();
        $kept = static fn (int $v): int => $v + 1;
        $hooks->addFilter('n', $kept, priority: 10);

        // Priority 10 exists, but this callback is not registered there.
        self::assertFalse($hooks->removeFilter('n', static fn (int $v): int => $v, priority: 10));
        // The real callback is untouched and still runs.
        self::assertSame(6, $hooks->applyFilters('n', 5));
    }

    public function testRemoveFilterKeepsOtherCallbacksAtSamePriority(): void
    {
        $hooks = new HookManager();
        $plusOne = static fn (int $v): int => $v + 1;
        $timesTwo = static fn (int $v): int => $v * 2;
        $hooks->addFilter('n', $plusOne, priority: 10);
        $hooks->addFilter('n', $timesTwo, priority: 10);

        self::assertTrue($hooks->removeFilter('n', $plusOne, priority: 10));
        self::assertTrue($hooks->hasFilter('n'), 'the other callback at the same priority survives');
        self::assertSame(10, $hooks->applyFilters('n', 5), 'only timesTwo remains');
    }

    public function testRemoveActionReturnsFalseForUnknownAndUnmatchedCallback(): void
    {
        $hooks = new HookManager();
        $listener = static function (): void {};
        $hooks->addAction('boot', $listener, priority: 10);

        // Unknown tag/priority → not isset.
        self::assertFalse($hooks->removeAction('missing', $listener));
        // Priority exists but this callback is not the registered one.
        self::assertFalse($hooks->removeAction('boot', static function (): void {}, priority: 10));
        self::assertTrue($hooks->hasAction('boot'));
    }

    public function testRemoveActionKeepsOtherCallbacksAtSamePriority(): void
    {
        $hooks = new HookManager();
        $calls = [];
        $first = static function () use (&$calls): void { $calls[] = 'first'; };
        $second = static function () use (&$calls): void { $calls[] = 'second'; };
        $hooks->addAction('boot', $first, priority: 10);
        $hooks->addAction('boot', $second, priority: 10);

        self::assertTrue($hooks->removeAction('boot', $first, priority: 10));

        $hooks->doAction('boot');
        self::assertSame(['second'], $calls, 'only the surviving callback runs');
    }

    public function testRemoveActionDetachesByIdentity(): void
    {
        $hooks = new HookManager();
        $calls = 0;
        $listener = static function () use (&$calls): void { ++$calls; };
        $hooks->addAction('boot', $listener);

        $hooks->doAction('boot');
        self::assertSame(1, $calls);

        self::assertTrue($hooks->removeAction('boot', $listener));
        self::assertFalse($hooks->hasAction('boot'));

        $hooks->doAction('boot');
        self::assertSame(1, $calls, 'detached listener must not run again');
    }

    public function testDidActionCountsDispatchesEvenWithoutListeners(): void
    {
        $hooks = new HookManager();

        self::assertSame(0, $hooks->didAction('ping'));
        $hooks->doAction('ping');
        $hooks->doAction('ping');
        self::assertSame(2, $hooks->didAction('ping'));
    }

    public function testCurrentFilterAndDoingActionReportTheActiveTag(): void
    {
        $hooks = new HookManager();
        $seen = [];

        $hooks->addAction('outer', function () use ($hooks, &$seen): void {
            $seen['current'] = $hooks->currentFilter();
            $seen['doing_outer'] = $hooks->doingAction('outer');
            $seen['doing_any'] = $hooks->doingAction();
            $seen['doing_other'] = $hooks->doingAction('nope');
        });

        // Outside any dispatch.
        self::assertNull($hooks->currentFilter());
        self::assertFalse($hooks->doingAction());

        $hooks->doAction('outer');

        self::assertSame('outer', $seen['current']);
        self::assertTrue($seen['doing_outer']);
        self::assertTrue($seen['doing_any']);
        self::assertFalse($seen['doing_other']);
        // Stack unwound after dispatch.
        self::assertNull($hooks->currentFilter());
    }

    public function testLoopBreakViaRemoveActionThenReAdd(): void
    {
        $hooks = new HookManager();
        $runs = 0;

        // The WP idiom: detach self before re-dispatching, re-attach after, so the
        // re-entrant doAction() does not recurse infinitely.
        $listener = function () use ($hooks, &$listener, &$runs): void {
            ++$runs;
            $hooks->removeAction('save', $listener);
            $hooks->doAction('save'); // would loop forever without the detach
            $hooks->addAction('save', $listener);
        };
        $hooks->addAction('save', $listener);

        $hooks->doAction('save');

        self::assertSame(1, $runs, 'listener must run exactly once despite re-dispatch');
        self::assertTrue($hooks->hasAction('save'), 're-attached after the guarded re-dispatch');
    }

    public function testResetClearsIntrospectionState(): void
    {
        $hooks = new HookManager();
        $hooks->addAction('x', static function (): void {});
        $hooks->doAction('x');

        $hooks->reset();

        self::assertSame(0, $hooks->didAction('x'));
        self::assertFalse($hooks->hasAction('x'));
        self::assertNull($hooks->currentFilter());
    }
}
