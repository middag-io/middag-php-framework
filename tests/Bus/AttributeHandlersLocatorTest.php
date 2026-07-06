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

use Middag\Framework\Bus\Attribute\AsCommandHandler;
use Middag\Framework\Bus\Command\AttributeHandlersLocator;
use Middag\Framework\Bus\Command\ConventionHandlersLocator;
use Middag\Framework\Bus\MessageBusFactory;
use Middag\Framework\Tests\Bus\Fixture\AsyncRoutedCommand;
use Middag\Framework\Tests\Bus\Fixture\AttributedNoArgHandler;
use Middag\Framework\Tests\Bus\Fixture\AttributedRecordHandler;
use Middag\Framework\Tests\Bus\Fixture\RecordCommand;
use Middag\Framework\Tests\Bus\Fixture\RecordCommandHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use RuntimeException;
use Symfony\Component\Messenger\Exception\NoHandlerForMessageException;

/**
 * Attribute bindings resolve handlers regardless of naming convention; the
 * fallback locator runs only when no attribute matches.
 *
 * @internal
 */
#[CoversClass(AttributeHandlersLocator::class)]
#[CoversClass(AsCommandHandler::class)]
final class AttributeHandlersLocatorTest extends TestCase
{
    private MessageBusFactory $factory;

    protected function setUp(): void
    {
        AttributedRecordHandler::reset();
        AttributedNoArgHandler::reset();
        RecordCommandHandler::reset();

        $this->factory = new MessageBusFactory();
    }

    public function testAttributeBindingResolvesNonConventionHandler(): void
    {
        $container = $this->containerWith([AttributedRecordHandler::class => new AttributedRecordHandler()]);

        $bus = $this->factory->create(
            $container,
            handlers: new AttributeHandlersLocator($container, [AttributedRecordHandler::class]),
        );

        $bus->dispatch(new RecordCommand('via-attribute'));

        self::assertSame(['via-attribute'], AttributedRecordHandler::$handled);
    }

    public function testNoArgInvokeHandlerIsCallableThroughTheBus(): void
    {
        // No senders locator → #[AsMessage('async')] routing is inert and the
        // command is handled inline; the handler's no-arg __invoke still runs.
        $container = $this->containerWith([AttributedNoArgHandler::class => new AttributedNoArgHandler()]);

        $bus = $this->factory->create(
            $container,
            handlers: new AttributeHandlersLocator($container, [AttributedNoArgHandler::class]),
        );

        $bus->dispatch(new AsyncRoutedCommand('ignored'));

        self::assertSame(1, AttributedNoArgHandler::$count);
    }

    public function testFallbackLocatorRunsWhenNoAttributeMatches(): void
    {
        // Attribute map only knows AsyncRoutedCommand; RecordCommand falls back
        // to the convention locator, which resolves {FQCN}Handler.
        $convention = $this->containerWith([RecordCommand::class . 'Handler' => new RecordCommandHandler()]);

        $bus = $this->factory->create(
            $convention,
            handlers: new AttributeHandlersLocator(
                $this->containerWith([AttributedNoArgHandler::class => new AttributedNoArgHandler()]),
                [AttributedNoArgHandler::class],
                new ConventionHandlersLocator($convention),
            ),
        );

        $bus->dispatch(new RecordCommand('via-fallback'));

        self::assertSame(['via-fallback'], RecordCommandHandler::$handled);
        self::assertSame(0, AttributedNoArgHandler::$count);
    }

    public function testAttributeMatchShadowsFallback(): void
    {
        // Both an attribute binding and a convention handler exist for
        // RecordCommand; only the attribute-bound handler runs.
        $container = $this->containerWith([
            AttributedRecordHandler::class => new AttributedRecordHandler(),
            RecordCommand::class . 'Handler' => new RecordCommandHandler(),
        ]);

        $bus = $this->factory->create(
            $container,
            handlers: new AttributeHandlersLocator(
                $container,
                [AttributedRecordHandler::class],
                new ConventionHandlersLocator($container),
            ),
        );

        $bus->dispatch(new RecordCommand('shadowed'));

        self::assertSame(['shadowed'], AttributedRecordHandler::$handled);
        self::assertSame([], RecordCommandHandler::$handled);
    }

    public function testThrowsWhenNothingMatchesAndNoFallback(): void
    {
        $empty = $this->containerWith([]);

        $bus = $this->factory->create(
            $empty,
            handlers: new AttributeHandlersLocator($empty, []),
        );

        $this->expectException(NoHandlerForMessageException::class);

        $bus->dispatch(new RecordCommand('unhandled'));
    }

    public function testUnregisteredHandlerIdIsSkipped(): void
    {
        // The attribute map binds the handler class, but the container cannot
        // resolve it → no handler yielded → the bus reports no handler.
        $empty = $this->containerWith([]);

        $bus = $this->factory->create(
            $empty,
            handlers: new AttributeHandlersLocator($empty, [AttributedRecordHandler::class]),
        );

        $this->expectException(NoHandlerForMessageException::class);

        $bus->dispatch(new RecordCommand('unresolvable'));
    }

    public function testMapIsCachedAcrossDispatchesAndNonClassIdsAreSkipped(): void
    {
        $container = $this->containerWith([AttributedRecordHandler::class => new AttributedRecordHandler()]);

        // A non-class id in the handler list must be skipped while building the
        // map; a second dispatch must reuse the memoised map (cache-hit branch).
        $bus = $this->factory->create(
            $container,
            handlers: new AttributeHandlersLocator($container, ['not.a.real.class', AttributedRecordHandler::class]),
        );

        $bus->dispatch(new RecordCommand('first'));
        $bus->dispatch(new RecordCommand('second'));

        self::assertSame(['first', 'second'], AttributedRecordHandler::$handled);
    }

    /**
     * @param array<string, object> $services
     */
    private function containerWith(array $services): ContainerInterface
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnCallback(
            static fn (string $id): bool => isset($services[$id]),
        );
        $container->method('get')->willReturnCallback(
            static fn (string $id): object => $services[$id]
                ?? throw new RuntimeException('unbound: ' . $id),
        );

        return $container;
    }
}
