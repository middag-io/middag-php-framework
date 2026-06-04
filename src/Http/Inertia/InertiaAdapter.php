<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Http\Inertia;

use Closure;
use Middag\Ui\Envelope\Contract\ContractEnvelopeInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Static facade that proxies Inertia rendering helpers.
 *
 * Platform-agnostic: consumes only protocol-side helpers
 * ({@see InertiaFactory}, {@see InertiaManager}, {@see InertiaVersionManager})
 * plus a host-supplied URL generator closure for {@see self::location()} and
 * {@see self::redirect()}. The Moodle/WP adapter wires the URL generator
 * during boot.
 *
 * Static by design — a deliberate exception to the framework's instance-based
 * service rule. The Inertia protocol is invariant (X-Inertia headers, JSON
 * props, asset version), so there is no alternative implementation to bind: all
 * host variability enters through the closures/setters here and on the helpers
 * above. The seam is configuration, not type substitution.
 *
 * The configuration methods are the documented host/adapter boot seam — a
 * standalone host wires them exactly as the Moodle/WP adapter does — so this is
 * part of the supported integration surface.
 *
 * @api
 */
class InertiaAdapter
{
    /** @var null|Closure(string, array<string, mixed>): string */
    private static ?Closure $urlGenerator = null;

    /**
     * Register the host-supplied URL generator.
     *
     * Signature: `fn(string $route, array $params): string` returning an
     * absolute or root-relative URL string. Adapter (Moodle/WP) calls this
     * during boot.
     *
     * @param Closure(string, array<string, mixed>): string $generator
     */
    public static function setUrlGenerator(Closure $generator): void
    {
        self::$urlGenerator = $generator;
    }

    /**
     * Wire a Symfony route URL generator as the name → path resolver.
     *
     * The batteries-included default the framework never connected: instead of
     * each standalone host hand-rolling a fake closure, pass the Symfony
     * {@see UrlGeneratorInterface} built from the app's RouteCollection +
     * RequestContext and named routes ({@see self::redirect()},
     * {@see self::location()}, and `AbstractController::redirectToRoute()`)
     * resolve and fill `{param}` placeholders for free.
     */
    public static function useUrlGenerator(UrlGeneratorInterface $generator): void
    {
        self::$urlGenerator = static fn (string $route, array $params = []): string => $generator->generate($route, $params);
    }

    /**
     * Render an Inertia page returning the appropriate HTTP response.
     *
     * @param array<string, mixed> $props
     */
    public static function render(string $component, array $props = []): Response
    {
        return InertiaFactory::render($component, $props)->toResponse();
    }

    /**
     * Render a middag-io/ui contract envelope as an Inertia response.
     *
     * The contract-driven counterpart to {@see self::render()}: hand it a
     * PageContract / Fragment instead of a component+props pair. See
     * {@see InertiaFactory::page()} for the bridge semantics.
     *
     * @param array<string, mixed> $extraProps merged alongside the contract
     */
    public static function page(ContractEnvelopeInterface $contract, array $extraProps = []): Response
    {
        return InertiaFactory::page($contract, $extraProps)->toResponse();
    }

    /**
     * Share a global prop accessible to all Inertia responses.
     */
    public static function share(string $key, mixed $value): void
    {
        InertiaManager::share($key, $value);
    }

    /**
     * Wrap a prop so its closure runs only on a partial reload.
     *
     * The Inertia v3 `optional()` helper: the callable never executes on a full
     * page load nor on a normal Inertia visit — the prop is simply absent. It
     * resolves once, only when its key is listed in `X-Inertia-Partial-Data` for
     * this component. Use it for expensive props the client fetches on demand.
     */
    public static function optional(Closure $callback): OptionalProp
    {
        return new OptionalProp($callback);
    }

    /**
     * Wrap a prop the client fetches automatically after mount (`defer`).
     *
     * The callable never runs on the initial response: the prop is absent and its
     * key is announced under the page object's `deferredProps[$group]`. The
     * Inertia client then fires one partial reload per group to resolve it.
     * Props sharing a `$group` are fetched together. With `$rescue = true`, a
     * resolution failure drops the prop (reported in `rescuedProps`) instead of
     * failing the request.
     */
    public static function defer(Closure $callback, string $group = 'default', bool $rescue = false): DeferProp
    {
        return new DeferProp($callback, $group, $rescue);
    }

    /**
     * Wrap a prop the client SHALLOW-merges into its current value (Inertia v3 `merge`).
     *
     * The value (or closure) resolves and is present on every render; the client
     * merges it on a follow-up partial reload instead of replacing — the basis for
     * "load more" / infinite-scroll feeds. Pass `$matchOn` keys so the client
     * dedupes array items by identity when appending a page rather than blindly
     * concatenating. A request may opt the key out for one response via the
     * `X-Inertia-Reset` header (the client then replaces).
     *
     * @param list<string> $matchOn match keys (relative to this prop) for pagination dedup
     */
    public static function merge(mixed $value, array $matchOn = []): MergeProp
    {
        return new MergeProp($value, false, $matchOn);
    }

    /**
     * Wrap a prop the client DEEP-merges into its current value (`deepMerge`).
     *
     * Like {@see self::merge()} but the client merges recursively, so nested
     * structures combine instead of the top-level value replacing wholesale.
     *
     * @param list<string> $matchOn match keys (relative to this prop) for pagination dedup
     */
    public static function deepMerge(mixed $value, array $matchOn = []): MergeProp
    {
        return new MergeProp($value, true, $matchOn);
    }

    /**
     * Override the current Inertia asset version.
     */
    public static function version(string $version): void
    {
        InertiaVersionManager::setVersion($version);
    }

    /**
     * Hard redirect to a location outside the SPA.
     *
     * Returns `409 + X-Inertia-Location` for Inertia clients (triggers full browser load)
     * or a standard 302 for non-Inertia clients. Use only when the destination is not
     * an Inertia page; for internal redirects after POST/PUT/DELETE use `redirect()`.
     *
     * @param array<string, mixed> $params
     */
    public static function location(string $route, array $params = []): RedirectResponse|Response
    {
        $url = self::resolveUrl($route, $params);
        $request = Request::createFromGlobals();

        if ($request->headers->get('X-Inertia')) {
            return new Response('', 409, [
                'X-Inertia-Location' => $url,
                'Vary' => 'X-Inertia',
            ]);
        }

        return new RedirectResponse($url);
    }

    /**
     * SPA-friendly redirect to another Inertia page (303 See Other).
     *
     * Per the Inertia protocol, POST/PUT/DELETE responses that redirect to another
     * Inertia page must use `303 See Other`. The client follows with GET and updates
     * the page in place — no full browser reload.
     *
     * @param array<string, mixed> $params
     */
    public static function redirect(string $route, array $params = []): RedirectResponse
    {
        return new RedirectResponse(
            self::resolveUrl($route, $params),
            Response::HTTP_SEE_OTHER,
        );
    }

    /**
     * @param array<string, mixed> $params
     */
    private static function resolveUrl(string $route, array $params): string
    {
        if (!self::$urlGenerator instanceof Closure) {
            throw new RuntimeException(
                'InertiaAdapter URL generator not configured. Adapter must call '
                . self::class . '::setUrlGenerator() (or ::useUrlGenerator()) during boot.'
            );
        }

        return (self::$urlGenerator)($route, $params);
    }
}
