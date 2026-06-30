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
use JsonSerializable;
use Middag\Framework\Http\Contract\DeferrableInterface;
use Middag\Framework\Http\Contract\IgnoreFirstLoadInterface;
use Middag\Framework\Http\Contract\MergeableInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Throwable;

/**
 * Value object representing an Inertia response payload.
 *
 * Platform-agnostic Inertia protocol implementation. Handles the
 * SPA JSON branch directly. For the first-visit HTML bootstrap, delegates to
 * a host-supplied callable registered via {@see InertiaFactory::setHtmlBootstrap()}
 * (Moodle/WP plumbs in its asset pipeline + page hooks). When no bootstrap
 * is registered, a minimal default `<div id="app">` shell is returned so the
 * SPA can still mount in non-Moodle/non-WP hosts.
 *
 * @internal
 */
final class InertiaResponse
{
    /**
     * Deferred-prop keys grouped for follow-up partial reloads.
     *
     * Populated by {@see self::resolveProps()} on the initial render; emitted as
     * the page object's `deferredProps`. Shape: `array<string, list<string>>`.
     *
     * @var array<string, list<string>>
     */
    private array $deferredProps = [];

    /**
     * Deferred-prop keys dropped by `rescue: true` after a resolution failure.
     *
     * @var list<string>
     */
    private array $rescuedProps = [];

    /**
     * Prop keys the client shallow-merges client-side (Inertia v3 `merge`).
     *
     * Populated by {@see self::resolveProps()}; emitted as the page object's
     * `mergeProps`. A key opted out via `X-Inertia-Reset` is omitted.
     *
     * @var list<string>
     */
    private array $mergeProps = [];

    /**
     * Prop keys the client deep-merges client-side (`deepMerge`).
     *
     * @var list<string>
     */
    private array $deepMergeProps = [];

    /**
     * Pagination match paths (`"{propKey}.{matchKey}"`) emitted as `matchPropsOn`.
     *
     * @var list<string>
     */
    private array $matchPropsOn = [];

    /**
     * @param array<string, mixed> $props the page's local props (closures resolved at render)
     */
    public function __construct(
        private readonly string $component,
        private readonly array $props = [],
        private ?Request $request = null
    ) {
        $this->request = $request ?: Request::createFromGlobals();
    }

    /**
     * Build the Inertia response (JSON for SPA, HTML bootstrap otherwise).
     */
    public function toResponse(): Response
    {
        // Purge stray host output (WordPress notices, Moodle debug echoes,
        // BOM, whitespace) buffered before the body, so it cannot corrupt the
        // X-Inertia JSON payload. Guarded: only act when a buffer is active.
        if (ob_get_level() > 0) {
            ob_clean();
        }

        $page = [
            'component' => $this->component,
            'props' => $this->resolveProps(),
            'url' => $this->request->getRequestUri(),
            'version' => InertiaVersionManager::getVersion(),
        ];

        // Append deferred/rescued metadata only when present, so the base
        // $page shape { component, props, url, version } stays intact (§6 #4).
        if ($this->deferredProps !== []) {
            $page['deferredProps'] = $this->deferredProps;
        }

        if ($this->rescuedProps !== []) {
            $page['rescuedProps'] = $this->rescuedProps;
        }

        // Inertia v3 merge props — announce which props the client merges (rather
        // than replaces) on a follow-up partial reload, and the match paths used
        // to dedupe paginated appends. Emitted only when present so the base
        // $page shape stays intact (§6 #4), exactly like deferred/rescued above.
        if ($this->mergeProps !== []) {
            $page['mergeProps'] = $this->mergeProps;
        }

        if ($this->deepMergeProps !== []) {
            $page['deepMergeProps'] = $this->deepMergeProps;
        }

        if ($this->matchPropsOn !== []) {
            $page['matchPropsOn'] = $this->matchPropsOn;
        }

        // Every Inertia response carries the asset version in a
        // header (not only the page-object body) and `Vary: X-Inertia`, so a
        // shared cache/CDN never serves a JSON page object to a document
        // navigation (or vice versa) for the same URL.
        $inertiaHeaders = [
            'Vary' => 'X-Inertia',
            'X-Inertia-Version' => $page['version'],
        ];

        if ($this->isInertiaRequest()) {
            // Asset-version skew: on a GET visit whose client version no longer
            // matches the server's, the protocol mandates a 409 + X-Inertia-Location
            // so the client hard-reloads the page instead of consuming stale props.
            if (
                $this->request->isMethod('GET')
                && $this->request->headers->has('X-Inertia-Version')
                && $this->request->headers->get('X-Inertia-Version') !== $page['version']
            ) {
                return new Response('', 409, ['X-Inertia-Location' => $this->request->getUri()] + $inertiaHeaders);
            }

            if ($this->isPartial()) {
                // Wire-level safety net: resolveProps() already skipped the
                // compute of filtered keys; this guarantees nothing
                // outside the allow-set — and nothing in `except` — leaks into
                // the serialized payload.
                $only = $this->partialOnly();
                $except = $this->partialExcept();
                $hasOnly = $only !== [];
                $page['props'] = array_filter(
                    $page['props'],
                    static fn ($k): bool => !in_array((string) $k, $except, true)
                        && (!$hasOnly || in_array((string) $k, $only, true)),
                    ARRAY_FILTER_USE_KEY
                );
            }

            return new JsonResponse($page, 200, ['X-Inertia' => 'true'] + $inertiaHeaders);
        }

        // JSON_HEX_* flags prevent XSS: escapes ', ", <, >, & inside JSON values
        // so they are safe for both HTML attributes and <script> blocks.
        $json = json_encode(
            $page,
            JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
        );
        $attr = htmlspecialchars((string) $json, ENT_QUOTES, 'UTF-8');

        $bootstrap = InertiaFactory::getHtmlBootstrap();

        if ($bootstrap instanceof Closure) {
            $response = $bootstrap($page, (string) $json, $attr);
            $response->headers->add($inertiaHeaders);

            return $response;
        }

        // Default minimal HTML bootstrap — no host integration available.
        // Inertia v3 server-side shape: the client reads the initial page from a
        // <script type="application/json" data-page="{id}"> element (data-page ===
        // createInertiaApp id), not from a div[data-page] attribute. The mount id
        // comes from InertiaFactory (default "app"; a product overrides it via
        // setAppId), keeping this generic shell product-agnostic. $json is
        // JSON_HEX_TAG/APOS/QUOT/AMP-encoded, so no </script> breakout is possible.
        $appId = InertiaFactory::getAppId();
        $html = <<<HTML
            <div id="{$appId}"></div>
            <script type="application/json" data-page="{$appId}">{$json}</script>
        HTML;

        return new Response($html, 200, $inertiaHeaders);
    }

