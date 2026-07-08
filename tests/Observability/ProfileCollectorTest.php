<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Observability;

use Middag\Framework\Observability\ProfileCollector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The in-memory profile sink records, filters by category, and resets.
 *
 * @internal
 */
#[CoversClass(ProfileCollector::class)]
final class ProfileCollectorTest extends TestCase
{
    #[Test]
    public function recordsEventsInOrderWithMetadata(): void
    {
        $collector = new ProfileCollector();
        $collector->record('bus', 'App\CreateTask', ['queued' => false], 1.5);
        $collector->record('hook', 'task.created', ['kind' => 'action'], 0.2);

        $events = $collector->events();

        $this->assertCount(2, $events);
        $this->assertSame('bus', $events[0]['category']);
        $this->assertSame('App\CreateTask', $events[0]['label']);
        $this->assertSame(['queued' => false], $events[0]['context']);
        $this->assertSame(1.5, $events[0]['duration_ms']);
    }

    #[Test]
    public function filtersByCategory(): void
    {
        $collector = new ProfileCollector();
        $collector->record('bus', 'A');
        $collector->record('hook', 'B');
        $collector->record('bus', 'C');

        $bus = $collector->byCategory('bus');

        $this->assertCount(2, $bus);
        $this->assertSame(['A', 'C'], array_column($bus, 'label'));
    }

    #[Test]
    public function resetEmptiesTheTimeline(): void
    {
        $collector = new ProfileCollector();
        $collector->record('bus', 'A');
        $collector->reset();

        $this->assertSame([], $collector->events());
    }
}
