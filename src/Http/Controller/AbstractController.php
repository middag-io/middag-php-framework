<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Http\Controller;

use Middag\Framework\Http\Contract\ControllerInterface;
use Middag\Framework\Http\Middleware\ShareFlashMiddleware;
use Middag\Framework\Http\Session\FlashBag;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Abstract base controller — platform-agnostic parent for all HTTP controllers.
 *
 * Provides PSR-11 container injection, Symfony request handling, and basic
 * response helpers. Platform adapters (Moodle, WP) extend this class to add
 * platform-specific page setup, auth, and rendering logic.
 *
 * @api
 *
 * @see ControllerInterface
 */
abstract class AbstractController implements ControllerInterface
{
    protected ContainerInterface $container;

    protected ?Request $request = null;

    /** @var array<string, mixed> */
    protected array $params = [];

    /** @var array<string, mixed> */
    protected array $payload = [];

    /**
     * Whether a subclass has already produced the response for this request.
     *
     * A host-adapter extension point: a subclass flips this once it has emitted
     * output so re-entrant dispatch can short-circuit. The OSS base never reads
     * it; it exists purely as a stable seam for host controller bases.
     */
    protected bool $handled = false;

    /**
     * Inject the service container.
     * Called automatically by the HttpKernel during resolution.
     */
    public function setContainer(ContainerInterface $container): void
    {
        $this->container = $container;
        $this->initializeRequest();
    }

    /**
     * Inject the current HTTP request.
     */
    public function setRequest(Request $request): void
    {
        $this->request = $request;
        $this->initializeRequest();
    }

    /**
     * Host page-controller entry point.
     *
     * Empty by default and NOT called by the kernel — the kernel invokes the
     * route-matched action method. An adapter's page controller overrides this
     * and invokes it from its own flow to apply auth, context resolution, and
     * page setup.
     */
    public function handle(): void {}

    /**
     * Pre-handle hook: run before handle() in the kernel pipeline.
     *
     * Empty by default. Subclasses override to configure auth flags
     * and call parent::pre_handle() or handle() explicitly.
     */
    public function preHandle(): void {}

    /**
     * Mark the controller as requiring an authenticated session.
     *
     * Platform adapters override to apply platform-specific login checks.
     */
    public function setRequireLogin(): void {}

    /**
     * Declare capabilities the current user must hold.
     *
     * @param array<int, string> $capabilities
     * @param string             $context      Platform-specific context type the adapter interprets
     */
    public function setRequireCapabilities(array $capabilities, string $context = 'system', int $instanceId = 0): void {}

    /**
     * Retrieve a service from the container safely.
     *
     * @param class-string|string $serviceName
     */
    protected function getService(string $serviceName): mixed
    {
        try {
            if ($this->container->has($serviceName)) {
                return $this->container->get($serviceName);
            }
        } catch (ContainerExceptionInterface) {
            // Intentionally suppressed — let caller handle null.
        }

        return null;
    }

    /**
     * Determine if the request expects a JSON response.
     */
    protected function isJson(): bool
    {
        if (!$this->request instanceof Request) {
            return false;
        }

        if ($this->request->isXmlHttpRequest()) {
            return true;
        }

        return str_contains((string) $this->request->headers->get('Accept', ''), 'application/json');
    }

    /**
     * Return a response based on data type and request accept header.
     *
     * @param mixed $data
     * @param int   $status
     *
     * @return JsonResponse|Response
     */
    protected function response(mixed $data, int $status = Response::HTTP_OK): JsonResponse|Response
    {
        if (is_array($data) || $this->isJson()) {
            return $this->jsonResponse($data, $status);
        }

        return new Response((string) $data, $status);
    }

    /**
     * Return a JSON response.
     *
     * @param mixed $data
     * @param int   $status
     */
    protected function jsonResponse(mixed $data, int $status = Response::HTTP_OK): JsonResponse
    {
        return new JsonResponse($data, $status);
    }

    /**
     * Redirect to a URL.
     */
    protected function redirect(string $url, int $status = Response::HTTP_FOUND): RedirectResponse
    {
        return new RedirectResponse($url, $status);
    }

    /**
     * Redirect to a named route, resolving `{param}` placeholders.
     *
     * Defaults to 303 See Other — the status Inertia expects after a write so
     * the client re-GETs the destination. Requires a Symfony
     * {@see UrlGeneratorInterface} bound in the container (the standalone boot
     * wires one over the app's RouteCollection); throws if none is available.
     *
     * @param array<string, mixed> $params
     */
    protected function redirectToRoute(string $name, array $params = [], int $status = Response::HTTP_SEE_OTHER): RedirectResponse
    {
        $generator = $this->getService(UrlGeneratorInterface::class);

        if (!$generator instanceof UrlGeneratorInterface) {
            throw new RuntimeException('No UrlGeneratorInterface bound; cannot resolve route "' . $name . '".');
        }

        return new RedirectResponse($generator->generate($name, $params), $status);
    }

    /**
     * Redirect back to the referring page, defaulting to "/".
     *
     * The "G" of post-redirect-get. Defaults to 303 so the Inertia client
     * re-GETs the previous page, where {@see ShareFlashMiddleware}
     * surfaces any flashed messages/errors.
     */
    protected function redirectBack(int $status = Response::HTTP_SEE_OTHER): RedirectResponse
    {
        $target = '/';

        if ($this->request instanceof Request) {
            $target = $this->request->headers->get('referer') ?: '/';
        }

        return new RedirectResponse($target, $status);
    }

    /**
     * Queue a one-shot flash message for the next request.
     *
     * No-op when no {@see FlashBag} is bound (e.g. a stateless API host), so it
     * is always safe to call.
     */
    protected function flash(string $key, mixed $value): void
    {
        $bag = $this->getService(FlashBag::class);

        if ($bag instanceof FlashBag) {
            $bag->add($key, $value);
        }
    }

    private function initializeRequest(): void
    {
        if (!$this->request instanceof Request) {
            $this->request = Request::createFromGlobals();
        }

        $this->params = $this->request->query->all();
        $this->payload = $this->request->getPayload()->all();
    }
}
