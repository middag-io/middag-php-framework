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

use Middag\Framework\Kernel\ContainerFactory;
use Middag\Framework\Kernel\Contract\BootstrapInterface;
use Middag\Framework\Tests\Kernel\Fixture\LazyProbe;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * The factory wires a lazy instantiator, so a service marked lazy is handed out
 * as a ghost whose real constructor runs only on first use.
 *
 * @internal
 */
#[CoversClass(ContainerFactory::class)]
final class ContainerFactoryLazyTest extends TestCase
{
    protected function setUp(): void
    {
        LazyProbe::$built = 0;
    }

    public function testLazyServiceDefersConstructionUntilFirstUse(): void
    {
        $factory = new ContainerFactory();
        $container = $factory->build($this->lazyBootstrap());

        $service = $container->get(LazyProbe::class);

        self::assertInstanceOf(LazyProbe::class, $service);
        self::assertSame(0, LazyProbe::$built, 'lazy ghost must NOT run the real constructor on get()');

        self::assertSame('pong', $service->ping());
        self::assertSame(1, LazyProbe::$built, 'real constructor runs on first method call');
    }

    public function testNonLazyServiceIsBuiltEagerlyOnGet(): void
    {
        $factory = new ContainerFactory();
        $container = $factory->build($this->eagerBootstrap());

        $container->get(LazyProbe::class);

        self::assertSame(1, LazyProbe::$built, 'eager service is constructed when fetched');
    }

    private function lazyBootstrap(): BootstrapInterface
    {
        return $this->bootstrap(lazy: true);
    }

    private function eagerBootstrap(): BootstrapInterface
    {
        return $this->bootstrap(lazy: false);
    }

    private function bootstrap(bool $lazy): BootstrapInterface
    {
        return new class($lazy) implements BootstrapInterface {
            public function __construct(private readonly bool $lazy) {}

            public function configure(ContainerBuilder $builder): void
            {
                $builder->register(LazyProbe::class, LazyProbe::class)
                    ->setLazy($this->lazy)
                    ->setPublic(true);
            }

            public function platform(): string
            {
                return 'standalone';
            }

            public function getProjectRoot(): string
            {
                return '';
            }

            /** @return array<string, mixed> */
            public function getOptions(): array
            {
                return [];
            }
        };
    }
}
