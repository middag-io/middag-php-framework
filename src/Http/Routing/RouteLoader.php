<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Http\Routing;

use Middag\Framework\Http\Contract\RouteLoaderInterface;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Route as SymfonyRoute;
use Symfony\Component\Routing\RouteCollection;

/**
 * Extracts #[Route] PHP 8 attributes from controller classes and registers
 * them in the Symfony RouteCollection.
 *
 * Platform-specific route loaders extend this with adapter-specific
 * base URL resolution and controller registration.
 *
 * @internal
 */
class RouteLoader implements RouteLoaderInterface
{
    /**
     * Load route annotations from a controller class into the route collection.
     *
     * Scans public methods for #[Route] attributes and registers each as a
     * Symfony Route with the controller as a service reference.
     */
    public function loadRoutes(
        RouteCollection $routes,
        ContainerInterface $container,
        ?string $className = null,
    ): void {
        if ($className === null) {
            return;
        }

        if (!class_exists($className)) {
            return;
        }

        $reflection = new ReflectionClass($className);

        if ($reflection->isAbstract()) {
            return;
        }

        // Register controller in container if not already registered.
        if ($container instanceof ContainerBuilder && !$container->has($className)) {
            $definition = $container->register($className);
            $definition->setAutowired(true);
            $definition->setPublic(true);
        }

        // Class-level #[Route] acts as a group: its path is a prefix and its
        // name a prefix for every method route.
        $classRouteAttrs = $reflection->getAttributes(Route::class);
        $classRoute = $classRouteAttrs === [] ? null : $classRouteAttrs[0]->newInstance();

        // Scan public methods for #[Route] attributes.
        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isConstructor()) {
                continue;
            }
            if ($method->isDestructor()) {
                continue;
            }
            $routeAttributes = $method->getAttributes(Route::class);

            foreach ($routeAttributes as $attribute) {
                /** @var Route $routeAttr */
                $routeAttr = $attribute->newInstance();

                $this->addRoute($routes, $routeAttr, $className, $method->getName(), $classRoute);
            }
        }
    }

    /**
     * Convert a Route attribute to a Symfony Route and add to the collection.
     *
     * A class-level #[Route] (when present) contributes a path prefix and a name
     * prefix — route groups without a separate config. The generated name
     * fallback uses the fully-qualified class so controllers sharing a short name
     * across namespaces no longer collide.
     */
    protected function addRoute(
        RouteCollection $routes,
        Route $routeAttr,
        string $controllerClass,
        string $method,
        ?Route $classRoute = null,
    ): void {
        $path = is_array($routeAttr->path) ? ($routeAttr->path[array_key_first($routeAttr->path)] ?? '') : (string) $routeAttr->path;
        $prefix = $classRoute instanceof Route ? (is_array($classRoute->path) ? $classRoute->path[array_key_first($classRoute->path)] ?? '' : (string) $classRoute->path) : ('');
        $path = $this->joinPath($prefix, $path);

        $name = $routeAttr->name;

        if ($name === '' || $name === null) {
            $name = strtolower(str_replace('\\', '_', $controllerClass) . '_' . $method);
        }

        $namePrefix = $classRoute instanceof Route ? (string) $classRoute->name : '';
        $name = $namePrefix . $name;

        $route = new SymfonyRoute(
            path: $path,
            defaults: [
                '_controller' => [$controllerClass, $method],
                ...$routeAttr->defaults,
            ],
            requirements: $routeAttr->requirements,
            methods: $routeAttr->methods,
        );

        $routes->add($name, $route);
    }

    /**
     * Join a class-level path prefix with a method path.
     */
    private function joinPath(string $prefix, string $path): string
    {
        if (trim($prefix, '/') === '') {
            return $path;
        }

        return '/' . trim($prefix, '/') . '/' . ltrim($path, '/');
    }
}
