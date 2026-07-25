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

use InvalidArgumentException;
use Middag\Framework\Http\Attribute\Middleware;
use Middag\Framework\Http\Contract\RouteMiddlewareInterface;
use Middag\Framework\Http\HttpKernel;

/**
 * The ordered route-middleware chain of one matched route, before resolution.
 *
 * Two sources feed a route's chain and this value object is where they meet:
 *
 * 1. **The route registrar** — whatever built the `RouteCollection` (a host
 *    adapter, or a fluent `Route::middleware()` facade downstream) declares ids
 *    imperatively on the Symfony route defaults, under {@see MIDDLEWARE_DEFAULT}
 *    and {@see WITHOUT_MIDDLEWARE_DEFAULT}. Both are plain `list<string>` — the
 *    framework models no host vocabulary here, only the two default keys and
 *    their merge semantics.
 * 2. **The controller** — the {@see Middleware} attribute on the action's class
 *    and method, appended by {@see HttpKernel} via {@see self::append()}.
 *
 * **Order (outermost first).** Broader declaration scope wraps narrower, which is
 * the same axis the attribute already follows (class outermost, method innermost):
 *
 * ```
 * route defaults  →  class #[Middleware]  →  method #[Middleware]  →  action
 * ```
 *
 * Registration-site (and therefore group) middleware is the broadest scope and
 * runs outermost — matching Laravel's group-wraps-controller semantics.
 *
 * **Deduplication.** An id named by more than one source runs exactly once, at its
 * **first** (outermost) position, so the chain is a deterministic function of the
 * declarations regardless of how many of them repeat an id.
 *
 * **Exclusion.** {@see WITHOUT_MIDDLEWARE_DEFAULT} is subtracted from the whole
 * merged list — it removes an id inherited from an enclosing group *and* one
 * contributed by a `#[Middleware]` attribute. That is why a registrar records the
 * exclusion list alongside the inclusion list instead of only pre-subtracting it:
 * the attribute-derived half of the chain is not visible at registration time.
 *
 * A malformed default (a non-list, or an entry that is not a non-empty string)
 * throws instead of being silently skipped — a misdeclared chain is a
 * configuration bug, and a silently dropped middleware is a security hole.
 *
 * Ids are resolved to {@see RouteMiddlewareInterface} instances by the kernel,
 * which owns the container; this object stays a pure value.
 *
 * @api
 */
final readonly class RouteMiddlewareStack
{
    /** Route default carrying the middleware ids declared for the route. */
    public const MIDDLEWARE_DEFAULT = '_middleware';

    /** Route default carrying the middleware ids excluded from the route's chain. */
    public const WITHOUT_MIDDLEWARE_DEFAULT = '_without_middleware';

    /**
     * @param string       $routeName the matched route's name, for error messages
     * @param list<string> $declared  declared ids, outermost first, duplicates allowed
     * @param list<string> $excluded  ids removed from the merged chain
     */
    private function __construct(
        public string $routeName,
        private array $declared,
        private array $excluded,
    ) {}

    /**
     * Read the chain a route declares on its Symfony defaults.
     *
     * A route that declares neither key yields an empty stack, so an
     * attribute-only route pays nothing for this path.
     *
     * @param array<string, mixed> $defaults the matched route's parameters/defaults
     *
     * @throws InvalidArgumentException on a malformed middleware default
     */
    public static function fromRouteDefaults(string $routeName, array $defaults): self
    {
        return new self(
            $routeName,
            self::readIds($routeName, $defaults, self::MIDDLEWARE_DEFAULT),
            self::readIds($routeName, $defaults, self::WITHOUT_MIDDLEWARE_DEFAULT),
        );
    }

    /**
     * Append ids inside the current chain (they run closer to the action).
     *
     * Used by the kernel to fold the `#[Middleware]` attribute chain in after the
     * route-default one. Returns the same instance when there is nothing to add.
     *
     * @param list<string> $ids
     */
    public function append(array $ids): self
    {
        if ($ids === []) {
            return $this;
        }

        return new self($this->routeName, [...$this->declared, ...$ids], $this->excluded);
    }

    /**
     * The effective chain: declared order, first occurrence of each id, minus the
     * excluded ones. Outermost first.
     *
     * @return list<string>
     */
    public function ids(): array
    {
        $ids = [];

        foreach ($this->declared as $id) {
            if (in_array($id, $this->excluded, true)) {
                continue;
            }

            if (in_array($id, $ids, true)) {
                continue;
            }

            $ids[] = $id;
        }

        return $ids;
    }

    /**
     * Read and validate one middleware default.
     *
     * @param array<string, mixed> $defaults
     *
     * @return list<string>
     *
     * @throws InvalidArgumentException when the default is not a list of non-empty strings
     */
    private static function readIds(string $routeName, array $defaults, string $key): array
    {
        $value = $defaults[$key] ?? [];

        if (!is_array($value)) {
            throw new InvalidArgumentException(sprintf(
                'Route "%s" declares a malformed "%s" route default: expected a list of middleware ids, got %s.',
                $routeName,
                $key,
                get_debug_type($value),
            ));
        }

        $ids = [];

        foreach ($value as $id) {
            if (!is_string($id) || trim($id) === '') {
                throw new InvalidArgumentException(sprintf(
                    'Route "%s" declares a malformed middleware id in the "%s" route default: expected a non-empty string, got %s.',
                    $routeName,
                    $key,
                    is_string($id) ? 'an empty string' : get_debug_type($id),
                ));
            }

            $ids[] = $id;
        }

        return $ids;
    }
}
