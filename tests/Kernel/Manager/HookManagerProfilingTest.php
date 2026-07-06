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
use Middag\Framework\Observability\ProfileCollector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Fired filters and actions are recorded into the profile collector when
 * one is attached, and not otherwise.
 *
 * @internal
 */
#[CoversClass(HookManager::class)]
final class HookManagerProfilingTest extends TestCase
{
    #[Test]
    public function recordsFiredFiltersAndActions(): void
    {
        $hooks = new HookManager();
        $hooks->setProfileCollector($collector = new ProfileCollector());

        $hooks->addFilter('demo.title', static fn (string $value): string => strtoupper($value));
        $hooks->addAction('demo.saved', static fn (): null => null);

        $hooks->applyFilters('demo.title', 'hello');
        $hooks->doAction('demo.saved');

        $events = $collector->byCategory('hook');
        $this->assertSame(['demo.title', 'demo.saved'], array_column($events, 'label'));
        $this->assertSame('filter', $events[0]['context']['kind']);
        $this->assertSame('action', $events[1]['context']['kind']);
    }

    #[Test]
    public function recordsNothingWithoutACollector(): void
    {
        $hooks = new HookManager();
        $hooks->addFilter('demo.title', static fn (string $value): string => $value);

        // No collector attached → must not error, simply no recording.
        $this->assertSame('hello', $hooks->applyFilters('demo.title', 'hello'));
    }
}
