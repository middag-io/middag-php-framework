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
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Stringable;

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

    #[Test]
    public function slowFilterWarnsThroughTheInjectedLogger(): void
    {
        $logger = new class extends AbstractLogger {
            /** @var list<array{level: mixed, message: string, context: array<string, mixed>}> */
            public array $records = [];

            public function log($level, string|Stringable $message, array $context = []): void
            {
                $this->records[] = ['level' => $level, 'message' => (string) $message, 'context' => $context];
            }
        };

        $hooks = new HookManager();
        $hooks->setLogger($logger);
        $hooks->setSlowThreshold(1); // 1ms
        $hooks->addFilter('slow', static function (int $v): int {
            usleep(3000); // ~3ms > threshold

            return $v;
        });

        $hooks->applyFilters('slow', 1);

        self::assertCount(1, $logger->records);
        self::assertSame('warning', $logger->records[0]['level']);
        self::assertStringContainsString('Slow {kind}', $logger->records[0]['message']);
        self::assertSame('filter', $logger->records[0]['context']['kind']);
        self::assertSame('slow', $logger->records[0]['context']['tag']);
    }

    #[Test]
    #[DoesNotPerformAssertions]
    public function slowActionFallsBackToNullLoggerWhenNoneInjected(): void
    {
        $hooks = new HookManager(slowThresholdMs: 1);
        $hooks->addAction('slow', static function (): void {
            usleep(3000);
        });

        // No logger set → warnIfSlow resolves a NullLogger lazily; must not error.
        $hooks->doAction('slow');
    }

    #[Test]
    public function slowThresholdOfZeroDisablesMonitoring(): void
    {
        $logger = new class extends AbstractLogger {
            public int $calls = 0;

            public function log($level, string|Stringable $message, array $context = []): void
            {
                ++$this->calls;
            }
        };

        $hooks = new HookManager();
        $hooks->setLogger($logger);
        $hooks->setSlowThreshold(0); // disabled
        $hooks->addFilter('slow', static function (int $v): int {
            usleep(3000);

            return $v;
        });

        $hooks->applyFilters('slow', 1);

        self::assertSame(0, $logger->calls, 'threshold 0 disables slow-hook warnings');
    }
}
