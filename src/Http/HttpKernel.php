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

use Middag\Framework\Exception\MiddagAuthenticationException;
use Middag\Framework\Exception\MiddagException;
use Middag\Framework\Exception\MiddagValidationException;
use Middag\Framework\Http\Attribute\Auth;
use Middag\Framework\Http\Attribute\Middleware;
use Middag\Framework\Http\Contract\AuthenticatorInterface;
use Middag\Framework\Http\Contract\CapabilityRequirementAwareInterface;
use Middag\Framework\Http\Contract\ControllerInterface;
use Middag\Framework\Http\Contract\ExceptionRendererInterface;
use Middag\Framework\Http\Contract\HttpKernelInterface;
use Middag\Framework\Http\Contract\MethodArgumentResolverInterface;
use Middag\Framework\Http\Contract\PublicRouteAwareInterface;
use Middag\Framework\Http\Contract\RouteMiddlewareInterface;
use Middag\Framework\Http\Inertia\InertiaAdapter;
use Middag\Framework\Http\Middleware\ShareFlashMiddleware;
use Middag\Framework\Http\Resolver\ContainerResolver;
use Middag\Framework\Http\Resolver\FormRequestResolver;
use Middag\Framework\Http\Resolver\FormResolver;
use Middag\Framework\Http\Resolver\InertiaResolver;
use Middag\Framework\Http\Resolver\MethodParameterResolver;
use Middag\Framework\Http\Resolver\RequestResolver;
use Middag\Framework\Http\Resolver\RouteParameterResolver;
use Middag\Framework\Http\Resolver\ValidatedDtoResolver;
use Middag\Framework\Http\Response\CacheHeaderApplier;
use Middag\Framework\Http\Response\CorsHeaderApplier;
use Middag\Framework\Http\Session\FlashBag;
use Middag\Framework\Translation\Contract\TranslatorInterface;
use Middag\Framework\Translation\FallbackTranslator;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\WrappedExceptionsInterface;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use Throwable;

/**
 * HTTP Kernel.
 *
 * PSR-15 RequestHandler. Receives a PSR-7 ServerRequest, internally bridges to
 * Symfony HttpFoundation for route matching, controller resolution and
 * parameter injection, and returns a PSR-7 Response.
 *
 * Symfony HttpFoundation is an implementation detail of the kernel; the public
 * boundary is PSR-15 to integrate with PSR-15 middleware ecosystems and any
 * host's PSR-15 native router (Slim/FastRoute-based).
 *
 * Controllers may keep their HttpFoundation signature (`Request -> Response`) —
 * the resolvers operate on the internal bridged request. Only the kernel
 * boundary speaks PSR-7.
 *
 * @internal
 *
 * @see HttpKernelInterface
 */
