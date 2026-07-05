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
use ReflectionNamedType;
use ReflectionParameter;
use RuntimeException;

/**
 * Route Parameter Resolver.
 *
 * Resolves parameters mapped from the route definition (e.g., {id}, {slug}).
 * Acts as a fallback for scalar types (int, string) or untyped arguments.
 * Performs automatic type casting for basic scalar types.
 *
 * @internal
 *
 * @see MethodArgumentResolverInterface
 */
final class RouteParameterResolver implements MethodArgumentResolverInterface
{
    /**
     * Indicates whether this resolver should handle the parameter.
     *
     * @param ReflectionParameter $parameter
     *
     * @return bool
     */
    public function supports(ReflectionParameter $parameter): bool
    {
        $type = $parameter->getType();

        // 1. If it has no type, assume it's a route scalar.
        if ($type === null) {
            return true;
        }

        // 2. If it is a built-in type (string, int, bool), it's likely a route param.
        // We AVOID handling Objects/Classes here to prevent stealing Service Injection.
        return $type instanceof ReflectionNamedType && $type->isBuiltin();
    }

    /**
     * Resolve the scalar/route parameter, applying type casting when needed.
     *
     * @param ReflectionParameter  $parameter
     * @param array<string, mixed> $routeParams
     *
     * @throws RuntimeException when the parameter is missing and no default is provided
     */
    public function resolve(ReflectionParameter $parameter, array $routeParams): mixed
    {
        $name = $parameter->getName();

        // 1. Check strict match in route parameters
        if (array_key_exists($name, $routeParams)) {
            $value = $routeParams[$name];

            // Automatic Type Casting
            // Routes often return strings (from URL), but Controllers might expect strict types.
            $type = $parameter->getType();
            if ($type instanceof ReflectionNamedType) {
                return match ($type->getName()) {
                    'int' => (int) $value,
                    'bool' => filter_var($value, FILTER_VALIDATE_BOOL),
                    'float' => (float) $value,
                    default => $value,
                };
            }

            return $value;
        }

        // 2. Fallback to default value if defined in method signature
        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        throw new RuntimeException(sprintf("Route parameter '%s' is missing and has no default value.", $name));
    }
}
