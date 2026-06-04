<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Http;

use Middag\Framework\Http\Contract\HttpKernelInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Server\RequestHandlerInterface;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\Request as SfRequest;
use Symfony\Component\HttpFoundation\Response as SfResponse;
use Symfony\Component\HttpKernel\HttpKernelInterface as SfHttpKernelInterface;
use Throwable;

/**
 * Standalone kernel — Symfony HttpKernel adapter over any PSR-15 handler.
 *
 * Bridges the Symfony HttpKernel lifecycle (HttpKernelInterface) to a PSR-15
 * RequestHandler. Use for deployment without Moodle or WordPress: CLI workers,
 * microservices, API-only containers.
 *
 * `$inner` is any PSR-15 {@see RequestHandlerInterface} — the framework's
 * {@see HttpKernelInterface} satisfies it, and
 * so does a PSR-15 middleware dispatcher, letting callers compose
 * StartSession/ShareFlash/VerifyCsrf in front of the kernel without a shim.
 *
 * @api
 */
class StandaloneKernel implements SfHttpKernelInterface
{
    private readonly Psr17Factory $psr17;

    private readonly HttpFoundationFactory $toFoundation;

    private readonly PsrHttpFactory $toPsr;

    public function __construct(
        private readonly RequestHandlerInterface $inner,
        private readonly bool $debug = false,
    ) {
        $this->psr17 = new Psr17Factory();
        $this->toFoundation = new HttpFoundationFactory();
        $this->toPsr = new PsrHttpFactory($this->psr17, $this->psr17, $this->psr17, $this->psr17);
    }

    /**
     * Handle a Symfony HttpFoundation Request and return an HttpFoundation Response.
     *
     * Converts: SfRequest → PSR-7 ServerRequest → PSR-7 Response → SfResponse
     */
    public function handle(SfRequest $request, int $type = self::MAIN_REQUEST, bool $catch = true): SfResponse
    {
        try {
            $psrRequest = $this->toPsr->createRequest($request);
            $psrResponse = $this->inner->handle($psrRequest);

            return $this->toFoundation->createResponse($psrResponse);
        } catch (Throwable $throwable) {
            if (!$catch) {
                throw $throwable;
            }

            // Honour the debug flag — render the shared developer exception
            // page (class/file:line/trace) instead of swallowing the trace behind
            // a bare 500. Production (debug off) still gets the opaque message.
            if ($this->debug) {
                return new SfResponse(
                    DebugExceptionRenderer::html($throwable),
                    SfResponse::HTTP_INTERNAL_SERVER_ERROR,
                    ['Content-Type' => 'text/html; charset=UTF-8'],
                );
            }

            return new SfResponse(
                'Internal Server Error',
                SfResponse::HTTP_INTERNAL_SERVER_ERROR,
            );
        }
    }
}
