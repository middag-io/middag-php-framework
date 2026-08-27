<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Kernel;

use Middag\Framework\Kernel\ShutdownCleanup;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use RuntimeException;
use Stringable;

/**
 * @internal
 */
#[CoversClass(ShutdownCleanup::class)]
final class ShutdownCleanupTest extends TestCase
{
    public function testRunsCleanupsInLifoOrder(): void
    {
        $order = [];
        $cleanup = new ShutdownCleanup();
        $cleanup->addCleanup(static function () use (&$order): void { $order[] = 'first'; }, 'first');
        $cleanup->addCleanup(static function () use (&$order): void { $order[] = 'second'; }, 'second');

        $cleanup->run();

        self::assertSame(['second', 'first'], $order, 'LIFO: last registered runs first');
    }

    public function testFailingCleanupIsIsolatedLoggedAndDoesNotBlockOthers(): void
    {
        $logger = new class extends AbstractLogger {
            /** @var list<array{level: mixed, context: array<string, mixed>}> */
            public array $records = [];

            public function log($level, string|Stringable $message, array $context = []): void
            {
                $this->records[] = ['level' => $level, 'context' => $context];
            }
        };
        $ran = false;

        $cleanup = new ShutdownCleanup($logger);
        // Registered first → runs last (LIFO), proving the earlier throw didn't block it.
        $cleanup->addCleanup(static function () use (&$ran): void { $ran = true; }, 'survivor');
        $cleanup->addCleanup(static function (): never { throw new RuntimeException('teardown boom'); }, 'broken');

        $cleanup->run();

        self::assertTrue($ran, 'a throwing cleanup must not block the remaining ones');
        self::assertCount(1, $logger->records);
        self::assertSame('error', $logger->records[0]['level']);
        self::assertSame('broken', $logger->records[0]['context']['label']);
    }

    public function testRunIsNotRepeatedOnSecondCall(): void
    {
        $calls = 0;
        $cleanup = new ShutdownCleanup();
        $cleanup->addCleanup(static function () use (&$calls): void { ++$calls; });

        $cleanup->run();
        $cleanup->run();

        self::assertSame(1, $calls, 'cleanups drain after running; a second run() is a no-op');
    }

    #[DoesNotPerformAssertions]
    public function testRegisterIsIdempotent(): void
    {
        $cleanup = new ShutdownCleanup();

        // First call installs the shutdown hook; the second hits the early-return
        // guard so register_shutdown_function is never called twice.
        $cleanup->register();
        $cleanup->register();
    }
}
