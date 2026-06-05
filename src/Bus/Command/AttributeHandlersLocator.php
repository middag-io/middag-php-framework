<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Bus\Command;

use Middag\Framework\Bus\Attribute\AsCommandHandler;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Handler\HandlerDescriptor;
use Symfony\Component\Messenger\Handler\HandlersLocatorInterface;

/**
 * Resolves a message's handler through {@see AsCommandHandler} attributes.
 *
 * Wiring passes the handler service ids — expected to be class FQCNs (the
 * service-loader convention) whose classes carry the attribute. The locator
 * reflects each id once, lazily, into a message-class → handler-ids map. On a
 * hit, handlers are fetched from the DI container and yielded through Symfony
 * Messenger's {@see HandlersLocatorInterface}, exactly like
 * {@see ConventionHandlersLocator}.
 *
 * Pass a fallback locator (typically the convention locator) to keep
 * convention-named handlers working on the same bus: the fallback runs only
 * when no attribute binding matches the message.
 *
 * @api
 */
final class AttributeHandlersLocator implements HandlersLocatorInterface
{
    /** @var null|array<class-string, list<string>> message FQCN → handler service ids */
    private ?array $map = null;

    /**
     * @param ContainerInterface            $container  resolves handler service ids to instances
     * @param list<string>                  $handlerIds class-FQCN service ids carrying #[AsCommandHandler]
     * @param null|HandlersLocatorInterface $fallback   consulted when no attribute binding matches
     */
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly array $handlerIds,
        private readonly ?HandlersLocatorInterface $fallback = null,
    ) {}

    /**
     * @return iterable<int|string, HandlerDescriptor>
     */
    public function getHandlers(Envelope $envelope): iterable
    {
        $ids = $this->map()[$envelope->getMessage()::class] ?? [];

        if ($ids === []) {
            if ($this->fallback instanceof HandlersLocatorInterface) {
                yield from $this->fallback->getHandlers($envelope);
            }

            return;
        }

        foreach ($ids as $id) {
            if (!$this->container->has($id)) {
                continue;
            }

            $handler = $this->container->get($id);

            if (is_callable($handler)) {
                yield new HandlerDescriptor($handler);
            }
        }
    }

    /**
     * @return array<class-string, list<string>>
     */
    private function map(): array
    {
        if ($this->map !== null) {
            return $this->map;
        }

        $map = [];

        foreach ($this->handlerIds as $id) {
            if (!class_exists($id)) {
                continue;
            }

            foreach ((new ReflectionClass($id))->getAttributes(AsCommandHandler::class) as $attribute) {
                $map[$attribute->newInstance()->command][] = $id;
            }
        }

        return $this->map = $map;
    }
}
