<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Http\Middleware;

use Middag\Framework\Http\HttpKernel;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * PSR-15 middleware pipeline.
 *
 * Composes a stack of {@see MiddlewareInterface} in front of an inner
 * {@see RequestHandlerInterface} (the {@see HttpKernel}).
 * Each middleware wraps the next; the innermost delegate is the kernel. This is
 * the seam the framework's "PSR-15" branding always implied but never shipped —
 * cross-cutting concerns (session start, auth gate, CSRF, flash sharing,
 * logging) now have somewhere to live instead of being copy-pasted into every
 * controller.
 *
 * Itself a {@see RequestHandlerInterface}, so it is a drop-in replacement for
 * the bare kernel and re-entrant for sub-requests: each {@see self::handle()}
 * builds fresh delegates rather than mutating shared state.
 *
 * @api
 */
final readonly class MiddlewareDispatcher implements RequestHandlerInterface
{
    /** @var list<MiddlewareInterface> */
    private array $queue;

    public function __construct(
        private RequestHandlerInterface $kernel,
        MiddlewareInterface ...$middlewares,
    ) {
        $this->queue = array_values($middlewares);
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $queue = $this->queue;
        $next = array_shift($queue);

        if (!$next instanceof MiddlewareInterface) {
            return $this->kernel->handle($request);
        }

        return $next->process($request, new self($this->kernel, ...$queue));
    }
}