class HttpKernel implements HttpKernelInterface
{
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly RouteCollection $routes,
        private readonly RequestContext $context,
        private readonly HttpFoundationFactory $foundationFactory,
        private readonly PsrHttpFactory $psrFactory,
        private readonly bool $debug = false,
        private readonly ?ExceptionRendererInterface $exceptionRenderer = null,
        private readonly ?string $basePath = null,
    ) {}

    /**
     * Handle the HTTP Request cycle.
     *
     * 1. Convert PSR-7 ServerRequest to HttpFoundation Request (internal bridge).
     * 2. Detect CORS preflight requests.
     * 3. Match the URL path to a Route.
     * 4. Resolve the Controller from the Container.
     * 5. Execute the Controller (HttpFoundation Request/Response inside).
     * 6. Bridge the HttpFoundation Response back to PSR-7 and return.
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $symfonyRequest = $this->foundationFactory->createRequest($request);

        $symfonyResponse = $this->handleSymfony($symfonyRequest);

        return $this->psrFactory->createResponse($symfonyResponse);
    }

    /**
     * Core dispatch loop on HttpFoundation Request/Response.
     *
     * Kept separate from {@see self::handle()} so internal sub-requests and tests
     * can drive the kernel with HttpFoundation directly without paying the
     * PSR-7 bridge cost twice. Public callers must use {@see self::handle()}.
     */
    protected function handleSymfony(Request $symfonyRequest): Response
    {
        $response = $this->dispatch($symfonyRequest);

        // Every kernel-emitted response varies on X-Inertia, so a
        // shared cache/CDN never serves a document navigation a JSON page object
        // (or vice versa) for one URL. Covers the auth-gate 409, validation 303,
        // error pages and CORS preflight — not only the Inertia response path,
        // which sets its own Vary in InertiaResponse::toResponse().
        $this->applyInertiaVary($response);

        return $response;
    }

    /**
     * Read the route's #[Auth] attribute (method, then class as fallback) and
     * apply the authentication/authorization flags on the controller before
     * preHandle().
     *
     * Precedence: attribute on the method > attribute on the class > none (no flags).
     *
     * After applying flags from the platform-agnostic `Auth` contract, delegates
     * to {@see self::applyPlatformAuth()} — a no-op hook in the framework,
     * overridden by adapters (Moodle, WordPress) to read platform-specific
     * attributes (e.g. `#[Sesskey]` on Moodle, `#[Nonce]` on WordPress).
     */
    protected function applyRouteAuth(ControllerInterface $controller, string $method): void
    {
        $auth = $this->resolveAuth($controller, $method);

        if ($auth instanceof Auth) {
            if (!$auth->login) {
                // Public route: signal controllers that run their own auth pass
                // (via the opt-in PublicRouteAwareInterface) to skip it. Page
                // controllers do not implement it and are left untouched
                // (handle() already honours the flags).
                if ($controller instanceof PublicRouteAwareInterface) {
                    $controller->disableAuthentication();
                }

                return;
            }

            $controller->setRequireLogin();

            if ($auth->capabilities !== []) {
                $controller->setRequireCapabilities($auth->capabilities, $auth->context, $auth->instanceId);
            }

            // Rich surface: forward the full CapabilityRequirement list to
            // adapters that opt in, so per-requirement context/host/definition
            // survives instead of being flattened to the class-wide context.
            // The string-surface call above stays for adapters that do not implement it.
            if ($auth->requirements !== [] && $controller instanceof CapabilityRequirementAwareInterface) {
                $controller->setRequireCapabilityRequirements($auth->requirements);
            }
        }

        $this->applyPlatformAuth($controller, $method);
    }

    /**
     * Hook for adapters to apply platform-specific auth attributes
     * (e.g. `#[Sesskey]` on Moodle, `#[Nonce]` on WordPress).
     *
     * The default implementation is a no-op. Subclasses override to read their
     * own attributes via reflection and apply flags on the controller.
     */
    protected function applyPlatformAuth(ControllerInterface $controller, string $method): void
    {
        // No-op in the framework. Adapters override.
    }

    /**
     * Match, resolve and execute the controller; map any failure to a response.
     */
    private function dispatch(Request $symfonyRequest): Response
    {
        // Handle CORS Preflight immediately (OPTIONS method)
        if (($corsResponse = $this->buildCorsPreflightResponse($symfonyRequest)) instanceof Response) {
            return $corsResponse;
        }

        $isJson = $this->isJsonRequest($symfonyRequest);

        try {
            // Sync the routing context with the real request BEFORE matching so
            // method-restricted routes (POST/PUT/PATCH/DELETE) match out of the box.
            $this->context->setMethod($symfonyRequest->getMethod());
            $this->context->setHost($symfonyRequest->getHost());
            $this->context->setScheme($symfonyRequest->getScheme());

            // Honour the entry-point base path (white-label multi-plugin): an
            // explicit base path wins, otherwise derive it from the request's
            // script path. A UrlGenerator sharing this context then prefixes
            // named URLs so each plugin's routes resolve under its own entry point.
            $this->context->setBaseUrl($this->basePath ?? $symfonyRequest->getBaseUrl());

            $matcher = new UrlMatcher($this->routes, $this->context);
            $parameters = $matcher->match($symfonyRequest->getPathInfo());

            // Extract special Symfony route parameters
            $controller = $parameters['_controller'];
            unset($parameters['_controller'], $parameters['_route']);

            // Populate request attributes with route params (standard Symfony behaviour).
            // Enables $request->get('param'), $request->attributes->get('param') in controllers.
            $symfonyRequest->attributes->add($parameters);

            // Execute Controller
            $resolved = $this->resolveController($controller, $symfonyRequest);

            // Tracked so response-shaping attributes (#[Cache]) can be read off
            // the resolved action; left null for closure controllers.
            $actionController = null;
            $actionMethod = '';

            // Handle closures/callables directly
            if (is_callable($resolved) && !is_array($resolved)) {
                $response = $resolved(...array_values($parameters));
            } else {
                // Handle [instance, method] format
                [$controllerInstance, $method] = $resolved;
                $actionController = $controllerInstance;
                $actionMethod = $method;

                if ($controllerInstance instanceof ControllerInterface) {
                    // 1. Apply auth flags declared via #[Auth] on the route or class.
                    $this->applyRouteAuth($controllerInstance, $method);

                    // 2. OSS #[Auth] gate: denies unauthenticated access when an
                    //    AuthenticatorInterface is in the container (inert without it → adapters intact).
                    if (($denied = $this->guardAuthenticatedAccess($controllerInstance, $method, $symfonyRequest, $isJson)) instanceof Response) {
                        return $denied;
                    }

                    // 3. Lifecycle: preHandle() always before the action.
                    //    api controllers: dual auth + handle() run here.
                    //    page controllers: empty hook; handle() stays lazy via render().
                    $controllerInstance->preHandle();
                }

                // Build the resolver per-request so every resolver receives the same
                // $request instance — the one that already has route params in attributes.
                // A container-registered resolver would hold a different Request object
                // (created during boot) and would miss $request->attributes route params.
                $parameterResolver = $this->buildParameterResolver($symfonyRequest);
                $args = $parameterResolver->resolveArguments($controllerInstance, $method, $parameters);

                // Wrap the action in any declared #[Middleware] (class-level
                // outermost, then method-level). Zero overhead when none declared:
                // the action is invoked directly, exactly as before.
                $middlewares = $this->resolveRouteMiddleware($controllerInstance, $method);

                if ($middlewares === []) {
                    $response = $controllerInstance->{$method}(...$args);
                } else {
                    $response = $this->runRouteMiddleware(
                        $middlewares,
                        $symfonyRequest,
                        function () use ($controllerInstance, $method, $args): Response {
                            $result = $controllerInstance->{$method}(...$args);

                            return $result instanceof Response ? $result : new JsonResponse($result);
                        },
                    );
                }
            }

            // Normalize Response: if Controller returns data/array, wrap it in JsonResponse
            if (!$response instanceof Response) {
                $response = new JsonResponse($response);
            }

            // Apply response-shaping attributes declared on the action (#[Cache]
            // HTTP caching headers). No-op for closures and unannotated actions.
            if (is_object($actionController)) {
                CacheHeaderApplier::apply($actionController, $actionMethod, $response);
                CorsHeaderApplier::apply($actionController, $actionMethod, $symfonyRequest, $response);
            }

            // A 302 redirect from an unsafe method breaks the stock Inertia
            // client — upgrade it to 303 so the client re-GETs the destination.
            return $this->enforceInertiaRedirect($symfonyRequest, $response);
        } catch (Throwable $throwable) {
            // Peel the bus wrapper first, then map by type (typed status).
            return $this->mapThrowable($this->unwrapHandlerFailure($throwable), $symfonyRequest, $isJson);
        }
    }

    /**
     * Resolve the effective #[Auth] attribute for an action.
     *
     * Method-level takes precedence over class-level; null when neither declares it.
     */
    private function resolveAuth(ControllerInterface $controller, string $method): ?Auth
    {
        $attrs = (new ReflectionMethod($controller, $method))->getAttributes(Auth::class);

        if ($attrs === []) {
            $attrs = (new ReflectionClass($controller))->getAttributes(Auth::class);
        }

        if ($attrs === []) {
            return null;
        }

        return $attrs[0]->newInstance();
    }

    /**
     * The OSS `#[Auth]` gate.
     *
     * When the matched action requires login AND an {@see AuthenticatorInterface}
     * is bound in the container, deny unauthenticated access:
     *  - Inertia request → 409 + `X-Inertia-Location` (client does a full visit to login)
     *  - JSON/XHR API     → throws {@see MiddagAuthenticationException} (401)
     *  - browser visit    → 303 redirect to the authenticator's login path
     *
     * When no authenticator is bound the gate is inert and returns null, so
     * host-delegated auth (Moodle/WordPress, which wire `setRequireLogin()` to
     * platform checks) is entirely unaffected. Returns null whenever access is
     * allowed (public route, already authenticated, or no OSS authenticator).
     */
    private function guardAuthenticatedAccess(ControllerInterface $controller, string $method, Request $request, bool $isJson): ?Response
    {
        $auth = $this->resolveAuth($controller, $method);

        if (!$auth instanceof Auth || !$auth->login) {
            return null;
        }

        if (!$this->container->has(AuthenticatorInterface::class)) {
            return null;
        }

        $authenticator = $this->container->get(AuthenticatorInterface::class);

        if (!$authenticator instanceof AuthenticatorInterface || $authenticator->check()) {
            return null;
        }

        if ($request->headers->get('X-Inertia')) {
            return new Response('', Response::HTTP_CONFLICT, ['X-Inertia-Location' => $authenticator->loginPath()]);
        }

        if ($isJson) {
            throw new MiddagAuthenticationException('Authentication required.');
        }

        return new RedirectResponse($authenticator->loginPath(), Response::HTTP_SEE_OTHER);
    }

    /**
     * Unwrap a Symfony Messenger {@see HandlerFailedException} down to the
     * exception the handler actually threw.
     *
     * The single dispatch bus wraps any handler exception in
     * HandlerFailedException, masking typed framework exceptions
     * (MiddagNotFoundException → 404, MiddagValidationException → 422, …) behind a
     * generic RuntimeException → 500. Peeling it here lets a command handler throw
     * the same typed exception a controller would and get the same HTTP status —
     * no duplicate guard in the controller. Recurses to survive nested buses.
     */
    private function unwrapHandlerFailure(Throwable $throwable): Throwable
    {
        while ($throwable instanceof WrappedExceptionsInterface) {
            $wrapped = $throwable->getWrappedExceptions();
            $inner = $wrapped === [] ? $throwable->getPrevious() : reset($wrapped);

            if (!$inner instanceof Throwable || $inner === $throwable) {
                break;
            }

            $throwable = $inner;
        }

        return $throwable;
    }

    /**
     * Resolve the exception renderer.
     *
     * Prefers an explicitly injected renderer, then a container-bound
     * {@see ExceptionRendererInterface} (so adapters can theme error pages
     * without touching kernel construction), and finally the OSS default that
     * preserves the framework's JSON envelope and debug page.
     */
    private function exceptionRenderer(): ExceptionRendererInterface
    {
        if ($this->exceptionRenderer instanceof ExceptionRendererInterface) {
            return $this->exceptionRenderer;
        }

        if ($this->container->has(ExceptionRendererInterface::class)) {
            $bound = $this->container->get(ExceptionRendererInterface::class);

            if ($bound instanceof ExceptionRendererInterface) {
                return $bound;
            }
        }

        return new DefaultExceptionRenderer($this->debug);
    }

    /**
     * Map an already-unwrapped throwable to an HTTP response.
     *
     * MiddagValidationException is matched first (kept in the kernel for its
     * field-level error map and flash redirect); other typed MiddagExceptions
     * map to their declared status, routing exceptions keep their 404/405, and
     * anything else falls through to a 500. Body construction is delegated to
     * the resolved {@see ExceptionRendererInterface}.
     */
    private function mapThrowable(Throwable $throwable, Request $request, bool $isJson): Response
    {
        if ($throwable instanceof MiddagValidationException) {
            return $this->createValidationResponse($throwable, $request, $isJson);
        }

        $renderer = $this->exceptionRenderer();

        if ($throwable instanceof MiddagException) {
            return $renderer->render($throwable, $request, $throwable->getStatusCode(), 'framework_error', $throwable->getMessage(), $isJson);
        }

        if ($throwable instanceof ResourceNotFoundException) {
            return $renderer->render($throwable, $request, Response::HTTP_NOT_FOUND, 'not_found', 'Route not found', $isJson);
        }

        if ($throwable instanceof MethodNotAllowedException) {
            return $renderer->render($throwable, $request, Response::HTTP_METHOD_NOT_ALLOWED, 'method_not_allowed', 'Method not allowed', $isJson);
        }

        return $renderer->render($throwable, $request, Response::HTTP_INTERNAL_SERVER_ERROR, 'server_error', 'Server Error', $isJson);
    }

    /**
     * Build the response for a failed validation.
     *
     * JSON/XHR/API clients get a 422 with the field-level error map
     * (`{message, errors:{field:[…]}}` — consumed by API clients directly).
     *
     * Browser/Inertia clients complete the post-redirect-get loop when a
     * {@see FlashBag} is bound: the errors are flashed to the session and the
     * request is redirected back (303) where {@see ShareFlashMiddleware}
     * re-shares them as the `errors` prop for `useForm().errors`. With no flash
     * store bound the kernel falls back to the 422 — the platform-agnostic
     * contract, valid for stateless API hosts.
     */
    private function createValidationResponse(MiddagValidationException $exception, Request $request, bool $isJson): Response
    {
        $serialized = (new ValidationErrorSerializer($this->resolveTranslator()))->serialize($exception->errors());

        // Inertia error bags: when the originating request scoped its form to a
        // named bag (X-Inertia-Error-Bag), nest the field map under it so the
        // client's useForm(bag) reads its own errors. Absent the header the
        // shape stays flat (the common single-form / API case).
        $errorBag = $request->headers->get('X-Inertia-Error-Bag');
        $errors = is_string($errorBag) && $errorBag !== ''
            ? [$errorBag => $serialized]
            : $serialized;

        $flash = $this->container->has(FlashBag::class) ? $this->container->get(FlashBag::class) : null;

        if (!$isJson && $flash instanceof FlashBag) {
            $flash->setErrors($errors);
            $flash->add('error', $exception->getMessage());

            return new RedirectResponse($request->headers->get('referer') ?: '/', Response::HTTP_SEE_OTHER);
        }

        return new JsonResponse([
            'success' => false,
            'error' => 'validation_failed',
            'message' => $exception->getMessage(),
            'errors' => $errors,
        ], $exception->getStatusCode());
    }

    private function resolveTranslator(): TranslatorInterface
    {
        if ($this->container->has(TranslatorInterface::class)) {
            $candidate = $this->container->get(TranslatorInterface::class);

            if ($candidate instanceof TranslatorInterface) {
                return $candidate;
            }
        }

        return new FallbackTranslator();
    }

    /**
     * Upgrade a 301/302 redirect to 303 for unsafe-method Inertia responses.
     *
     * The Inertia client requires `303 See Other` after POST/PUT/PATCH/DELETE so
     * it re-issues the follow-up as GET; a stock 302 — or a 301, which is also
     * cacheable — would otherwise replay the unsafe method. {@see InertiaAdapter::redirect()}
     * already emits 303, but a controller using a plain 301/302 redirect helper
     * would break the client, so the kernel enforces it (matching Inertia v3,
     * which promotes both 301 and 302). No-op for non-Inertia requests, safe
     * methods, and non-redirect (≠ 301/302) responses.
     */
    private function enforceInertiaRedirect(Request $request, Response $response): Response
    {
        if ($request->headers->get('X-Inertia') === null) {
            return $response;
        }

        if (!in_array($request->getMethod(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $response;
        }

        if (in_array($response->getStatusCode(), [Response::HTTP_MOVED_PERMANENTLY, Response::HTTP_FOUND], true)) {
            $response->setStatusCode(Response::HTTP_SEE_OTHER);
        }

        return $response;
    }

    /**
     * Ensure a response varies on the X-Inertia header.
     *
     * Appends `X-Inertia` to any existing Vary instead of clobbering it, so a
     * response already varying (e.g. on Accept-Encoding) keeps those entries and
     * is never double-added.
     */
    private function applyInertiaVary(Response $response): void
    {
        $vary = $response->getVary();

        if (!in_array('X-Inertia', $vary, true)) {
            $vary[] = 'X-Inertia';
            $response->setVary($vary);
        }
    }

    /**
     * Resolve the ordered route-middleware chain declared via #[Middleware].
     *
     * Class-level declarations come first (outermost), then method-level, each in
     * declaration order; the attribute is repeatable so several accumulate. Each
     * entry is fetched from the container — falling back to a zero-argument `new`
     * when unregistered — and must implement {@see RouteMiddlewareInterface}.
     *
     * @return list<RouteMiddlewareInterface>
     */
    private function resolveRouteMiddleware(object $controller, string $method): array
    {
        $attributes = [
            ...(new ReflectionClass($controller))->getAttributes(Middleware::class),
            ...(new ReflectionMethod($controller, $method))->getAttributes(Middleware::class),
        ];

        if ($attributes === []) {
            return [];
        }

        $resolved = [];

        foreach ($attributes as $attribute) {
            foreach ($attribute->newInstance()->middleware as $id) {
                $instance = $this->container->has($id)
                    ? $this->container->get($id)
                    : (class_exists($id) ? new $id() : null);

                if (!$instance instanceof RouteMiddlewareInterface) {
                    throw new RuntimeException(sprintf(
                        'Route middleware "%s" must implement %s.',
                        $id,
                        RouteMiddlewareInterface::class,
                    ));
                }

                $resolved[] = $instance;
            }
        }

        return $resolved;
    }

    /**
     * Compose the route-middleware chain around the action, outermost first.
     *
     * @param list<RouteMiddlewareInterface> $middlewares
     * @param callable(): Response           $action
     */
    private function runRouteMiddleware(array $middlewares, Request $request, callable $action): Response
    {
        $next = static fn (Request $request): Response => $action();

        foreach (array_reverse($middlewares) as $middleware) {
            $current = $next;
            $next = static fn (Request $request): Response => $middleware->process($request, $current);
        }

        return $next($request);
    }

    /**
     * Builds a per-request method parameter resolver.
     *
     * Composes a {@see MethodParameterResolver} over the ordered
     * {@see MethodArgumentResolverInterface}
     * chain (RequestResolver, FormRequestResolver, FormResolver, ContainerResolver,
     * InertiaResolver, RouteParameterResolver). It must be constructed per-request —
     * not fetched from the container — so that the request-bound resolvers
     * (RequestResolver, FormRequestResolver, FormResolver) receive the SAME $request
     * instance the kernel uses, including route params already populated in
     * $request->attributes.
     */
    private function buildParameterResolver(Request $request): MethodParameterResolver
    {
        return new MethodParameterResolver([
            new RequestResolver($request),
            new FormRequestResolver($this->container, $request),
            new FormResolver($this->container, $request),
            new ValidatedDtoResolver($request),
            new ContainerResolver($this->container),
            new InertiaResolver($this->container),
            new RouteParameterResolver(),
        ]);
    }

    /**
     * Build a CORS preflight response when the incoming request is OPTIONS.
     *
     * Returns `null` for non-preflight requests so the caller continues normal
     * dispatch; an OPTIONS request yields a 204 preflight response.
     */
    private function buildCorsPreflightResponse(Request $request): ?Response
    {
        if ($request->getMethod() !== 'OPTIONS') {
            return null;
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT, [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With, Accept, Origin',
            'Access-Control-Max-Age' => '3600',
        ]);
    }

    /**
     * Resolves the controller into a callable.
     *
     * Supports service IDs (strings) or direct callables.
     * Also injects the Container and Request when the controller implements
     * {@see ControllerInterface} (the AbstractController family).
     *
     * @return array{0: object, 1: string}|callable
     *
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    private function resolveController(mixed $controller, Request $request): array|callable
    {
        // Normalize "Fqcn::method" string to ["Fqcn", "method"] tuple.
        if (is_string($controller) && str_contains($controller, '::')) {
            $parts = explode('::', $controller, 2);
            if (count($parts) === 2 && $parts[0] !== '' && $parts[1] !== '') {
                $controller = [$parts[0], $parts[1]];
            }
        }

        // Case: Controller defined as ["service.id", "method"]
        if (is_array($controller) && is_string($controller[0]) && $this->container->has($controller[0])) {
            $instance = $this->container->get($controller[0]);

            // Setter Injection for Container-Aware controllers
            if ($instance instanceof ControllerInterface) {
                $instance->setContainer($this->container);
                $instance->setRequest($request);
            }
            $controller[0] = $instance;
        }

        if (!is_callable($controller)) {
            throw new RuntimeException('Invalid controller or service not found: ' . var_export($controller, true));
        }

        return $controller;
    }

    /**
     * Determines if the client expects a JSON response.
     */
    private function isJsonRequest(Request $request): bool
    {
        if ($request->isXmlHttpRequest()) {
            return true;
        }

        return str_contains((string) $request->headers->get('Accept', ''), 'application/json');
    }
}