    /**
     * Merge local props with shared props, resolving closures and normalizing entities.
     *
     * Lazy by partial: on a partial reload for this component only the
     * requested keys are resolved — every other key's closure is never invoked,
     * so partials save compute and not just transfer. `except` blacklists
     * a key even when `only` named it (except wins); an except-only partial keeps
     * every plain key but the blacklisted ones. Off the partial path, plain
     * closures stay eager but {@see IgnoreFirstLoadInterface} props (e.g.
     * `optional()`) are skipped: they resolve only when explicitly named in a
     * partial's `only`. Shared closures resolve here exactly once (no
     * double-pass) because {@see InertiaManager::getShared()} returns raw values.
     * Deferred props are skipped on the initial render and announce their
     * group in {@see self::$deferredProps}; `rescue` failures land in
     * {@see self::$rescuedProps}. Both are emitted by {@see self::toResponse()}.
     *
     * Values that are not scalar/array/JsonSerializable are normalized via
     * ObjectNormalizer so that rich domain entities can be passed as Inertia props
     * without boilerplate jsonSerialize() implementations.
     *
     * @return array<string, mixed>
     */
    private function resolveProps(): array
    {
        // Reset per-call metadata: a re-rendered response must not inherit
        // the previous pass's deferred/rescued/merge keys.
        $this->deferredProps = [];
        $this->rescuedProps = [];
        $this->mergeProps = [];
        $this->deepMergeProps = [];
        $this->matchPropsOn = [];

        // RAW shared map — this is the single resolution pass. Local
        // props override shared on key collision (shared-then-local precedence).
        $merged = array_merge(InertiaManager::getShared(), $this->props);

        $isPartial = $this->isPartial();
        $only = $isPartial ? $this->partialOnly() : [];
        $except = $isPartial ? $this->partialExcept() : [];
        $hasOnly = $only !== [];

        $props = [];

        foreach ($merged as $key => $value) {
            if ($isPartial) {
                // Cast to string: PHP coerces numeric-string array keys to int,
                // but the parsed only/except sets are string tokens — keep this
                // in lock-step with the wire net's (string) cast so both passes
                // filter identically.
                $name = (string) $key;

                // `except` wins over `only`: a blacklisted key is dropped
                // even if `only` listed it — closure never invoked.
                if (in_array($name, $except, true)) {
                    continue;
                }

                if ($value instanceof IgnoreFirstLoadInterface) {
                    // optional()/defer() are opt-in: resolved only when the key
                    // is explicitly named in `only` (never on an except-only reload).
                    if (!in_array($name, $only, true)) {
                        continue;
                    }
                } elseif ($hasOnly && !in_array($name, $only, true)) {
                    // `only` whitelist: a plain key absent from it is
                    // skipped. An except-only partial (no `only`) keeps plain keys.
                    continue;
                }
            } elseif ($value instanceof IgnoreFirstLoadInterface) {
                // Full load / normal visit: optional()/defer() props are absent.
                // A deferred prop additionally announces its group so the client
                // fetches it in a follow-up partial reload.
                if ($value instanceof DeferrableInterface) {
                    $this->deferredProps[$value->group()][] = (string) $key;
                }

                continue;
            }

            // rescue: a failure during RESOLUTION drops the deferred prop
            // (reported under rescuedProps) instead of failing the whole request.
            // Scope is the resolution callback only — a serialization failure of
            // a successfully-resolved value still propagates, same as the
            // no-rescue path below (and matching Inertia v3 semantics).
            if ($value instanceof DeferrableInterface && $value->rescue()) {
                try {
                    $resolved = $value->resolve();
                } catch (Throwable) {
                    $this->rescuedProps[] = (string) $key;

                    continue;
                }

                $props[$key] = $this->normalize($resolved);

                continue;
            }

            // Merge props (Inertia v3): resolve eagerly like a plain prop, but
            // record the key so the client merges instead of replaces — unless the
            // request opted it out via X-Inertia-Reset.
            if ($value instanceof MergeableInterface) {
                $this->recordMerge((string) $key, $value);
            }

            $props[$key] = $this->normalize($this->resolveValue($value));
        }

        return $props;
    }

