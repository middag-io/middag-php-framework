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

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * HTTP kernel contract.
 *
 * PSR-15 RequestHandler. Accepts a PSR-7 ServerRequest and returns a PSR-7
 * Response. Internal implementation may bridge to HttpFoundation; the public
 * boundary is PSR-15 to integrate with PSR-15 middleware ecosystems and any
 * host's PSR-15 native router (Slim/FastRoute-based).
 *
 * @api
 */
interface HttpKernelInterface extends RequestHandlerInterface
{
    /**
     * Handle the request/response cycle.
     *
     * Caller is responsible for emitting the returned response. Sub-requests
     * (e.g. Inertia, HTMX) may invoke this handler recursively; callers should
     * not call `send()` on the returned response unless they own the entry point.
     */
    public function handle(ServerRequestInterface $request): ResponseInterface;
}
