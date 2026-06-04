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
use Symfony\Component\HttpFoundation\Request;

/**
 * Request Resolver.
 *
 * Resolves Symfony Request instances for method injection.
 *
 * @internal
 *
 * @see MethodArgumentResolverInterface
 */
final readonly class RequestResolver implements MethodArgumentResolverInterface
{
    public function __construct(
        private Request $request
    ) {}

    /**
     * Determines if the parameter expects a Symfony Request instance.
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
            && is_a($type->getName(), Request::class, true);
    }

    /**
     * Returns the current request instance.
     *
     * @param ReflectionParameter  $parameter
     * @param array<string, mixed> $routeParams
     *
     * @return mixed
     */
    public function resolve(ReflectionParameter $parameter, array $routeParams): mixed
    {
        return $this->request;
    }
}
