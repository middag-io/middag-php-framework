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
use ReflectionException;
use ReflectionMethod;
use ReflectionParameter;
use RuntimeException;

/**
 * Method Parameter Resolver.
 *
 * Resolves controller method arguments using a chain of argument resolvers.
 * Includes reflection caching for performance to avoid overhead on repeated calls.
 *
 * @internal
 *
 * @see MethodArgumentResolverInterface
 */
class MethodParameterResolver
{
    /**
     * Cache of reflection parameters indexed by controller signature.
     *
     * @var array<string, array<int, ReflectionParameter>>
     */
    private static array $cache = [];

    /**
     * @param array<MethodArgumentResolverInterface> $resolvers ordered list of resolvers
     */
    public function __construct(
        private readonly array $resolvers
    ) {}

    /**
     * Builds the final ordered list of method arguments.
     *
     * @param object               $controller
     * @param string               $method
     * @param array<string, mixed> $routeParams
     *
     * @return array<int, mixed>
     *
     * @throws RuntimeException    if a parameter cannot be resolved
     * @throws ReflectionException
     */
    public function resolveArguments(
        object $controller,
        string $method,
        array $routeParams
    ): array {
        $signature = $controller::class . '::' . $method;

        if (!isset(self::$cache[$signature])) {
            self::$cache[$signature] = (new ReflectionMethod($controller, $method))->getParameters();
        }

        $params = self::$cache[$signature];
        $arguments = [];

        foreach ($params as $param) {
            $handled = false;

            foreach ($this->resolvers as $resolver) {
                if ($resolver->supports($param)) {
                    $arguments[] = $resolver->resolve($param, $routeParams);
                    $handled = true;

                    break;
                }
            }

            if (!$handled) {
                // If no resolver handled it, check for a default value (e.g. $page = 1)
                if ($param->isDefaultValueAvailable()) {
                    $arguments[] = $param->getDefaultValue();

                    continue;
                }

                // Critical failure: Controller expects an argument we cannot provide
                throw new RuntimeException(
                    sprintf('Unable to resolve argument $%s in %s. Check your type hints or route parameters.', $param->getName(), $signature)
                );
            }
        }

        return $arguments;
    }
}
