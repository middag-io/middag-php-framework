<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Http\Response;

use DateTimeImmutable;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\Cache;

/**
 * Applies the HTTP cache headers declared by Symfony's `#[Cache]` attribute on a
 * controller action to the built {@see Response}.
 *
 * The framework's PSR-15 kernel does not run Symfony's HttpKernel event cycle,
 * so Symfony's own `CacheAttributeListener` never fires — a bare `#[Cache]`
 * would be inert. This applier reads the attribute by reflection (method, then
 * class) and maps the stable cache-control fields to `Response` setters, only
 * for cacheable status codes.
 *
 * Reuses Symfony's attribute CLASS (no own attribute), so it tracks the
 * installed Symfony version. The expression-backed fields (`etag`,
 * `lastModified`, `$if`) and version-specific extras are intentionally NOT
 * applied — declare those headers in the controller when needed.
 *
 * @internal
 */
final class CacheHeaderApplier
{
    /** Statuses Symfony's listener also treats as cacheable. */
    private const CACHEABLE_STATUSES = [200, 203, 300, 301, 302, 304, 404, 410];

    public static function apply(object $controller, string $method, Response $response): void
    {
        if (!in_array($response->getStatusCode(), self::CACHEABLE_STATUSES, true)) {
            return;
        }

        $cache = self::resolve($controller, $method);

        if (!$cache instanceof Cache) {
            return;
        }

        if ($cache->public === true) {
            $response->setPublic();
        }

        if ($cache->public === false) {
            $response->setPrivate();
        }

        if ($cache->maxage !== null) {
            $response->setMaxAge(self::toSeconds($cache->maxage));
        }

        if ($cache->smaxage !== null) {
            $response->setSharedMaxAge(self::toSeconds($cache->smaxage));
        }

        if ($cache->mustRevalidate) {
            $response->headers->addCacheControlDirective('must-revalidate');
        }

        if ($cache->expires !== null) {
            $response->setExpires(new DateTimeImmutable($cache->expires));
        }

        if ($cache->vary !== []) {
            $response->setVary($cache->vary, false);
        }
    }

    private static function resolve(object $controller, string $method): ?Cache
    {
        $attributes = (new ReflectionMethod($controller, $method))->getAttributes(Cache::class);

        if ($attributes === []) {
            $attributes = (new ReflectionClass($controller))->getAttributes(Cache::class);
        }

        return $attributes === [] ? null : $attributes[0]->newInstance();
    }

    private static function toSeconds(int|string $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (ctype_digit($value)) {
            return (int) $value;
        }

        return max(0, (int) strtotime($value) - time());
    }
}
