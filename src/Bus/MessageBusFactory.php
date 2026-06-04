<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Bus;

use Middag\Framework\Bus\Command\CommandWorker;
use Middag\Framework\Bus\Command\ConventionHandlersLocator;
use Middag\Framework\Bus\Contract\MessageBusInterface;
use Middag\Framework\Bus\Middleware\ProfilingMiddleware;
use Psr\Container\ContainerInterface;
use Symfony\Component\Messenger\Handler\HandlersLocatorInterface;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\SendMessageMiddleware;
use Symfony\Component\Messenger\Transport\Sender\SendersLocatorInterface;

/**
 * Assembles the default MIDDAG {@see MessageBus} with the standard middleware
 * stack — batteries-included, zero-config sync out of the box.
 *
 * With no {@see SendersLocatorInterface} every message is handled synchronously
 * (only {@see HandleMessageMiddleware} runs). Pass a senders locator to route
 * message types to transports: {@see SendMessageMiddleware} then sends matched
 * messages to their transport (async) instead of handling inline, and
 * {@see CommandWorker} drains them later.
 *
 * Handlers resolve through {@see ConventionHandlersLocator} by default; pass a
 * custom {@see HandlersLocatorInterface} to use explicit maps instead.
 *
 * Pass `$middleware` to prepend cross-cutting middleware ahead of the
 * send/handle stack — e.g. {@see ProfilingMiddleware} to give the profiler a
 * `bus` timeline. Prepended middleware run first, so they wrap both routing
 * and handling.
 *
 * @api
 */
final readonly class MessageBusFactory
{
    /**
     * @param iterable<MiddlewareInterface> $middleware prepended ahead of send/handle
     */
    public function create(
        ContainerInterface $handlerContainer,
        ?SendersLocatorInterface $senders = null,
        ?HandlersLocatorInterface $handlers = null,
        iterable $middleware = [],
    ): MessageBusInterface {
        $stack = [];

        foreach ($middleware as $mw) {
            $stack[] = $mw;
        }

        if ($senders instanceof SendersLocatorInterface) {
            $stack[] = new SendMessageMiddleware($senders);
        }

        $stack[] = new HandleMessageMiddleware(
            $handlers ?? new ConventionHandlersLocator($handlerContainer),
        );

        return new MessageBus($stack);
    }
}
