<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Bus;

use Middag\Framework\Bus\Command\ConventionHandlersLocator;
use Middag\Framework\Bus\Contract\MessageBusInterface;
use Middag\Framework\Bus\MessageBus;
use Middag\Framework\Bus\MessageBusFactory;
use Middag\Framework\Bus\Transport\InMemoryTransport;
use Middag\Framework\Bus\Transport\TransportLocator;
use Middag\Framework\Kernel\ServiceProvider;
use Middag\Framework\Tests\Bus\Fixture\AsyncRoutedCommand;
use Middag\Framework\Tests\Bus\Fixture\AsyncRoutedCommandHandler;
use Middag\Framework\Tests\Bus\Fixture\RecordCommand;
use Middag\Framework\Tests\Bus\Fixture\RecordCommandHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

/**
 * The framework wires a working MessageBus at boot (ServiceProvider core
 * bindings): a plain command runs synchronously, while a command marked
 * Symfony's #[AsMessage('async')] is routed to the default async transport
 * via an empty-map SendersLocator (the attribute, not config, decides).
 *
 * @internal
 */
#[CoversClass(ServiceProvider::class)]
#[CoversClass(MessageBusFactory::class)]
#[CoversClass(TransportLocator::class)]
#[CoversClass(MessageBus::class)]
#[CoversClass(ConventionHandlersLocator::class)]
final class BusDefaultsTest extends TestCase
{
    public function testCreateWrapsPrependedMiddlewareIntoTheStack(): void
    {
        $middleware = new class implements MiddlewareInterface {
            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                return $stack->next()->handle($envelope, $stack);
            }
        };

        // Passing $middleware exercises the prepend loop; a MessageBus is returned.
        $bus = (new MessageBusFactory())->create(new ContainerBuilder(), middleware: [$middleware]);

        self::assertInstanceOf(MessageBus::class, $bus);
    }

    public function testBusInterfaceIsWiredAndPlainCommandRunsSynchronously(): void
    {
        $container = $this->boot();
        RecordCommandHandler::reset();

        $bus = $container->get(MessageBusInterface::class);
        self::assertInstanceOf(MessageBusInterface::class, $bus);

        $bus->dispatch(new RecordCommand('sync'));

        // No #[AsMessage] → handled inline at dispatch.
        self::assertSame(['sync'], RecordCommandHandler::$handled);
    }

    public function testAsMessageCommandRoutesToAsyncTransport(): void
    {
        $container = $this->boot();
        AsyncRoutedCommandHandler::reset();

        $container->get(MessageBusInterface::class)->dispatch(new AsyncRoutedCommand('queued'));

        // #[AsMessage('async')] → sent to the transport, NOT handled inline.
        self::assertSame([], AsyncRoutedCommandHandler::$handled);
        self::assertCount(1, iterator_to_array($container->get(InMemoryTransport::class)->get()));
    }

    private function boot(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        // Base ServiceProvider has no SCAN_DIRS, so this only runs the core
        // bindings (incl. the bus defaults under test). $basePath is unused here.
        ServiceProvider::register($container, __DIR__);

        // Convention handlers: {Command}Handler resolved from the container.
        $container->register(RecordCommand::class . 'Handler', RecordCommandHandler::class)->setPublic(true);
        $container->register(AsyncRoutedCommand::class . 'Handler', AsyncRoutedCommandHandler::class)->setPublic(true);

        $container->compile();

        return $container;
    }
}
