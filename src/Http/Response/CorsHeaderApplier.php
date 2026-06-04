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

use Middag\Framework\Http\Attribute\Cors;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the per-route CORS headers declared by `#[Cors]` (method, then class)
 * to the actual-request response.
 *
 * A wildcard origin list emits `Access-Control-Allow-Origin: *`; a specific list
 * echoes the request `Origin` only when it is allowed (and adds `Vary: Origin`),
 * otherwise no CORS header is emitted and the browser blocks the read. The
 * OPTIONS preflight is unaffected (the kernel keeps its global default).
 *
 * @internal
 */
final class CorsHeaderApplier
{
    public static function apply(object $controller, string $method, Request $request, Response $response): void
    {
        $cors = self::resolve($controller, $method);

        if (!$cors instanceof Cors) {
            return;
        }

        $allowOrigin = self::resolveAllowOrigin($cors->origins, $request->headers->get('Origin'));

        if ($allowOrigin === null) {
            return;
        }

        $headers = $response->headers;
        $headers->set('Access-Control-Allow-Origin', $allowOrigin);

        if ($cors->methods !== []) {
            $headers->set('Access-Control-Allow-Methods', implode(', ', $cors->methods));
        }

        if ($cors->headers !== []) {
            $headers->set('Access-Control-Allow-Headers', implode(', ', $cors->headers));
        }

        if ($cors->credentials) {
            $headers->set('Access-Control-Allow-Credentials', 'true');
        }

        if ($cors->exposeHeaders !== []) {
            $headers->set('Access-Control-Expose-Headers', implode(', ', $cors->exposeHeaders));
        }

        if ($cors->maxAge !== null) {
            $headers->set('Access-Control-Max-Age', (string) $cors->maxAge);
        }

        if ($allowOrigin !== '*') {
            $vary = $response->getVary();

            if (!in_array('Origin', $vary, true)) {
                $vary[] = 'Origin';
                $response->setVary($vary);
            }
        }
    }

    private static function resolve(object $controller, string $method): ?Cors
    {
        $attributes = (new ReflectionMethod($controller, $method))->getAttributes(Cors::class);

        if ($attributes === []) {
            $attributes = (new ReflectionClass($controller))->getAttributes(Cors::class);
        }

        return $attributes === [] ? null : $attributes[0]->newInstance();
    }

    /**
     * @param list<string> $origins
     */
    private static function resolveAllowOrigin(array $origins, ?string $requestOrigin): ?string
    {
        if (in_array('*', $origins, true)) {
            return '*';
        }

        if ($requestOrigin !== null && in_array($requestOrigin, $origins, true)) {
            return $requestOrigin;
        }

        return null;
    }
}
