<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Http\Contract;

use ReflectionParameter;

/**
 * Resolves a single controller method argument.
 *
 * Resolvers are tried in registration order: the first whose {@see supports()}
 * returns true wins, so the order in which they are registered encodes priority.
 * A resolver that cannot handle a parameter must return false from supports()
 * rather than guessing inside resolve().
 *
 * @internal
 */
interface MethodArgumentResolverInterface
{
    /**
     * Whether this resolver can provide a value for the given parameter.
     */
    public function supports(ReflectionParameter $parameter): bool;

    /**
     * Resolve the parameter value. Only invoked after supports() returned true.
     *
     * @param array<string, mixed> $routeParams the parameters matched from the route
     */
    public function resolve(ReflectionParameter $parameter, array $routeParams): mixed;
}
