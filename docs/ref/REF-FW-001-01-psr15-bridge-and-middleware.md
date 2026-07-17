---
ref: REF-FW-001-01
adr: FW-001
title: 'PSR-15 Bridge Wiring & Middleware Catalog'
lang: en
---

# REF-FW-001-01: PSR-15 Bridge Wiring & Middleware Catalog

> Detail supporting [FW-001](../decisions/FW-001-psr15-http-kernel-boundary.md). Reconstructed from the `moodle-local_middag` legacy vault (ADR-208, ADR-209 — neither has a REF companion in the source vault; this material is drawn from the ADR bodies themselves).

## The rejected shape (ADR-208, historical)

`ADR-208` proposed `http_kernel` implement `Symfony\Component\HttpKernel\HttpKernel` directly, with `handle(Request $request, ...): Response` built on pure `HttpFoundation`. It was superseded five weeks later by ADR-209 without ever shipping — the value of keeping it here is showing the evolution (Symfony HttpKernel as public contract → PSR-15 boundary), since a framework-bound public interface is exactly the failure mode a portable Composer package (FW-011) cannot afford.

## Public contract shape

```php
namespace Middag\Framework\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

interface HttpKernelInterface extends RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface;
}
```

## The internal bridge (technical boundary, decision PD-041 option B)

PSR-15 is the **public** boundary; internally the kernel converts to Symfony HttpFoundation via `HttpFoundationFactory::createRequest()`. Routing (`symfony/routing` `UrlMatcher`), the resolver chain (see FW-002/REF-FW-002-01) and controllers stay HttpFoundation `Request -> Response` — controller signatures are unaffected by this ADR. The final bridge step converts the HttpFoundation `Response` back to a PSR-7 `ResponseInterface` on the way out of `handle()`. Rationale recorded at the time: this reduced touch surface from all controllers (~140 in the legacy plugin) to a handful of kernel-boundary files, and it kept the DX of `JsonResponse`/`BinaryFileResponse`/`StreamedResponse`/`RedirectResponse` that a pure-PSR-7 controller layer would have lost.

## Native PSR-15 middleware pipeline

```php
namespace Middag\Framework\Kernel\Http;

final class MiddlewarePipeline implements RequestHandlerInterface
{
    /** @param MiddlewareInterface[] $middlewares */
    public function __construct(private array $middlewares, private RequestHandlerInterface $core) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if ($this->middlewares === []) {
            return $this->core->handle($request);
        }
        $next = array_shift($this->middlewares);

        return $next->process($request, $this);
    }
}
```

CORS, exception handling and the pre-dispatch hook migrate to middleware classes rather than framework-specific lifecycle callbacks — this is the pattern the current `src/Http/Middleware/` directory implements (`CorsMiddleware`, `MiddlewareDispatcher`, `ShareFlashMiddleware`, `StartSessionMiddleware`, `VerifyCsrfMiddleware`). Exception-to-response mapping itself now lives in dedicated renderers (`Http/DefaultExceptionRenderer.php`, `Http/DebugExceptionRenderer.php`) rather than a middleware class — a naming detail this ADR could not have predicted, since it was written before the exception hierarchy (FW-010) existed in its current form.

## Dependency set (confirmed present in the real package)

`psr/http-server-handler`, `psr/http-server-middleware`, `psr/http-message`, `symfony/psr-http-message-bridge`, `nyholm/psr7` — all five are `require` entries in this repo's `composer.json` today, exactly matching the list ADR-209 specified as net-new.

## Compatibility note (historical)

At the time of ADR-209, this was recorded as backward-compatible for the ~140 existing Moodle-plugin controllers, with one explicit breaking change: `middag::handle()`'s signature moved from `void` to `ResponseInterface`, requiring a deprecation policy for any external consumer. The three-phase rollout the ADR sketched (framework lib → plugin `local_middag` → cleanup) is superseded by the fact that the framework now ships as its own Composer package (FW-011) — the phased-migration framing no longer applies to this repo, only to products still consuming an older in-plugin copy.
