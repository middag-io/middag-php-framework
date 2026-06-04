<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Kernel\Bootstrap;

use Middag\Framework\Kernel\Bootstrap\BootIsolateFailurePolicy;
use Middag\Framework\Kernel\Contract\ModuleInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\AbstractLogger;
use RuntimeException;
use Stringable;

/**
 * @internal
 */
#[CoversClass(BootIsolateFailurePolicy::class)]
final class BootIsolateFailurePolicyTest extends TestCase
{
    public function testLogsCriticalAndDoesNotRethrow(): void
    {
        $logger = new class extends AbstractLogger {
            /** @var list<array{level: mixed, context: array<string, mixed>}> */
            public array $records = [];

            public function log($level, string|Stringable $message, array $context = []): void
            {
                $this->records[] = ['level' => $level, 'context' => $context];
            }
        };

        $policy = new BootIsolateFailurePolicy($logger);

        // Must NOT throw — the platform keeps booting the remaining modules.
        $policy->handle($this->fakeModule('billing'), new RuntimeException('boom'));

        self::assertCount(1, $logger->records);
        self::assertSame('critical', $logger->records[0]['level']);
        self::assertSame('billing', $logger->records[0]['context']['module']);
        self::assertSame('boom', $logger->records[0]['context']['message']);
    }

    private function fakeModule(string $name): ModuleInterface
    {
        return new class($name) implements ModuleInterface {
            public function __construct(private readonly string $name) {}

            public function getName(): string
            {
                return $this->name;
            }

            public function getLabel(): string
            {
                return $this->name;
            }

            public function getVersion(): string
            {
                return '1.0.0';
            }

            public function getDependencies(): array
            {
                return [];
            }

            public function register(ContainerInterface $container): void {}

            public function boot(): void {}

            public function isEnabled(): bool
            {
                return true;
            }

            public function isAvailable(): bool
            {
                return true;
            }
        };
    }
}
