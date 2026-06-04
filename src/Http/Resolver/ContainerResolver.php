<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Http\Resolver;

use Middag\Framework\Http\Contract\MethodArgumentResolverInterface;
use Psr\Container\ContainerInterface;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * Container Resolver.
 *
 * Resolves dependencies from the DI container by type-hint.
 * Handles standard Service Injection.
 *
 * @internal
 *
 * @see MethodArgumentResolverInterface
 */
final readonly class ContainerResolver implements MethodArgumentResolverInterface
{
    public function __construct(
        private ContainerInterface $container
    ) {}

    /**
     * Checks if the container can resolve the given parameter by type-hint.
     *
     * @param ReflectionParameter $parameter
     *
     * @return bool
     */
    public function supports(ReflectionParameter $parameter): bool
    {
        $type = $parameter->getType();

        // We only support Named Types (classes/interfaces), not Union/Intersection types for now.
        // Complex types are harder to match against a single container entry.
        return $type instanceof ReflectionNamedType
            && !$type->isBuiltin()
            && $this->container->has($type->getName());
    }

    /**
     * Resolves the dependency from the container.
     *
     * @param ReflectionParameter  $parameter
     * @param array<string, mixed> $routeParams
     *
     * @return mixed
     */
    public function resolve(ReflectionParameter $parameter, array $routeParams): mixed
    {
        /** @var ReflectionNamedType $type */
        $type = $parameter->getType();

        return $this->container->get($type->getName());
    }
}
