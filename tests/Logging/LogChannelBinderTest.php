<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Logging;

use Middag\Framework\Kernel\ServiceProvider;
use Middag\Framework\Logging\Attribute\LogChannel;
use Middag\Framework\Logging\Contract\ActorResolverInterface;
use Middag\Framework\Logging\Contract\OriginResolverInterface;
use Middag\Framework\Logging\LoggerFactory;
use Middag\Framework\Logging\NullActorResolver;
use Middag\Framework\Logging\NullOriginResolver;
use Middag\Framework\Logging\Resolver\LogChannelBinder;
use Middag\Framework\Tests\Logging\Fixture\ChanneledService;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use stdClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * #[LogChannel] binds a service's injected logger to its declared
 * (module, channel) tuple during discovery.
 *
 * @internal
 */
#[CoversClass(LogChannelBinder::class)]
#[CoversClass(LogChannel::class)]
#[CoversClass(ServiceProvider::class)]
final class LogChannelBinderTest extends TestCase
{
    public function testBindsTheChannelLoggerDeclaredByTheAttribute(): void
    {
        $container = new ContainerBuilder();
        $this->registerEnabledLoggerFactory($container);
        $definition = $container->autowire(ChanneledService::class, ChanneledService::class)->setPublic(true);

        LogChannelBinder::apply($container, $definition, new ReflectionClass(ChanneledService::class));
        $container->compile();

        $service = $container->get(ChanneledService::class);
        self::assertInstanceOf(Logger::class, $service->logger);
        self::assertSame('reports/audit', $service->logger->getName());
    }

    public function testNoOpWhenClassHasNoAttribute(): void
    {
        $container = new ContainerBuilder();
        $this->registerEnabledLoggerFactory($container);
        $definition = new Definition(stdClass::class);

        LogChannelBinder::apply($container, $definition, new ReflectionClass(stdClass::class));

        self::assertSame([], $definition->getArguments());
    }

    public function testNoOpWhenLoggerFactoryIsNotRegistered(): void
    {
        $container = new ContainerBuilder();
        $definition = new Definition(ChanneledService::class);

        LogChannelBinder::apply($container, $definition, new ReflectionClass(ChanneledService::class));

        self::assertSame([], $definition->getArguments());
    }

    public function testWiredContainerBindsLoggingDefaults(): void
    {
        $container = new ContainerBuilder();
        ServiceProvider::register($container, __DIR__);
        $container->compile();

        self::assertInstanceOf(LoggerFactory::class, $container->get(LoggerFactory::class));
        self::assertInstanceOf(ActorResolverInterface::class, $container->get(ActorResolverInterface::class));
        self::assertInstanceOf(OriginResolverInterface::class, $container->get(OriginResolverInterface::class));
    }

    private function registerEnabledLoggerFactory(ContainerBuilder $container): void
    {
        $container->register(LoggerFactory::class, LoggerFactory::class)
            ->setArguments([
                sys_get_temp_dir(),
                new Definition(NullActorResolver::class),
                new Definition(NullOriginResolver::class),
                true,
            ])
            ->setPublic(true);
    }
}
