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

use Middag\Framework\Bus\Command\CommandWorker;
use Middag\Framework\Bus\Command\ConventionHandlersLocator;
use Middag\Framework\Bus\MessageBus;
use Middag\Framework\Bus\MessageBusFactory;
use Middag\Framework\Bus\Transport\InMemoryTransport;
use Middag\Framework\Tests\Bus\Fixture\RecordCommand;
use Middag\Framework\Tests\Bus\Fixture\RecordCommandHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use RuntimeException;
use Symfony\Component\Messenger\Exception\NoHandlerForMessageException;
use Symfony\Component\Messenger\Transport\Sender\SendersLocator;

/**
 * One bus, two paths: dispatch is always the same call; routing decides whether
 * a message is handled inline (sync) or sent to a transport and drained (async).
 *
 * @internal
 */
#[CoversClass(MessageBus::class)]
#[CoversClass(MessageBusFactory::class)]
#[CoversClass(ConventionHandlersLocator::class)]
#[CoversClass(CommandWorker::class)]
#[CoversClass(InMemoryTransport::class)]
final class AsyncCommandBusTest extends TestCase
{
    private MessageBusFactory $factory;

    private ContainerInterface&MockObject $handlers;

    protected function setUp(): void
    {
        RecordCommandHandler::reset();

        // Convention: {RecordCommand}Handler resolves to the fixture handler.
        $this->handlers = $this->createMock(ContainerInterface::class);
        $this->handlers->method('has')->willReturnCallback(
            static fn (string $id): bool => $id === RecordCommand::class . 'Handler',
        );
        $this->handlers->method('get')->willReturnCallback(
            static fn (string $id): object => $id === RecordCommand::class . 'Handler'
                ? new RecordCommandHandler()
                : throw new RuntimeException('unbound: ' . $id),
        );

        $this->factory = new MessageBusFactory();
    }

    public function testDispatchHandlesSynchronouslyWhenNoRouting(): void
    {
        $bus = $this->factory->create($this->handlers);

        $bus->dispatch(new RecordCommand('inline'));

        // No sender routing → handled inline at dispatch.
        self::assertSame(['inline'], RecordCommandHandler::$handled);
    }

    public function testConventionLocatorThrowsWhenNoHandlerRegistered(): void
    {
        $empty = $this->createMock(ContainerInterface::class);
        $empty->method('has')->willReturn(false);
        $bus = $this->factory->create($empty);

        $this->expectException(NoHandlerForMessageException::class);

        $bus->dispatch(new RecordCommand('orphan'));
    }

    public function testRoutedDispatchSendsToTransportWithoutHandlingInline(): void
    {
        $transport = new InMemoryTransport();
        $bus = $this->factory->create($this->handlers, $this->sendersFor($transport));

        $bus->dispatch(new RecordCommand('queued'));

        // Async: routed to the transport, handler did NOT run at dispatch.
        self::assertSame([], RecordCommandHandler::$handled);
        self::assertCount(1, iterator_to_array($transport->get()));
    }

    public function testWorkerDrainsTransportAndHandlesThroughTheSameBus(): void
    {
        $transport = new InMemoryTransport();
        $bus = $this->factory->create($this->handlers, $this->sendersFor($transport));
        $worker = new CommandWorker($transport, $bus);

        $bus->dispatch(new RecordCommand('one'));
        $bus->dispatch(new RecordCommand('two'));

        $handled = $worker->drain();

        self::assertSame(2, $handled);
        self::assertSame(['one', 'two'], RecordCommandHandler::$handled);
        // Drained: nothing left, and the ReceivedStamp stops a re-send loop.
        self::assertSame(0, $worker->drain());
    }

    /**
     * Route RecordCommand to a single in-memory transport aliased "async".
     */
    private function sendersFor(InMemoryTransport $transport): SendersLocator
    {
        $senderContainer = $this->createMock(ContainerInterface::class);
        $senderContainer->method('has')->willReturn(true);
        $senderContainer->method('get')->willReturn($transport);

        return new SendersLocator([RecordCommand::class => ['async']], $senderContainer);
    }
}