    /**
     * Record a merge prop's key (and match paths) for emission on the page object.
     *
     * A key listed in `X-Inertia-Reset` is resolved but NOT announced — the client
     * replaces it this round instead of merging.
     */
    private function recordMerge(string $key, MergeableInterface $prop): void
    {
        if (in_array($key, $this->resetKeys(), true)) {
            return;
        }

        if ($prop->deep()) {
            $this->deepMergeProps[] = $key;
        } else {
            $this->mergeProps[] = $key;
        }

        foreach ($prop->matchOn() as $match) {
            $this->matchPropsOn[] = $key . '.' . $match;
        }
    }

    /**
     * Keys the client asked to reset (replace, not merge) via `X-Inertia-Reset`.
     *
     * @return list<string>
     */
    private function resetKeys(): array
    {
        return $this->parseKeyList($this->request->headers->get('X-Inertia-Reset') ?? '');
    }

    /**
     * Resolve a single prop value to its concrete form.
     *
     * {@see IgnoreFirstLoadInterface} wrappers resolve through their callable;
     * bare closures are invoked; every other value passes through untouched.
     */
    private function resolveValue(mixed $value): mixed
    {
        if ($value instanceof IgnoreFirstLoadInterface) {
            return $value->resolve();
        }

        if ($value instanceof MergeableInterface) {
            return $value->resolve();
        }

        if ($value instanceof Closure) {
            return $value();
        }

        return $value;
    }

    /**
     * Whether the incoming request is an Inertia (SPA) request.
     *
     * Single source of truth for the JSON-vs-HTML branch and every partial /
     * version check — they must agree on what counts as an Inertia request so a
     * malformed header (`X-Inertia: 0`/``) can never route to the JSON path in
     * one predicate and the HTML path in another.
     */
    private function isInertiaRequest(): bool
    {
        return (bool) $this->request->headers->get('X-Inertia');
    }

    /**
     * Whether this request is a partial reload addressed to this component.
     */
    private function isPartial(): bool
    {
        return $this->isInertiaRequest()
            && $this->request->headers->get('X-Inertia-Partial-Component') === $this->component;
    }

    /**
     * The `only` allow-set parsed from `X-Inertia-Partial-Data`.
     *
     * @return list<string>
     */
    private function partialOnly(): array
    {
        return $this->parseKeyList($this->request->headers->get('X-Inertia-Partial-Data') ?? '');
    }

    /**
     * The `except` deny-set parsed from `X-Inertia-Partial-Except`.
     *
     * @return list<string>
     */
    private function partialExcept(): array
    {
        return $this->parseKeyList($this->request->headers->get('X-Inertia-Partial-Except') ?? '');
    }

    /**
     * Split a comma-separated partial header into a trimmed, non-empty key list.
     *
     * @return list<string>
     */
    private function parseKeyList(string $header): array
    {
        return array_values(array_filter(
            array_map('trim', explode(',', $header)),
            static fn (string $key): bool => $key !== '',
        ));
    }

    /**
     * Normalize a value to a JSON-safe scalar/array.
     *
     * Passes through: null, scalar, array, JsonSerializable.
     * Normalizes via ObjectNormalizer: any other object (domain entities, DTOs).
     */
    private function normalize(mixed $value): mixed
    {
        if ($value === null || is_scalar($value)) {
            return $value;
        }

        if (is_array($value)) {
            return array_map(fn (mixed $v): mixed => $this->normalize($v), $value);
        }

        if ($value instanceof JsonSerializable) {
            return $value->jsonSerialize();
        }

        if (is_object($value)) {
            static $serializer = null;
            $serializer ??= new Serializer(
                [new ObjectNormalizer()],
                [new JsonEncoder()],
            );

            return $serializer->normalize($value, null, [
                ObjectNormalizer::CIRCULAR_REFERENCE_HANDLER => static fn (object $o): string => $o::class,
                ObjectNormalizer::SKIP_NULL_VALUES => false,
            ]);
        }

        return $value;
    }
}
