<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Kernel\Module;

use Middag\Framework\Kernel\Module\AbstractModule;
use Middag\Framework\Tests\Kernel\Module\Fixture\RecordingHooks;
use Middag\Framework\Tests\Kernel\Module\Fixture\TestableModule;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * @internal
 */
#[CoversClass(AbstractModule::class)]
final class AbstractModuleTest extends TestCase
{
    protected function setUp(): void
    {
        RecordingHooks::$calls = 0;
    }

    public function testRegisterHooksInvokesDiscoveredHookRegister(): void
    {
        $module = new TestableModule();
        $module->register($this->containerWithHook());

        $module->registerHooks();

        self::assertSame(1, RecordingHooks::$calls);
    }

    public function testBootRunsHookRegistration(): void
    {
        $module = new TestableModule();
        $module->register($this->containerWithHook());

        $module->boot();

        self::assertSame(1, RecordingHooks::$calls);
    }

    public function testRegisterHooksSkipsWhenContainerLacksHook(): void
    {
        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturn(false);

        $module = new TestableModule();
        $module->register($container);

        $module->registerHooks();

        self::assertSame(0, RecordingHooks::$calls);
    }

    public function testLifecycleDefaultsComeFromConstants(): void
    {
        $module = new TestableModule();

        self::assertSame('testable', $module->getName());
        self::assertSame('testable', $module->getLabel());
        self::assertSame('1.0.0', $module->getVersion());
        self::assertSame([], $module->getDependencies());
        self::assertTrue($module->isEnabled());
        self::assertTrue($module->isAvailable());
    }

    public function testDiscoverClassesBySuffixHandlesMissingDirsAndNonPhpFiles(): void
    {
        $module = new class extends AbstractModule {
            public ?string $dirOverride = null;

            /** @return string[] */
            public function exposeDiscover(string $suffix): array
            {
                return $this->discoverClassesBySuffix($suffix);
            }

            protected function getModuleDirectory(): ?string
            {
                return $this->dirOverride;
            }
        };

        // A missing directory yields nothing (the is_dir guard).
        $module->dirOverride = sys_get_temp_dir() . '/middag-no-such-module-dir';
        self::assertSame([], $module->exposeDiscover('Hooks'));

        // A directory holding only a non-PHP file is skipped by the extension guard.
        $tmp = sys_get_temp_dir() . '/middag-mod-' . getmypid();
        mkdir($tmp, 0o777, true);
        file_put_contents($tmp . '/notes.txt', 'not php');
        $module->dirOverride = $tmp;

        try {
            self::assertSame([], $module->exposeDiscover('Hooks'));
        } finally {
            unlink($tmp . '/notes.txt');
            rmdir($tmp);
        }
    }

    private function containerWithHook(): ContainerInterface
    {
        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->method('get')->willReturn(new RecordingHooks());

        return $container;
    }
}
