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

use Psr\Container\ContainerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Handler\HandlerDescriptor;
use Symfony\Component\Messenger\Handler\HandlersLocatorInterface;

/**
 * Resolves a message's handler by naming convention: {MessageFQCN}Handler in the
 * same namespace, fetched from the DI container and invoked via __invoke().
 *
 * Batteries-included handler resolution for the framework {@see MessageBus},
 * expressed through Symfony Messenger's own {@see HandlersLocatorInterface} so
 * the convention plugs into the standard middleware pipeline instead of a
 * bespoke bus. Bootstraps that want explicit message→handler maps can bind a
 * different {@see HandlersLocatorInterface} on the same seam.
 *
 * Example: App\Item\CreateItem → App\Item\CreateItemHandler::__invoke().
 *
 * @api
 */
final readonly class ConventionHandlersLocator implements HandlersLocatorInterface
{
    public function __construct(private ContainerInterface $container) {}

    /**
     * @return iterable<int, HandlerDescriptor>
     */
    public function getHandlers(Envelope $envelope): iterable
    {
        $handlerClass = $envelope->getMessage()::class . 'Handler';

        if (!$this->container->has($handlerClass)) {
            return;
        }

        $handler = $this->container->get($handlerClass);

        if (is_callable($handler)) {
            yield new HandlerDescriptor($handler);
        }
    }
}
