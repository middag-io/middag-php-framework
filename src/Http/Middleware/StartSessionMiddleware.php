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

use Middag\Framework\Http\Contract\SessionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Opens the session at the front of the pipeline.
 *
 * The first default middleware: it starts the {@see SessionInterface} backend so
 * everything downstream — the `#[Auth]` gate, flash sharing, CSRF — can rely on
 * an active session. Replaces the `session_start()` boilerplate standalone hosts
 * otherwise hand-write in their front controller.
 *
 * @api
 */
final readonly class StartSessionMiddleware implements MiddlewareInterface
{
    public function __construct(
        private SessionInterface $session,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->session->start();

        return $handler->handle($request);
    }
}
