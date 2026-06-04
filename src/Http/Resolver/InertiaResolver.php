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
use Middag\Framework\Http\Inertia\InertiaFactory;
use Psr\Container\ContainerInterface;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * Inertia Resolver.
 *
 * Supports injection of Inertia services/factories, if registered in the container.
 *
 * Host-agnostic by contract: the resolver consumes only the
 * framework method-argument-resolver contract plus the Inertia factory living
 * in `Middag\Framework\Http\Inertia`. Additional
 * Inertia classes (host adapters, UI descriptors) are supplied per-request
 * via the {@see self::__construct()} `$supported` array rather than
 * imported here, so adding a Moodle/WP-side Inertia service never requires
 * editing this file.
 *
 * @internal
 *
 * @see MethodArgumentResolverInterface
 */
final readonly class InertiaResolver implements MethodArgumentResolverInterface
{
    /** @var string[] List of supported class names */
    private array $supported;

    /**
     * @param ContainerInterface      $container
     * @param null|list<class-string> $supported
     */
    public function __construct(
        private ContainerInterface $container,
        ?array $supported = null
    ) {
        // Defaults to common Inertia classes if not provided
        $this->supported = $supported ?? [
            InertiaFactory::class,
        ];
    }

    /**
     * Checks whether the parameter expects a supported Inertia class.
     *
     * @param ReflectionParameter $parameter
     *
     * @return bool
     */
    public function supports(ReflectionParameter $parameter): bool
    {
        $type = $parameter->getType();

        return $type instanceof ReflectionNamedType
            && !$type->isBuiltin()
            && in_array($type->getName(), $this->supported, true)
            && $this->container->has($type->getName());
    }

    /**
     * Resolves the Inertia dependency from the container.
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
