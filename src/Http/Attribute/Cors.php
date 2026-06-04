<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Http\Attribute;

use Attribute;
use InvalidArgumentException;

/**
 * Declares per-route CORS for a controller action (method- or class-level).
 *
 * Applied to the actual-request response (the GET/POST/… response), setting the
 * `Access-Control-Allow-*` headers; a non-wildcard origin list echoes the
 * request `Origin` only when it is on the list and adds `Vary: Origin`. The
 * OPTIONS preflight stays on the kernel's global default — this attribute
 * overrides the response headers, not the preflight.
 *
 * Symfony has no CORS attribute (its ecosystem uses NelmioCorsBundle's YAML
 * config), so this is framework-owned and platform-agnostic.
 *
 * @api
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
final readonly class Cors
{
    /**
     * @param list<string> $origins       allowed origins, or `['*']` for any
     * @param list<string> $methods       `Access-Control-Allow-Methods`
     * @param list<string> $headers       `Access-Control-Allow-Headers`
     * @param list<string> $exposeHeaders `Access-Control-Expose-Headers`
     */
    public function __construct(
        public array $origins = ['*'],
        public array $methods = [],
        public array $headers = [],
        public bool $credentials = false,
        public array $exposeHeaders = [],
        public ?int $maxAge = null,
    ) {
        if ($credentials && in_array('*', $origins, true)) {
            throw new InvalidArgumentException('CORS: credentials cannot be combined with the wildcard origin "*".');
        }
    }
}
