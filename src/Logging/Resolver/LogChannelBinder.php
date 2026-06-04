<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Logging\Resolver;

use Middag\Framework\Logging\Attribute\LogChannel;
use Middag\Framework\Logging\LoggerFactory;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use ReflectionNamedType;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Binds a service's `LoggerInterface` constructor argument(s) to the
 * `(module, channel)` logger declared by `#[LogChannel]` on the class.
 *
 * Invoked during service discovery. A no-op when the class carries no
 * `#[LogChannel]`, when `LoggerFactory` is not registered, or when the
 * constructor has no `LoggerInterface` parameter. Each matching parameter is
 * wired (by name) to an inline `LoggerFactory::forChannel(module, channel)`
 * factory, overriding plain autowiring for that argument.
 *
 * @internal
 */
final class LogChannelBinder
{
    /**
     * @param ReflectionClass<object> $reflection
     */
    public static function apply(ContainerBuilder $container, Definition $definition, ReflectionClass $reflection): void
    {
        $attributes = $reflection->getAttributes(LogChannel::class);

        if ($attributes === [] || !$container->has(LoggerFactory::class)) {
            return;
        }

        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return;
        }

        $channel = $attributes[0]->newInstance();

        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();
            if (!$type instanceof ReflectionNamedType) {
                continue;
            }
            if ($type->getName() !== LoggerInterface::class) {
                continue;
            }

            $logger = (new Definition(LoggerInterface::class))
                ->setFactory([new Reference(LoggerFactory::class), 'forChannel'])
                ->setArguments([$channel->module, $channel->channel]);

            $definition->setArgument('$' . $parameter->getName(), $logger);
        }
    }
}
