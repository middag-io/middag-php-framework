<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Kernel\Facade;

use BadMethodCallException;
use Middag\Framework\Kernel\Contract\FacadeInterface;
use Psr\Container\ContainerInterface;
use RuntimeException;

/**
 * Static proxy to a service resolved from the DI container.
 *
 * Concrete facades declare get_facade_accessor() returning the service
 * interface/class to resolve. All static method calls are forwarded
 * to the resolved instance via __callStatic.
 *
 * Test-friendly: swap() replaces instances, disable_cache() forces
 * fresh resolution on every call.
 *
 * @api
 */
abstract class AbstractFacade implements FacadeInterface
{
    /** Resolved instances cache. @var array<string, object> */
    protected static array $resolvedInstances = [];

    /** The container used for facade resolution. */
    protected static ?ContainerInterface $container = null;

    /** Whether instance caching is enabled. */
    protected static bool $cacheEnabled = true;

    /**
     * Forward static calls to the resolved facade instance.
     *
     * @param string       $method
     * @param array<mixed> $args
     *
     * @return mixed
     */
    public static function __callStatic(string $method, array $args): mixed
    {
        $instance = static::getFacadeRoot();

        if (!method_exists($instance, $method)) {
            throw new BadMethodCallException(
                sprintf(
                    'Method [%s] does not exist on [%s] (facade accessor: %s).',
                    $method,
                    $instance::class,
                    static::getFacadeAccessor(),
                ),
            );
        }

        return $instance->{$method}(...$args);
    }

    /**
     * Set the container used by all facades for service resolution.
     */
    public static function setFacadeContainer(ContainerInterface $container): void
    {
        static::$container = $container;
    }

    /**
     * Get the resolved facade root instance.
     */
    public static function getFacadeRoot(): object
    {
        $accessor = static::getFacadeAccessor();

        return static::resolveFacadeInstance($accessor);
    }

    /**
     * Swap the facade's resolved instance (for testing).
     */
    public static function swap(object $instance): void
    {
        $accessor = static::getFacadeAccessor();
        static::$resolvedInstances[$accessor] = $instance;
    }

    /**
     * Clear the resolved instance for this facade.
     */
    public static function clearResolvedInstance(): void
    {
        $accessor = static::getFacadeAccessor();
        unset(static::$resolvedInstances[$accessor]);
    }

    /**
     * Clear all resolved facade instances.
     */
    public static function clearResolvedInstances(): void
    {
        static::$resolvedInstances = [];
    }

    /**
     * Disable instance caching (each call resolves fresh from container).
     */
    public static function disableCache(): void
    {
        static::$cacheEnabled = false;
    }

    /**
     * Enable instance caching (default behavior).
     */
    public static function enableCache(): void
    {
        static::$cacheEnabled = true;
    }

    /**
     * Reset all state (clear instances, re-enable cache, remove container).
     */
    public static function reset(): void
    {
        static::clearResolvedInstances();
        static::$cacheEnabled = true;
        static::$container = null;
    }

    /**
     * Resolve the facade instance from the container.
     */
    protected static function resolveFacadeInstance(string $name): object
    {
        if (static::$cacheEnabled && isset(static::$resolvedInstances[$name])) {
            return static::$resolvedInstances[$name];
        }

        if (!static::$container instanceof ContainerInterface) {
            throw new RuntimeException(
                'Facade container not set. Call AbstractFacade::setFacadeContainer() during bootstrap.',
            );
        }

        $instance = static::$container->get($name);

        if (!is_object($instance)) {
            throw new RuntimeException(
                sprintf('Facade accessor [%s] did not resolve to an object.', $name),
            );
        }

        if (static::$cacheEnabled) {
            static::$resolvedInstances[$name] = $instance;
        }

        return $instance;
    }
}
