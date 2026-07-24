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
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionMethod;
use SplFileInfo;
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

        $this->loadRoutesReflected($routes, $container, new ReflectionClass($className));
    }

    /**
     * Opt-in route auto-discovery: enumerate every controller under the given
     * directories, collect its #[Route] attributes, and register them in the
     * collection — no manual class-by-class {@see loadRoutes()} list.
     *
     * This is the discovery → RouteCollection bridge (issue #61). The manual
     * {@see loadRoutes()} path is untouched and remains the default; a host
     * calls this only when it wants convention-based registration. Each module
     * or extension contributes its own controller directory, so pass the union
     * of those directories here.
     *
     * Registration order is deterministic: candidate controllers are sorted by
     * fully-qualified class name before loading, so the resulting collection is
     * identical regardless of directory argument order or filesystem read order.
     * A class discovered through two directories is loaded once (deduplicated by
     * FQCN). Only concrete classes that actually carry a #[Route] (class-level or
     * on a public method) are registered — a plain class without routes is
     * skipped entirely and never enters the container.
     *
     * Compiled-route caching is intentionally out of scope: the matcher is
     * rebuilt per request today (see HttpKernel), so discovery runs per request
     * as well. No host (Moodle/WordPress) dependency is used.
     *
     * @param list<string> $directories absolute directory paths to scan recursively
     */
    public function discoverRoutes(
        RouteCollection $routes,
        ContainerInterface $container,
        array $directories,
    ): void {
        $controllers = [];

        foreach ($directories as $directory) {
            if (!is_dir($directory)) {
                continue;
            }

            foreach ($this->routableControllersIn($directory) as $className) {
                $controllers[$className] = true;
            }
        }

        $ordered = array_keys($controllers);
        sort($ordered);

        foreach ($ordered as $className) {
            $this->loadRoutesReflected($routes, $container, new ReflectionClass($className));
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
     * Find every concrete, route-carrying controller class declared under a
     * directory tree.
     *
     * @return list<class-string>
     */
    private function routableControllersIn(string $directory): array
    {
        $found = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $className = $this->fqcnFromFile($file->getPathname());
            if ($className === null) {
                continue;
            }
            if (!class_exists($className)) {
                continue;
            }

            $reflection = new ReflectionClass($className);
            if ($reflection->isAbstract()) {
                continue;
            }
            if (!$this->hasAnyRoute($reflection)) {
                continue;
            }

            $found[] = $className;
        }

        return $found;
    }

    /**
     * Does the class declare a #[Route] at class level or on any public method?
     *
     * @param ReflectionClass<object> $reflection
     */
    private function hasAnyRoute(ReflectionClass $reflection): bool
    {
        if ($reflection->getAttributes(Route::class) !== []) {
            return true;
        }

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getAttributes(Route::class) !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract the single fully-qualified class name declared in a PHP file by
     * tokenizing its `namespace` + `class` declarations.
     *
     * The tokenizer is deliberate: discovered controllers live in module and
     * extension trees with their own PSR-4 roots, so a path→namespace convention
     * (as in ServiceProvider) does not generalize. Interfaces, traits and enums
     * are ignored — only a `class` declaration yields an FQCN. Anonymous
     * classes (`new class`) and the `::class` constant are skipped.
     *
     * @return null|class-string
     */
    private function fqcnFromFile(string $path): ?string
    {
        $source = file_get_contents($path);
        if ($source === false) {
            return null;
        }

        $tokens = token_get_all($source);
        $namespace = '';
        $count = count($tokens);

        for ($i = 0; $i < $count; ++$i) {
            $token = $tokens[$i];
            if (!is_array($token)) {
                continue;
            }

            if ($token[0] === T_NAMESPACE) {
                $namespace = $this->readName($tokens, $i);

                continue;
            }

            if ($token[0] === T_CLASS) {
                $prev = $this->previousSignificant($tokens, $i);
                if (is_array($prev) && $prev[0] === T_DOUBLE_COLON) {
                    continue;
                }

                $name = $this->readName($tokens, $i);
                if ($name === '') {
                    continue;
                }

                return $namespace === '' ? $name : $namespace . '\\' . $name;
            }
        }

        return null;
    }

    /**
     * Read the namespaced name that follows a `namespace` or `class` keyword at
     * index $i, advancing $i past the consumed name tokens.
     *
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens
     */
    private function readName(array $tokens, int &$i): string
    {
        $name = '';
        $count = count($tokens);
        $j = $i + 1;

        for (; $j < $count; ++$j) {
            $token = $tokens[$j];

            if (is_array($token)) {
                if (in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                    if ($name !== '') {
                        break;
                    }

                    continue;
                }

                if (in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NS_SEPARATOR], true)) {
                    $name .= $token[1];

                    continue;
                }
            }

            break;
        }

        $i = $j;

        return trim($name, '\\');
    }

    /**
     * The nearest non-whitespace, non-comment token before index $i.
     *
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens
     *
     * @return null|array{0: int, 1: string, 2: int}|string
     */
    private function previousSignificant(array $tokens, int $i): array|string|null
    {
        for ($j = $i - 1; $j >= 0; --$j) {
            $token = $tokens[$j];
            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            return $token;
        }

        return null;
    }

    /**
     * Register the routes declared by an already-reflected controller class.
     *
     * @param ReflectionClass<object> $reflection
     */
    private function loadRoutesReflected(
        RouteCollection $routes,
        ContainerInterface $container,
        ReflectionClass $reflection,
    ): void {
        if ($reflection->isAbstract()) {
            return;
        }

        $className = $reflection->getName();

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
